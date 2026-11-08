<?php

namespace App\Services\Territorio;

use App\Enums\CargoEnum;
use App\Models\EventoCampana;
use App\Models\PartidoIdeologia;
use App\Models\PublicRegistration;
use App\Models\Tenant;
use App\Services\Historico\AnalisisElectoral;
use App\Services\Historico\AnalisisPuestos;
use App\Services\Historico\PlanCampania;
use App\Support\Partidos;

/**
 * Plan de giras: a dónde ir pronto, juntando la prioridad electoral de cada
 * municipio (PlanCampania::prioridades) con la presencia real de la campaña
 * (eventos de campaña).
 *
 * Urgencia = prioridad × (0,5 + 0,5 × días sin visita / 45, hasta 1). Un
 * municipio con un evento ya planeado baja a la mitad: ya está cubierto.
 *
 * - Cargos departamentales: por municipio.
 * - Cargos municipales: por puesto de votación de su municipio (dónde crecer).
 * - Cargos nacionales: todavía no hay plan.
 */
class PlanGiras
{
    private const DIAS_OLVIDO = 45;

    public function __construct(
        private AnalisisElectoral $analisis,
        private PlanCampania $plan,
        private AnalisisPuestos $puestos,
    ) {}

    public function calcular(Tenant $tenant, string $enfoque = 'equilibrado'): array
    {
        return match ($tenant->ambito()) {
            CargoEnum::AMBITO_DEPARTAMENTAL => $this->porMunicipio($tenant, $enfoque),
            CargoEnum::AMBITO_MUNICIPAL => $this->porPuesto($tenant),
            default => ['modo' => $tenant->ambito() ? 'nacional' : 'incompleto'],
        };
    }

    /** Cargo del histórico con el que se prioriza: el del candidato o, si no hay datos, gobernación. */
    private function cargoHistorico(Tenant $tenant, int $departamentoId): ?string
    {
        $cargos = $this->analisis->cargos($departamentoId);

        return collect([$tenant->cargo?->value, 'gobernacion', 'asamblea', 'alcaldia'])->first(fn ($c) => in_array($c, $cargos, true));
    }

    private function bloque(Tenant $tenant, array $ideologias): ?string
    {
        if (! $tenant->partido) {
            return null;
        }

        return PartidoIdeologia::bloque($ideologias[Partidos::clave($tenant->partido)] ?? AnalisisElectoral::inferir($tenant->partido, $ideologias));
    }

