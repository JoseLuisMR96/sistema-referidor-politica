<?php

namespace App\Services\Redes;

use App\Models\RedCuenta;
use App\Models\RedMetrica;
use App\Models\RedTono;
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
     * Cuentas DEL CANDIDATO (sin rivales).
     *
     * @return list<array{
     *     red: string, usuario: string, nombre: string, url: ?string, es_rival: bool,
     *     seguidores: ?int, cambio_7d: ?int, cambio_7d_pct: ?float, interacciones_7d: ?int,
     *     engagement: ?float, publicaciones_7d: ?int,
     *     semanas: list<array{semana: string, seguidores: ?int}>,
     *     ultimo_sync_at: ?string, ultimo_error: ?string
     * }>
     */
    public function resumen(int $semanas = 8): array
    {
        return RedCuenta::query()
            ->where('activo', true)
            ->where('tipo', RedCuenta::TIPO_PROPIA)
            ->orderBy('red')
            ->get()
            ->map(fn (RedCuenta $cuenta) => $this->fila($cuenta, $semanas))
            ->all();
    }

    /**
     * El Instagram del candidato frente a sus rivales, ordenado por quién crece
     * más rápido (porcentaje en 7 días). Las cuentas sin una semana de historia
     * van al final.
     *
     * @return list<array<string, mixed>> mismas claves que resumen()
     */
    public function rivales(int $semanas = 8): array
    {
        $cuentas = RedCuenta::query()
            ->where('activo', true)
            ->where('red', RedCuenta::INSTAGRAM)
            ->get();

        // Sin rivales registrados no hay comparación que mostrar
        if (! $cuentas->contains(fn (RedCuenta $c) => $c->esRival())) {
            return [];
        }

        return $cuentas
            ->map(fn (RedCuenta $cuenta) => $this->fila($cuenta, $semanas))
            ->sort(function ($a, $b) {
                // Primero los que tienen dato de crecimiento, de mayor a menor
                if (($a['cambio_7d_pct'] === null) !== ($b['cambio_7d_pct'] === null)) {
                    return $a['cambio_7d_pct'] === null ? 1 : -1;
                }

                return ($b['cambio_7d_pct'] ?? 0) <=> ($a['cambio_7d_pct'] ?? 0)
                    ?: ($b['seguidores'] ?? 0) <=> ($a['seguidores'] ?? 0);
            })
            ->values()
            ->all();
    }

    /**
     * Tono de los comentarios de las cuentas del candidato, por semana.
     *
     * @return array{
     *     total: int, a_favor: int, en_contra: int, neutro: int,
     *     semanas: list<array{semana: string, a_favor: int, en_contra: int, neutro: int, total: int}>,
     *     temas: array<string, int>
     * }
     */
    public function tono(int $semanas = 6): array
    {
        $desde = Carbon::today()->subWeeks($semanas - 1)->startOfWeek();

        $filas = RedTono::query()
            ->whereDate('fecha', '>=', $desde)
            ->whereHas('cuenta', fn ($q) => $q->where('tipo', RedCuenta::TIPO_PROPIA))
            ->get();

        $porSemana = $filas->groupBy(fn (RedTono $t) => $t->fecha->copy()->startOfWeek()->toDateString());

        $serie = [];
        for ($i = $semanas - 1; $i >= 0; $i--) {
            $inicio = Carbon::today()->subWeeks($i)->startOfWeek()->toDateString();
            $semana = $porSemana->get($inicio, collect());
            $fila = [
                'semana' => $inicio,
                'a_favor' => (int) $semana->sum('a_favor'),
                'en_contra' => (int) $semana->sum('en_contra'),
                'neutro' => (int) $semana->sum('neutro'),
            ];
            $fila['total'] = $fila['a_favor'] + $fila['en_contra'] + $fila['neutro'];
            $serie[] = $fila;
        }

        $temas = [];
        foreach ($filas as $fila) {
            foreach ($fila->temas ?? [] as $tema => $veces) {
                $temas[$tema] = ($temas[$tema] ?? 0) + (int) $veces;
            }
        }
        arsort($temas);

        $aFavor = (int) $filas->sum('a_favor');
        $enContra = (int) $filas->sum('en_contra');
        $neutro = (int) $filas->sum('neutro');

        return [
            'total' => $aFavor + $enContra + $neutro,
            'a_favor' => $aFavor,
            'en_contra' => $enContra,
            'neutro' => $neutro,
            'semanas' => $serie,
            'temas' => array_slice($temas, 0, 8, true),
        ];
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

    /** @return array<string, mixed> */
    private function fila(RedCuenta $cuenta, int $semanas): array
    {
        $metricas = RedMetrica::query()
            ->where('red_cuenta_id', $cuenta->id)
            ->whereDate('fecha', '>=', Carbon::today()->subWeeks($semanas)->startOfWeek())
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

        $seguidores = $ultima?->seguidores;
        $interacciones = $ultima?->interacciones_7d;

        return [
            'red' => $cuenta->red,
            'usuario' => $cuenta->usuario,
            'nombre' => $cuenta->nombreVisible(),
            'url' => $cuenta->url,
            'es_rival' => $cuenta->esRival(),
            'seguidores' => $seguidores,
            'cambio_7d' => $cambio,
            'cambio_7d_pct' => ($cambio !== null && $haceUnaSemana->seguidores > 0)
                ? round($cambio / $haceUnaSemana->seguidores * 100, 2)
                : null,
            'interacciones_7d' => $interacciones,
            // Engagement: interacciones de la semana por cada 100 seguidores.
            // Compara cuentas de distinto tamaño mejor que las cifras brutas.
            'engagement' => ($seguidores && $interacciones !== null)
                ? round($interacciones / $seguidores * 100, 2)
                : null,
            'publicaciones_7d' => $ultima?->datos['publicaciones_7d'] ?? null,
            'semanas' => $this->porSemana($metricas, $semanas),
            // Texto ISO: el resultado va a una propiedad pública de Livewire
            'ultimo_sync_at' => $cuenta->ultimo_sync_at?->toIso8601String(),
            'ultimo_error' => $cuenta->ultimo_error,
        ];
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
