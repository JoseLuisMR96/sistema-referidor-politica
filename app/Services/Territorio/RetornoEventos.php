<?php

namespace App\Services\Territorio;

use App\Models\EventoCampana;
use App\Models\PublicRegistration;
use Illuminate\Support\Carbon;

/**
 * Retorno de los eventos de campaña: cuántos registros nuevos aparecen en el
 * municipio del evento en los días siguientes, frente a lo que se esperaba
 * por el ritmo de los 30 días anteriores, y cuánto costó cada uno.
 *
 * - Esperado = registros diarios promedio de los 30 días antes × días de la ventana.
 * - Atribuibles = máx(0, registros en la ventana − esperado). Si otro evento
 *   cayó en la misma ventana del mismo municipio, se reparten entre ellos.
 * - Costo = lo gastado (o lo asignado, si aún no se legaliza) / atribuibles.
 *
 * Es una coincidencia en el tiempo, no una prueba de que el evento trajo esos
 * registros. No cuenta los importados del padrón (no son registros nuevos).
 * Usa el candidato del contexto (scope de los modelos).
 */
class RetornoEventos
{
    public const VENTANAS = [7, 14, 30];

    private const BASE_DIAS = 30;

    public function calcular(int $ventana = 14, ?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $ventana = in_array($ventana, self::VENTANAS, true) ? $ventana : 14;

        $eventos = EventoCampana::query()
            ->with(['municipio:id,nombre', 'responsable:id,nombre,rol'])
            ->where('estado', 'realizado')
            ->whereNotNull('municipio_id')
            ->when($desde, fn ($q) => $q->where('fecha', '>=', $desde->copy()->startOfDay()))
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta->copy()->endOfDay()))
            ->orderBy('fecha')
            ->get();

        if ($eventos->isEmpty()) {
            return $this->vacio($ventana);
        }

        // Registros por municipio y día, solo de los municipios con eventos
        $inicio = $eventos->min('fecha')->copy()->subDays(self::BASE_DIAS)->startOfDay();
        $porDia = [];
        PublicRegistration::query()
            ->whereIn('voting_municipality_id', $eventos->pluck('municipio_id')->unique())
            ->where('created_at', '>=', $inicio)
            ->where(fn ($q) => $q->whereNull('origen')->orWhere('origen', '!=', 'importado'))
            ->selectRaw('voting_municipality_id as m, DATE(created_at) as d, COUNT(*) as n')
            ->groupByRaw('voting_municipality_id, DATE(created_at)')
            ->toBase()->get()
            ->each(function ($r) use (&$porDia) {
                $porDia[(int) $r->m][substr((string) $r->d, 0, 10)] = (int) $r->n;
            });

        $sumar = function (int $municipio, Carbon $desde, int $dias) use ($porDia): int {
            $total = 0;
            for ($i = 0; $i < $dias; $i++) {
                $total += $porDia[$municipio][$desde->copy()->addDays($i)->toDateString()] ?? 0;
            }

            return $total;
        };

        $hoy = now()->startOfDay();
        $filas = [];
        foreach ($eventos as $e) {
            $dia = $e->fecha->copy()->startOfDay();
            $antes = $sumar($e->municipio_id, $dia->copy()->subDays(self::BASE_DIAS), self::BASE_DIAS);
            // Ventana desde el día del evento; si aún no termina, solo los días que van
            $dias = (int) min($ventana, max(1, $dia->diffInDays($hoy) + 1));
            $despues = $sumar($e->municipio_id, $dia, $dias);
            $esperado = round($antes / self::BASE_DIAS * $dias, 1);

            // Otros eventos realizados en el mismo municipio dentro de la ventana
            $solapados = $eventos->filter(fn ($o) => $o->municipio_id === $e->municipio_id
                && $o->fecha->copy()->startOfDay()->between($dia->copy()->subDays($ventana - 1), $dia->copy()->addDays($ventana - 1)))->count();

            $atribuibles = round(max(0, $despues - $esperado) / max(1, $solapados), 1);
            $costo = (float) ($e->monto_gastado ?? $e->monto_asignado);

            $filas[] = [
                'id' => $e->id,
                'titulo' => $e->titulo,
                'fecha' => $e->fecha,
                'tipo' => $e->tipoTexto(),
                'municipio_id' => $e->municipio_id,
                'municipio' => $e->municipio?->nombre,
                'candidato' => $e->asistio_candidato,
                'responsable_id' => $e->responsable_id,
                'responsable' => $e->responsable?->nombre,
                'antes' => $antes,
                'despues' => $despues,
                'esperado' => $esperado,
                'atribuibles' => $atribuibles,
                'solapados' => $solapados,
                'dias' => $dias,
                'en_curso' => $dias < $ventana,
                'costo' => $costo,
                'costo_registro' => $atribuibles >= 1 && $costo > 0 ? round($costo / $atribuibles) : null,
            ];
        }

        $agrupar = function (callable $clave, callable $nombre) use ($filas) {
            return collect($filas)->groupBy($clave)->map(function ($g, $k) use ($nombre) {
                $atrib = $g->sum('atribuibles');
                $costo = $g->sum('costo');

                return [
                    'clave' => $k,
                    'nombre' => $nombre($g->first(), $k),
                    'eventos' => $g->count(),
                    'atribuibles' => round($atrib, 1),
                    'por_evento' => round($atrib / max(1, $g->count()), 1),
                    'costo' => $costo,
                    'costo_registro' => $atrib >= 1 && $costo > 0 ? round($costo / $atrib) : null,
                ];
            })->sortByDesc('atribuibles')->values()->all();
        };

        return [
            'ventana' => $ventana,
            'base_dias' => self::BASE_DIAS,
            'eventos' => collect($filas)->sortByDesc('fecha')->values()->all(),
            'totales' => [
                'eventos' => count($filas),
                'despues' => array_sum(array_column($filas, 'despues')),
                'atribuibles' => round(array_sum(array_column($filas, 'atribuibles')), 1),
                'costo' => array_sum(array_column($filas, 'costo')),
            ],
            'por_quien' => $agrupar(fn ($f) => $f['candidato'] ? 'candidato' : 'equipo',
                fn ($f, $k) => $k === 'candidato' ? 'Con el candidato' : 'Solo el equipo'),
            'por_persona' => array_values(array_filter($agrupar(fn ($f) => (string) ($f['responsable_id'] ?? ''),
                fn ($f) => $f['responsable'] ?? 'Sin responsable'), fn ($r) => $r['clave'] !== '')),
            'por_municipio' => $agrupar(fn ($f) => (string) $f['municipio_id'], fn ($f) => $f['municipio']),
            'por_tipo' => $agrupar(fn ($f) => $f['tipo'], fn ($f) => $f['tipo']),
        ];
    }

    private function vacio(int $ventana): array
    {
        return [
            'ventana' => $ventana, 'base_dias' => self::BASE_DIAS, 'eventos' => [],
            'totales' => ['eventos' => 0, 'despues' => 0, 'atribuibles' => 0, 'costo' => 0],
            'por_quien' => [], 'por_persona' => [], 'por_municipio' => [], 'por_tipo' => [],
        ];
    }
}