    private function porMunicipio(Tenant $tenant, string $enfoque): array
    {
        $dep = $tenant->departamento_id;
        $cargo = $this->cargoHistorico($tenant, $dep);
        if (! $cargo) {
            return ['modo' => 'sin_historico'];
        }

        $ideologias = PartidoIdeologia::mapa();
        $registros = PublicRegistration::query()->whereNotNull('voting_municipality_id')
            ->selectRaw('voting_municipality_id, COUNT(*) as n')->groupBy('voting_municipality_id')
            ->pluck('n', 'voting_municipality_id')->map(fn ($n) => (int) $n)->all();
        $p = $this->plan->prioridades($dep, $cargo, $this->bloque($tenant, $ideologias), $enfoque, $registros, null, null, $ideologias);

        // Presencia de la campaña por municipio
        $hoy = now()->startOfDay();
        $eventos = EventoCampana::query()->where('estado', '!=', 'cancelado')->get(['id', 'municipio_id', 'fecha', 'estado', 'asistio_candidato', 'titulo']);
        $presencia = $eventos->groupBy('municipio_id')->map(function ($g) use ($hoy) {
            $hechos = $g->where('estado', 'realizado');
            $conCandidato = $hechos->where('asistio_candidato', true);
            $planeado = $g->where('estado', 'planeado')->filter(fn ($e) => $e->fecha->gte($hoy))->sortBy('fecha')->first();

            return [
                'eventos' => $hechos->count(),
                'ultimo' => $hechos->max('fecha'),
                'ultimo_candidato' => $conCandidato->max('fecha'),
                'planeado' => $planeado,
            ];
        });

        $filas = [];
        foreach ($p['municipios'] as $m) {
            $pr = $presencia[$m['id']] ?? ['eventos' => 0, 'ultimo' => null, 'ultimo_candidato' => null, 'planeado' => null];
            $dias = $pr['ultimo'] ? (int) $pr['ultimo']->copy()->startOfDay()->diffInDays($hoy) : null;
            $diasCandidato = $pr['ultimo_candidato'] ? (int) $pr['ultimo_candidato']->copy()->startOfDay()->diffInDays($hoy) : null;

            $factor = 0.5 + 0.5 * min(1, ($dias ?? self::DIAS_OLVIDO) / self::DIAS_OLVIDO);
            $urgencia = (int) round($m['puntaje'] * $factor * ($pr['planeado'] ? 0.5 : 1));

            $motivos = [];
            if (! $pr['eventos']) {
                $motivos[] = 'Nunca se ha estado';
            } elseif ($dias >= 30) {
                $motivos[] = "Sin eventos hace {$dias} días";
            }
            if ($m['accion'] === 'persuadir' && ($diasCandidato === null || $diasCandidato > 60)) {
                $motivos[] = 'Disputado y el candidato no ha ido';
            }
            if ($m['accion'] === 'movilizar' && ($dias === null || $dias > 30)) {
                $motivos[] = 'Zona afín sin atender';
            }
            if (($m['falta'] ?? 0) > 0 && $m['puesto'] <= 5) {
                $motivos[] = 'Entre los 5 más prioritarios';
            }

            $filas[] = $m + [
                'urgencia' => $urgencia,
                'eventos' => $pr['eventos'],
                'dias_sin_visita' => $dias,
                'dias_sin_candidato' => $diasCandidato,
                'planeado' => $pr['planeado'],
                'motivos' => $motivos,
            ];
        }
        usort($filas, fn ($a, $b) => [$b['urgencia'], $b['potencial'] ?? 0] <=> [$a['urgencia'], $a['potencial'] ?? 0]);

        return [
            'modo' => 'municipios',
            'cargo' => $cargo,
            'enfoque' => $enfoque,
            'bloque' => $p['bloque'],
            'municipios' => $filas,
            'sin_visita' => collect($filas)->where('eventos', 0)->count(),
            'proximos' => $eventos->where('estado', 'planeado')->filter(fn ($e) => $e->fecha->gte($hoy))->sortBy('fecha')->take(15)->values(),
        ];
    }

    /** Cargos municipales: puestos de su municipio donde el partido puede crecer. */
    private function porPuesto(Tenant $tenant): array
    {
        $municipioId = $tenant->municipio_id;
        $cargos = $this->puestos->cargos($municipioId);
        $cargo = collect([$tenant->cargo?->value, 'alcaldia', 'concejo', 'gobernacion'])->first(fn ($c) => isset($cargos[$c]));
        if (! $cargo) {
            return ['modo' => 'sin_puestos'];
        }

        $anio = end($cargos[$cargo]);
        $clave = $tenant->partido ? Partidos::clave($tenant->partido) : null;
        $d = $this->puestos->puestos($municipioId, $cargo, $anio, $clave);
        $maxVotos = max(1, collect($d['puestos'])->max('votos'));
        $maxBrecha = max(1, collect($d['puestos'])->max('brecha'));

        $filas = collect($d['puestos'])->map(function ($p) use ($maxVotos, $maxBrecha) {
            // Tamaño del puesto y votos que le faltan al partido para su promedio
            $p['urgencia'] = (int) round(100 * (0.5 * $p['votos'] / $maxVotos + 0.5 * $p['brecha'] / $maxBrecha));

            return $p;
        })->sortByDesc('urgencia')->values()->all();

        return [
            'modo' => 'puestos',
            'cargo' => $cargo,
            'anio' => $anio,
            'partido' => $d['partido_nombre'],
            'partido_propio' => $clave !== null && $d['clave'] === $clave,
            'municipio' => $tenant->municipio?->nombre,
            'municipio_id' => $municipioId,
            'puestos' => $filas,
            'proximos' => EventoCampana::query()->where('estado', 'planeado')->where('fecha', '>=', now()->startOfDay())
                ->orderBy('fecha')->limit(15)->get(['id', 'titulo', 'fecha', 'lugar', 'municipio_id']),
        ];
    }
}
