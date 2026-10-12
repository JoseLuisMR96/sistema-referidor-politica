<?php

namespace App\Services\Redes;

use App\Models\RedCuenta;
use App\Models\RedMetrica;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Resumen de redes del candidato del contexto para el dashboard.
 *
 * Las consultas pasan por el scope del tenant: llamarlo con un tenant elegido.
 */
class RedesStats
{
    /**
     * @return list<array{
     *     red: string, usuario: string, url: ?string,
     *     seguidores: ?int, cambio_7d: ?int, cambio_7d_pct: ?float, interacciones_7d: ?int,
     *     semanas: list<array{semana: string, seguidores: ?int}>,
     *     ultimo_sync_at: ?string, ultimo_error: ?string
     * }>
     */
    public function resumen(int $semanas = 8): array
    {
        $desde = Carbon::today()->subWeeks($semanas)->startOfWeek();

        return RedCuenta::query()
            ->where('activo', true)
            ->orderBy('red')
            ->get()
            ->map(function (RedCuenta $cuenta) use ($desde, $semanas) {
                $metricas = RedMetrica::query()
                    ->where('red_cuenta_id', $cuenta->id)
                    ->whereDate('fecha', '>=', $desde)
                    ->orderBy('fecha')
                    ->get();

                $ultima = $metricas->last();

                // La foto más reciente de hace al menos 7 días
                $haceUnaSemana = $metricas
                    ->filter(fn (RedMetrica $m) => $m->fecha->lte(Carbon::today()->subDays(7)))
                    ->last();

                $cambio = ($ultima?->seguidores !== null && $haceUnaSemana?->seguidores !== null)
                    ? $ultima->seguidores - $haceUnaSemana->seguidores
                    : null;

                return [
                    'red' => $cuenta->red,
                    'usuario' => $cuenta->usuario,
                    'url' => $cuenta->url,
                    'seguidores' => $ultima?->seguidores,
                    'cambio_7d' => $cambio,
                    'cambio_7d_pct' => ($cambio !== null && $haceUnaSemana->seguidores > 0)
                        ? round($cambio / $haceUnaSemana->seguidores * 100, 2)
                        : null,
                    'interacciones_7d' => $ultima?->interacciones_7d,
                    'semanas' => $this->porSemana($metricas, $semanas),
                    // Texto ISO: el resultado va a una propiedad pública de Livewire
                    'ultimo_sync_at' => $cuenta->ultimo_sync_at?->toIso8601String(),
                    'ultimo_error' => $cuenta->ultimo_error,
                ];
            })
            ->all();
    }

    /**
     * Totales de un resumen (suma de todas las cuentas del candidato).
     *
     * @param  list<array<string, mixed>>  $resumen
     * @return array{seguidores: int, cambio_7d: ?int, interacciones_7d: int, cuentas: int}
     */
    public function totales(array $resumen): array
    {
        $cambios = array_filter(array_column($resumen, 'cambio_7d'), fn ($c) => $c !== null);

        return [
            'seguidores' => (int) array_sum(array_map('intval', array_column($resumen, 'seguidores'))),
            'cambio_7d' => $cambios ? (int) array_sum($cambios) : null,
            'interacciones_7d' => (int) array_sum(array_map('intval', array_column($resumen, 'interacciones_7d'))),
            'cuentas' => count($resumen),
        ];
    }

    /**
     * Comparativo entre candidatos para "Todos los tenants".
     *
     * Recorre cada tenant con su contexto fijado, así resumen() sigue pasando
     * por el scope de tenant y no se mezclan cuentas.
     *
     * @return list<array{tenant: string, descripcion: ?string, seguidores: int, cambio_7d: ?int, interacciones_7d: int, cuentas: int}>
     */
    public function comparativo(): array
    {
        $contexto = app(TenantContext::class);

        return Tenant::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($tenant) => [
                'tenant' => $tenant->nombre,
                'descripcion' => $tenant->descripcion,
                ...$this->totales($contexto->con($tenant->id, fn () => $this->resumen())),
            ])
            ->filter(fn ($fila) => $fila['cuentas'] > 0)
            ->sortByDesc('seguidores')
            ->values()
            ->all();
    }

    /**
     * Seguidores al cierre de cada semana (la última foto de esa semana).
     * Agrupado en PHP para no depender de funciones de fecha de MySQL.
     *
     * @return list<array{semana: string, seguidores: ?int}>
     */
    private function porSemana($metricas, int $semanas): array
    {
        $porSemana = $metricas->groupBy(fn (RedMetrica $m) => $m->fecha->copy()->startOfWeek()->toDateString());

        $salida = [];
        for ($i = $semanas - 1; $i >= 0; $i--) {
            $inicio = Carbon::today()->subWeeks($i)->startOfWeek()->toDateString();
            $salida[] = [
                'semana' => $inicio,
                'seguidores' => $porSemana->get($inicio)?->last()?->seguidores,
            ];
        }

        return $salida;
    }
}
