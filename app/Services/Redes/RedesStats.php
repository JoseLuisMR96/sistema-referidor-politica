<?php

namespace App\Services\Redes;

use App\Models\RedCuenta;
use App\Models\RedMetrica;
use App\Models\RedPublicacion;
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

    /** Franjas del día (hora de Colombia) => [desde, hasta) */
    public const FRANJAS = [
        'madrugada' => ['Madrugada (0–6 h)', 0, 6],
        'manana' => ['Mañana (6–12 h)', 6, 12],
        'tarde' => ['Tarde (12–18 h)', 12, 18],
        'noche' => ['Noche (18–24 h)', 18, 24],
    ];

    public const DIAS = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    /**
     * Qué contenido funciona en Instagram, del candidato y de sus rivales, en
     * los últimos $dias: ritmo de publicación, rendimiento por tipo, día,
     * franja y tema, y las mejores publicaciones de la semana.
     *
     * El rendimiento es el engagement: interacciones (me gusta + comentarios)
     * por cada 100 seguidores de la cuenta, para comparar cuentas de distinto
     * tamaño. Las agrupaciones separan "candidato" de "rivales".
     *
     * @return array<string, mixed>
     */
    public function contenido(int $dias = 30): array
    {
        $cuentas = RedCuenta::query()
            ->where('activo', true)
            ->where('red', RedCuenta::INSTAGRAM)
            ->get()
            ->keyBy('id');

        $publicaciones = $cuentas->isEmpty() ? collect() : RedPublicacion::query()
            ->whereIn('red_cuenta_id', $cuentas->keys())
            ->where('publicada_at', '>=', Carbon::now()->subDays($dias))
            ->orderByDesc('publicada_at')
            ->get();

        if ($publicaciones->isEmpty()) {
            return ['hay' => false, 'dias' => $dias];
        }

        // Seguidores actuales de cada cuenta (última foto con dato)
        $seguidores = RedMetrica::query()
            ->whereIn('red_cuenta_id', $cuentas->keys())
            ->whereNotNull('seguidores')
            ->whereDate('fecha', '>=', Carbon::today()->subDays(30))
            ->orderBy('fecha')
            ->get(['red_cuenta_id', 'seguidores'])
            ->groupBy('red_cuenta_id')
            ->map(fn ($g) => (int) $g->last()->seguidores);

        $vacio = fn () => ['n' => 0, 'eng' => 0.0, 'con_eng' => 0, 'interacciones' => 0];
        $sumar = function (array &$acc, ?float $eng, int $interacciones) {
            $acc['n']++;
            $acc['interacciones'] += $interacciones;
            if ($eng !== null) {
                $acc['eng'] += $eng;
                $acc['con_eng']++;
            }
        };
        $cerrar = fn (array $acc) => [
            'n' => $acc['n'],
            'engagement' => $acc['con_eng'] ? round($acc['eng'] / $acc['con_eng'], 2) : null,
            'interacciones' => $acc['n'] ? (int) round($acc['interacciones'] / $acc['n']) : 0,
        ];

        $porCuenta = [];
        $grupos = ['tipo' => [], 'dia' => [], 'franja' => [], 'tema' => []];
        $filasTop = [];

        foreach ($publicaciones as $p) {
            $cuenta = $cuentas[$p->red_cuenta_id];
            $grupo = $cuenta->esRival() ? 'rivales' : 'candidato';
            $seg = $seguidores[$p->red_cuenta_id] ?? null;
            $interacciones = $p->interacciones();
            $eng = $seg ? $interacciones * 100 / $seg : null;
            $fecha = $p->publicada_at?->copy()->timezone('America/Bogota');

            $porCuenta[$p->red_cuenta_id] ??= $vacio() + ['repro' => 0, 'con_repro' => 0];
            $sumar($porCuenta[$p->red_cuenta_id], $eng, $interacciones);
            if ($p->reproducciones !== null) {
                $porCuenta[$p->red_cuenta_id]['repro'] += $p->reproducciones;
                $porCuenta[$p->red_cuenta_id]['con_repro']++;
            }

            $claves = [
                'tipo' => $p->tipo ?: 'otro',
                'tema' => $p->tema ?: ($p->texto ? 'sin_clasificar' : 'sin_texto'),
            ];
            if ($fecha) {
                $claves['dia'] = $fecha->dayOfWeekIso;
                $claves['franja'] = collect(self::FRANJAS)->search(fn ($f) => $fecha->hour >= $f[1] && $fecha->hour < $f[2]);
            }
            foreach ($claves as $dim => $clave) {
                $grupos[$dim][$clave][$grupo] ??= $vacio();
                $sumar($grupos[$dim][$clave][$grupo], $eng, $interacciones);
            }

            if ($fecha && $p->publicada_at->gte(Carbon::now()->subDays(7))) {
                $filasTop[] = [
                    'cuenta' => $cuenta->nombreVisible(),
                    'usuario' => $cuenta->usuario,
                    'es_rival' => $cuenta->esRival(),
                    'tipo' => RedPublicacion::TIPOS[$p->tipo] ?? $p->tipo,
                    'tema' => RedPublicacion::TEMAS[$p->tema] ?? null,
                    'texto' => $p->texto ? mb_strimwidth($p->texto, 0, 160, '…') : null,
                    'enlace' => $p->enlace,
                    'fecha' => $fecha->toIso8601String(),
                    'me_gusta' => $p->me_gusta,
                    'comentarios' => $p->comentarios,
                    'reproducciones' => $p->reproducciones,
                    'engagement' => $eng !== null ? round($eng, 2) : null,
                    'interacciones' => $interacciones,
                ];
            }
        }

        // Cada dimensión: clave => ['candidato' => fila, 'rivales' => fila]
        $tabla = function (array $dim, array $orden = []) use ($cerrar) {
            $salida = [];
            foreach ($dim as $clave => $porGrupo) {
                $salida[$clave] = array_map($cerrar, $porGrupo);
            }
            if ($orden) {
                uksort($salida, fn ($a, $b) => array_search($a, $orden) <=> array_search($b, $orden));
            } else {
                uasort($salida, fn ($a, $b) => (($b['candidato']['n'] ?? 0) + ($b['rivales']['n'] ?? 0))
                    <=> (($a['candidato']['n'] ?? 0) + ($a['rivales']['n'] ?? 0)));
            }

            return $salida;
        };

        $porTipo = $tabla($grupos['tipo']);
        $porDia = $tabla($grupos['dia'], array_keys(self::DIAS));
        $porFranja = $tabla($grupos['franja'], array_keys(self::FRANJAS));
        $porTema = $tabla($grupos['tema']);

        // Lo mejor del candidato: necesita al menos 2 publicaciones para no
        // sacar conclusiones de una sola
        $mejor = function (array $dim) {
            $candidatas = array_filter($dim, fn ($g) => ($g['candidato']['n'] ?? 0) >= 2 && ($g['candidato']['engagement'] ?? null) !== null);
            if (! $candidatas) {
                return null;
            }
            uasort($candidatas, fn ($a, $b) => $b['candidato']['engagement'] <=> $a['candidato']['engagement']);

            return array_key_first($candidatas);
        };

        usort($filasTop, fn ($a, $b) => ($b['engagement'] ?? -1) <=> ($a['engagement'] ?? -1) ?: $b['interacciones'] <=> $a['interacciones']);

        $semanas = $dias / 7;

        return [
            'hay' => true,
            'dias' => $dias,
            'hay_rivales' => $cuentas->contains(fn (RedCuenta $c) => $c->esRival()),
            'cuentas' => collect($porCuenta)->map(function ($acc, $id) use ($cuentas, $cerrar, $semanas, $seguidores) {
                $c = $cuentas[$id];

                return $cerrar($acc) + [
                    'cuenta' => $c->nombreVisible(),
                    'usuario' => $c->usuario,
                    'es_rival' => $c->esRival(),
                    'seguidores' => $seguidores[$id] ?? null,
                    'por_semana' => round($acc['n'] / $semanas, 1),
                    'reproducciones' => $acc['con_repro'] ? (int) round($acc['repro'] / $acc['con_repro']) : null,
                ];
            })->sortBy([['es_rival', 'asc'], ['engagement', 'desc']])->values()->all(),
            'por_tipo' => $porTipo,
            'por_dia' => $porDia,
            'por_franja' => $porFranja,
            'por_tema' => $porTema,
            'mejor' => [
                'tipo' => $mejor($porTipo),
                'dia' => $mejor($porDia),
                'franja' => $mejor($porFranja),
                'tema' => $mejor(array_diff_key($porTema, ['sin_clasificar' => 1, 'sin_texto' => 1])),
            ],
            'sin_clasificar' => (int) $publicaciones->whereNull('tema')->whereNotNull('texto')->count(),
            'top' => array_slice($filasTop, 0, 6),
        ];
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
