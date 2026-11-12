<?php

namespace App\Services;

use App\Enums\CargoEnum;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\Tenant;
use App\Services\Historico\AnalisisElectoral;
use App\Services\Historico\PlanCampania;

/**
 * Cuántos votos podrían salir de los registros del candidato.
 *
 * Un registro es una promesa de voto y la mayoría no se cumple. Se descuenta
 * en dos pasos:
 *
 * 1. Participación: de los registrados en un municipio, vota más o menos la
 *    misma proporción que votó ahí en la última elección del cargo (votos
 *    válidos / potencial, del histórico). Sin dato del municipio se usa el
 *    promedio de su departamento.
 * 2. Fidelidad: de los que votan, qué % vota por el candidato. No hay cómo
 *    medirla antes de la elección, así que es un supuesto con tres escenarios
 *    (FIDELIDAD) que el usuario puede mover en pantalla.
 *
 * Votos probables = Σ registros × participación × fidelidad.
 *
 * No es un margen de error estadístico (los registros no son una muestra al
 * azar): es un rango entre supuestos. Solo cuentan los registros del
 * formulario (no los importados del padrón) que votan dentro de la
 * circunscripción.
 */
class EstimacionVotos
{
    /** Escenarios de fidelidad por defecto, en %. */
    public const FIDELIDAD = ['pesimista' => 30, 'media' => 50, 'optimista' => 70];

    /** La participación sale de votos pasados sobre el potencial actual: se acota. */
    private const TOPE_PARTICIPACION = 95.0;

    public function __construct(
        private AnalisisElectoral $analisis,
        private PlanCampania $plan,
    ) {}

    public function calcular(Tenant $tenant): ?array
    {
        $ambito = $tenant->ambito();
        if (! $ambito) {
            return null;
        }
        $cargo = $tenant->cargo->value;

        $propios = fn () => PublicRegistration::query()->where('origen', '!=', 'importado');

        $porMunicipio = $propios()
            ->whereNotNull('voting_municipality_id')
            ->when($ambito === CargoEnum::AMBITO_MUNICIPAL, fn ($q) => $q->where('voting_municipality_id', $tenant->municipio_id))
            ->when($ambito === CargoEnum::AMBITO_DEPARTAMENTAL, fn ($q) => $q->whereIn(
                'voting_municipality_id',
                Municipio::where('departamento_id', $tenant->departamento_id)->select('id'),
            ))
            ->selectRaw('voting_municipality_id, COUNT(*) as total')
            ->groupBy('voting_municipality_id')
            ->pluck('total', 'voting_municipality_id')
            ->map(fn ($n) => (int) $n);

        $conMunicipio = $propios()->whereNotNull('voting_municipality_id')->count();
        $dentro = $porMunicipio->sum();

        // Participación por municipio, departamento por departamento (el
        // histórico se consulta así y queda en caché)
        $municipios = Municipio::whereIn('id', $porMunicipio->keys())->get(['id', 'nombre', 'departamento_id'])->keyBy('id');
        $participacion = [];
        $promedioDepto = [];
        $anio = null;
        foreach ($municipios->pluck('departamento_id')->unique() as $dep) {
            $datos = $this->analisis->municipios((int) $dep, $cargo);
            $anio = max($anio, $datos['ultimo']);
            $votos = $potencial = 0;
            foreach ($datos['municipios'] as $f) {
                if ($f['participacion'] !== null) {
                    $participacion[$f['id']] = min(self::TOPE_PARTICIPACION, $f['participacion']);
                    $votos += $f['votos'];
                    $potencial += $f['potencial'];
                }
            }
            $promedioDepto[$dep] = $potencial ? min(self::TOPE_PARTICIPACION, round($votos * 100 / $potencial, 1)) : null;
        }

        $filas = [];
        $base = 0.0;
        $sinParticipacion = 0;
        foreach ($porMunicipio as $id => $registros) {
            $m = $municipios[$id] ?? null;
            $p = $participacion[$id] ?? null;
            $fuente = $p !== null ? 'municipio' : null;
            if ($p === null && $m && ($promedioDepto[$m->departamento_id] ?? null) !== null) {
                $p = $promedioDepto[$m->departamento_id];
                $fuente = 'departamento';
            }
            if ($p === null) {
                $sinParticipacion += $registros;
            } else {
                $base += $registros * $p / 100;
            }

            $filas[] = [
                'municipio' => $m?->nombre ?? '—',
                'registros' => $registros,
                'participacion' => $p,
                'fuente' => $fuente,
                // Votantes probables (antes de la fidelidad)
                'votantes' => $p !== null ? round($registros * $p / 100, 1) : null,
            ];
        }
        usort($filas, fn ($a, $b) => $b['registros'] <=> $a['registros']);

        $contables = $dentro - $sinParticipacion;

        return [
            'cargo' => $tenant->cargo->label(),
            'anio' => $anio,
            'registros' => $dentro,
            'contables' => $contables,
            // Votantes probables: Σ registros × participación (sin fidelidad)
            'votantes' => round($base, 1),
            // Participación media ponderada de los registros contables
            'participacion' => $contables ? round($base * 100 / $contables, 1) : null,
            'sin_participacion' => $sinParticipacion,
            'fuera' => $conMunicipio - $dentro,
            'sin_municipio' => $propios()->whereNull('voting_municipality_id')->count(),
            'importados' => PublicRegistration::where('origen', 'importado')->count(),
            'fidelidad' => self::FIDELIDAD,
            'meta' => $this->meta($tenant, $cargo, $ambito),
            'municipios' => array_slice($filas, 0, 15),
        ];
    }

    /** Meta de votos del Plan de campaña (null en cargos nacionales o sin histórico). */
    private function meta(Tenant $tenant, string $cargo, string $ambito): ?int
    {
        if ($ambito === CargoEnum::AMBITO_NACIONAL || ! $tenant->departamento_id) {
            return null;
        }

        $m = $this->plan->meta($tenant->departamento_id, $cargo);
        if (! ($m['aplica'] ?? false)) {
            return null;
        }

        if ($m['municipal']) {
            $fila = collect($m['municipios'])->firstWhere('id', $tenant->municipio_id);

            return $fila['meta'] ?? null;
        }

        return $m['meta'];
    }
}
