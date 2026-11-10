<?php

namespace App\Services\Historico;

use App\Enums\CargoEnum;
use App\Models\PartidoIdeologia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Plan de campaña: lecturas del histórico para decidir dónde trabajar.
 *
 * - meta(): cuántos votos hacen falta para ganar el cargo y cómo se reparten
 *   por municipio, frente a los registros del candidato.
 * - conquistar(): dónde hay gente habilitada que no vota o vota en blanco.
 * - disputa(): municipios con poco margen o que cambian de ganador.
 * - prioridades(): junta todo en un orden de "dónde ir primero".
 *
 * Todo es por municipio y en cifras agregadas. Parte de AnalisisElectoral::datos().
 */
class PlanCampania
{
    /** Cargos que se ganan por cifra repartidora (listas). */
    public const CORPORACIONES = ['asamblea', 'camara', 'concejo'];

    /** Cargos con meta calculable desde los datos del departamento. */
    public const CON_META = ['gobernacion', 'asamblea', 'camara', 'alcaldia', 'concejo'];

    /**
     * Enfoques del orden de prioridades: peso de cada criterio.
     * tamano = gente, falta = lo que falta para la meta, disputa, afinidad
     * con el bloque del candidato, conquistar = abstención + voto en blanco.
     */
    public const ENFOQUES = [
        'equilibrado' => ['Equilibrado', ['tamano' => 30, 'falta' => 25, 'disputa' => 20, 'afinidad' => 15, 'conquistar' => 10]],
        'movilizar' => ['Cuidar lo propio', ['tamano' => 20, 'falta' => 30, 'disputa' => 10, 'afinidad' => 40, 'conquistar' => 0]],
        'persuadir' => ['Ir por lo disputado', ['tamano' => 25, 'falta' => 20, 'disputa' => 40, 'afinidad' => 10, 'conquistar' => 5]],
        'nuevos' => ['Votantes nuevos', ['tamano' => 25, 'falta' => 20, 'disputa' => 10, 'afinidad' => 5, 'conquistar' => 40]],
    ];

    public const CRITERIOS = [
        'tamano' => 'Mucha gente',
        'falta' => 'Falta mucho para la meta',
        'disputa' => 'Municipio disputado',
        'afinidad' => 'Zona afín',
        'conquistar' => 'Muchos por conquistar',
    ];

    /**
     * Curules de la Cámara por departamento (circunscripción territorial,
     * 2018–2026). Los elegidos que trae la Registraduría para la Cámara
     * vienen incompletos, así que no se cuentan de ahí.
     */
    public const CURULES_CAMARA = [
        'AMAZONAS' => 2, 'ANTIOQUIA' => 17, 'ARAUCA' => 2, 'ATLANTICO' => 7, 'BOGOTA' => 18,
        'BOLIVAR' => 6, 'BOYACA' => 6, 'CALDAS' => 5, 'CAQUETA' => 2, 'CASANARE' => 2,
        'CAUCA' => 4, 'CESAR' => 4, 'CHOCO' => 2, 'CORDOBA' => 5, 'CUNDINAMARCA' => 7,
        'GUAINIA' => 2, 'GUAVIARE' => 2, 'HUILA' => 4, 'LA GUAJIRA' => 2, 'MAGDALENA' => 5,
        'META' => 3, 'NARINO' => 5, 'NORTE DE SANTANDER' => 5, 'PUTUMAYO' => 2, 'QUINDIO' => 3,
        'RISARALDA' => 4, 'SAN ANDRES' => 2, 'SANTANDER' => 7, 'SUCRE' => 3, 'TOLIMA' => 6,
        'VALLE DEL CAUCA' => 13, 'VAUPES' => 2, 'VICHADA' => 2,
    ];

    public function __construct(private AnalisisElectoral $analisis) {}

    private function recordar(string $metodo, array $args, \Closure $calcular): mixed
    {
        $version = (int) Cache::get(HistoricoElectoralStats::CACHE_VERSION, 1);

        return Cache::rememberForever("plan:{$version}.1:{$metodo}:".md5(serialize($args)), $calcular);
    }

    public static function esCorporacion(string $cargo): bool
    {
        return in_array($cargo, self::CORPORACIONES, true);
    }

    public static function esMunicipal(string $cargo): bool
    {
        return CargoEnum::tryFrom($cargo)?->ambito() === CargoEnum::AMBITO_MUNICIPAL;
    }

    // ===================== Meta =====================

    /**
     * Curules de la corporación según los elegidos de la última elección que
     * los trae (desde 2019 la Registraduría no los publica).
     *
     * @return array{anio: ?int, departamento: ?int, municipios: array<int, int>}
     */
    public function curules(int $departamentoId, string $cargo): array
    {
        if ($cargo === 'camara') {
            $nombre = HistoricoElectoralStats::normalizar((string) DB::table('departamentos')->where('id', $departamentoId)->value('nombre'));

            return ['anio' => null, 'departamento' => self::CURULES_CAMARA[$nombre] ?? null, 'municipios' => []];
        }

        return $this->recordar('curules', [$departamentoId, $cargo], function () use ($departamentoId, $cargo) {
            $anio = DB::table('historico_electoral')->where('departamento_id', $departamentoId)
                ->where('cargo', $cargo)->where('elegido', true)->max('anio');
            if (! $anio) {
                return ['anio' => null, 'departamento' => null, 'municipios' => []];
            }

            $elegidos = DB::table('historico_electoral')->where('departamento_id', $departamentoId)
                ->where('cargo', $cargo)->where('anio', $anio)->where('elegido', true)
                ->whereNotNull('candidato')->where('candidato', '!=', '')
                ->distinct()->get(['municipio_id', 'partido', 'candidato']);

            return [
                'anio' => (int) $anio,
                'departamento' => $elegidos->unique(fn ($e) => $e->partido.'|'.$e->candidato)->count() ?: null,
                'municipios' => $elegidos->groupBy('municipio_id')->map->count()->all(),
            ];
        });
    }

    /**
     * Votos para ganar en una circunscripción (el departamento o un municipio).
     *
     * - Uninominal: los del ganador de la última elección.
     * - Corporación: la cifra repartidora (lo que costó la última curul) con
     *   umbral del 50 % del cuociente (30 % si hay 2 curules o menos).
     *
     * Todo se proyecta con el crecimiento de los votos entre las dos últimas elecciones.
     *
     * @param  array<string, int>  $votos  clave del partido => votos (incluye VOTO EN BLANCO)
     */
    public static function metaCircunscripcion(array $votos, ?int $totalPrevio, bool $corporacion, ?int $curules): array
    {
        $total = array_sum($votos);
        $listas = array_filter($votos, fn ($v, $k) => $k !== 'VOTO EN BLANCO' && $v > 0, ARRAY_FILTER_USE_BOTH);
        arsort($listas);
        // Crecimiento acotado: una elección rara no debe disparar la meta
        $crecimiento = $totalPrevio ? max(0.8, min(1.5, $total / $totalPrevio)) : 1.0;
        $claves = array_keys($listas);

        $r = [
            'total' => $total,
            'crecimiento' => round(($crecimiento - 1) * 100, 1),
            'proyectado' => (int) round($total * $crecimiento),
            'ganador' => $claves[0] ?? null,
            'ganador_votos' => $listas[$claves[0] ?? ''] ?? 0,
            'segundo' => $claves[1] ?? null,
            'segundo_votos' => $listas[$claves[1] ?? ''] ?? 0,
            'curules' => $curules,
            'umbral' => null,
            'cifra' => null,
            'con_curul' => null,
            'meta' => null,
        ];

        if (! $corporacion) {
            $r['meta'] = $r['ganador_votos'] ? (int) round($r['ganador_votos'] * $crecimiento) : null;

            return $r;
        }

        if (! $curules || ! $total) {
            return $r;
        }

        $umbral = $total / $curules * ($curules > 2 ? 0.5 : 0.3);
        $cocientes = [];
        foreach ($listas as $clave => $v) {
            if ($v < $umbral) {
                continue;
            }
            for ($k = 1; $k <= $curules; $k++) {
                $cocientes[] = [$v / $k, $clave];
            }
        }
        if (! $cocientes) {
            return $r;
        }

        usort($cocientes, fn ($a, $b) => $b[0] <=> $a[0]);
        $ganadores = array_slice($cocientes, 0, $curules);
        $cifra = end($ganadores)[0];

        $r['umbral'] = (int) ceil($umbral * $crecimiento);
        $r['cifra'] = (int) floor($cifra);
        $r['con_curul'] = count(array_unique(array_column($ganadores, 1)));
        // Para una curul la lista necesita la cifra repartidora y pasar el umbral
        $r['meta'] = (int) round(max($cifra, $umbral) * $crecimiento);

        return $r;
    }

    /**
     * Meta del cargo y su reparto por municipio, con el avance de los registros.
     *
     * Departamental: una meta para todo el departamento, repartida según los
     * votos que puso cada municipio en la última elección. Municipal: cada
     * municipio tiene su propia meta (su propia elección).
     *
     * @param  array<int, int>|null  $registros  municipio_id => registros del candidato (null = sin candidato)
     */
    public function meta(int $departamentoId, string $cargo, ?array $registros = null, ?int $metaManual = null, ?int $curulesManual = null, ?int $anio = null): array
    {
        if (! in_array($cargo, self::CON_META, true)) {
            return ['aplica' => false];
        }

        $d = $this->analisis->datosHasta($departamentoId, $cargo, $anio);
        $anios = $d['anios'];
        $ultimo = end($anios) ?: null;
        $previo = count($anios) > 1 ? $anios[count($anios) - 2] : null;
        if (! $ultimo) {
            return ['aplica' => false];
        }

        $corporacion = self::esCorporacion($cargo);
        $municipal = self::esMunicipal($cargo);
        $curules = $corporacion ? $this->curules($departamentoId, $cargo) : null;
        $votosDe = fn (array $m, int $a) => array_filter(array_map(fn ($p) => $p[$a] ?? 0, $m['partidos']));

        $filas = [];
        foreach ($d['municipios'] as $m) {
            $total = $m['total'][$ultimo] ?? 0;
            $fila = [
                'id' => $m['id'],
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $m['potencial'],
                'votos' => $total,
                'registros' => $registros !== null ? ($registros[$m['id']] ?? 0) : null,
            ];

            if ($municipal) {
                $fila['eleccion'] = self::metaCircunscripcion(
                    $votosDe($m, $ultimo),
                    $previo ? ($m['total'][$previo] ?? null) : null,
                    $corporacion,
                    $curules['municipios'][$m['id']] ?? null,
                );
                $fila['meta'] = $fila['eleccion']['meta'];
            }
            $filas[$m['id']] = $fila;
        }

        $eleccion = null;
        $meta = null;
        if (! $municipal) {
            $votos = [];
            foreach ($d['municipios'] as $m) {
                foreach ($votosDe($m, $ultimo) as $clave => $v) {
                    $votos[$clave] = ($votos[$clave] ?? 0) + $v;
                }
            }
            $eleccion = self::metaCircunscripcion($votos, $previo ? ($d['totales'][$previo] ?? null) : null,
                $corporacion, $curulesManual ?: ($curules['departamento'] ?? null));
            $meta = $metaManual ?: $eleccion['meta'];

            $totalDepto = array_sum(array_column($filas, 'votos'));
            foreach ($filas as &$f) {
                $f['meta'] = $meta && $totalDepto ? (int) round($meta * $f['votos'] / $totalDepto) : null;
            }
            unset($f);
        }

        foreach ($filas as &$f) {
            $f['avance'] = $f['registros'] !== null && $f['meta'] ? round($f['registros'] * 100 / $f['meta'], 1) : null;
            $f['falta'] = $f['meta'] !== null ? max(0, $f['meta'] - ($f['registros'] ?? 0)) : null;
        }
        unset($f);

        $filas = array_values($filas);
        usort($filas, fn ($a, $b) => ($b['meta'] ?? 0) <=> ($a['meta'] ?? 0));
        $nombre = fn ($clave) => $clave ? ($d['partidos'][$clave]['nombre'] ?? $clave) : null;

        $registrosTotal = $registros !== null ? array_sum(array_column($filas, 'registros')) : null;

        return [
            'aplica' => true,
            'municipal' => $municipal,
            'corporacion' => $corporacion,
            'ultimo' => $ultimo,
            'previo' => $previo,
            'eleccion' => $eleccion ? $eleccion + ['ganador_nombre' => $nombre($eleccion['ganador']), 'segundo_nombre' => $nombre($eleccion['segundo'])] : null,
            'curules' => $curules,
            'curules_manual' => (bool) $curulesManual,
            'meta' => $meta,
            'meta_manual' => (bool) $metaManual,
            'registros' => $registrosTotal,
            'avance' => $registrosTotal !== null && $meta ? round($registrosTotal * 100 / $meta, 1) : null,
            'municipios' => array_map(function ($f) use ($nombre) {
                if (isset($f['eleccion'])) {
                    $f['eleccion']['ganador_nombre'] = $nombre($f['eleccion']['ganador']);
                }

                return $f;
            }, $filas),
        ];
    }

    // ===================== Votos por conquistar =====================

    /**
     * Gente habilitada que no votó en la última elección del cargo, voto en
     * blanco, y quienes votaron en la última presidencial pero no en esta.
     *
     * Aproximado: el potencial es el más reciente cargado y el histórico no
     * trae nulos ni no marcados, así que "no votó" queda un poco alto.
     */
    public function conquistar(int $departamentoId, string $cargo, ?int $anio = null): array
    {
        $d = $this->analisis->datosHasta($departamentoId, $cargo, $anio);
        $anios = $d['anios'];
        $ultimo = end($anios) ?: null;
        $previo = count($anios) > 1 ? $anios[count($anios) - 2] : null;

        $presidencial = null;
        if ($cargo !== 'presidencia' && in_array('presidencia', $this->analisis->cargos($departamentoId), true)) {
            $p = $this->analisis->datos($departamentoId, 'presidencia');
            // La presidencial más reciente que no sea posterior a la elección elegida
            $anioPres = collect($p['anios'])->filter(fn ($a) => ! $ultimo || $a <= $ultimo)->last();
            $presidencial = ['anio' => $anioPres, 'municipios' => $p['municipios']];
        }

        $filas = [];
        foreach ($d['municipios'] as $m) {
            $votaron = $m['total'][$ultimo] ?? 0;
            $antes = $previo ? ($m['total'][$previo] ?? 0) : 0;
            $blanco = $m['partidos']['VOTO EN BLANCO'][$ultimo] ?? 0;
            $potencial = $m['potencial'];
            $noVotaron = $potencial !== null ? max(0, $potencial - $votaron) : null;
            $pres = $presidencial ? ($presidencial['municipios'][$m['id']]['total'][$presidencial['anio']] ?? null) : null;

            $filas[] = [
                'id' => $m['id'],
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $potencial,
                'votaron' => $votaron,
                'participacion' => $potencial ? round($votaron * 100 / $potencial, 1) : null,
                'crecio' => $antes ? round(($votaron - $antes) * 100 / $antes, 1) : null,
                'no_votaron' => $noVotaron,
                'blanco' => $blanco,
                'blanco_pct' => $votaron ? round($blanco * 100 / $votaron, 1) : null,
                'presidencial' => $pres,
                // Votaron por presidente pero no en esta elección: voto de opinión que se puede despertar
                'solo_presidencial' => $pres !== null ? max(0, $pres - $votaron) : null,
                'conquistables' => $noVotaron !== null ? $noVotaron + $blanco : null,
            ];
        }
        usort($filas, fn ($a, $b) => ($b['conquistables'] ?? $b['blanco']) <=> ($a['conquistables'] ?? $a['blanco']));

        $suma = fn ($k) => array_sum(array_map(fn ($f) => $f[$k] ?? 0, $filas));
        $potencialTotal = $suma('potencial');

        return [
            'ultimo' => $ultimo,
            'previo' => $previo,
            'anio_presidencial' => $presidencial['anio'] ?? null,
            'hay_potencial' => $potencialTotal > 0,
            'totales' => [
                'potencial' => $potencialTotal,
                'votaron' => $suma('votaron'),
                'participacion' => $potencialTotal ? round($suma('votaron') * 100 / $potencialTotal, 1) : null,
                'no_votaron' => $potencialTotal ? $suma('no_votaron') : null,
                'blanco' => $suma('blanco'),
                'blanco_pct' => $suma('votaron') ? round($suma('blanco') * 100 / $suma('votaron'), 1) : null,
                'solo_presidencial' => $presidencial ? $suma('solo_presidencial') : null,
                'conquistables' => $potencialTotal ? $suma('conquistables') : null,
            ],
            'municipios' => $filas,
        ];
    }

    // ===================== Municipios en disputa =====================

    /**
     * Qué tan disputado es cada municipio en el cargo: margen entre los dos
     * primeros, si cambió de ganador (o de bloque) y cuánto se movieron los votos.
     *
     * Puntaje 0–100 = 60 % margen (0 pts → 1, 30 pts o más → 0) + 25 % cambio
     * de ganador en la última elección + 15 % volatilidad entre bloques.
     * Si no se sabe el bloque, cambiar de partido ganador cuenta un cuarto.
     */
    public function disputa(int $departamentoId, string $cargo, ?array $ideologias = null, ?int $anio = null): array
    {
        $d = $this->analisis->datosHasta($departamentoId, $cargo, $anio);
        $t = $this->analisis->tendencia($departamentoId, $cargo, $ideologias, $anio);
        $anios = $d['anios'];
        $ultimo = $t['ultimo'];
        $previo = $t['previo'];
        $tendencia = collect($t['municipios'])->keyBy('id');

        $filas = [];
        foreach ($d['municipios'] as $m) {
            $porAnio = [];
            foreach ($anios as $a) {
                $total = $m['total'][$a] ?? 0;
                $votos = [];
                foreach ($m['partidos'] as $clave => $x) {
                    if ($clave !== 'VOTO EN BLANCO' && ($x[$a] ?? 0) > 0) {
                        $votos[$clave] = $x[$a];
                    }
                }
                arsort($votos);
                $claves = array_keys($votos);
                $pct = fn ($i) => isset($claves[$i]) && $total ? $votos[$claves[$i]] * 100 / $total : 0;
                $porAnio[$a] = [
                    'hubo' => $total > 0,
                    'ganador' => $claves[0] ?? null,
                    'segundo' => $claves[1] ?? null,
                    'margen' => $total ? round($pct(0) - $pct(1), 1) : null,
                    'bloque' => $tendencia[$m['id']]['por_anio'][$a]['bloque'] ?? null,
                ];
            }

            $u = $ultimo ? $porAnio[$ultimo] : null;
            $p = $previo ? $porAnio[$previo] : null;
            if (! $u || ! $u['hubo']) {
                continue;
            }

            // Cambio de ganador: por bloque si los dos están clasificados; si no, por partido (vale la mitad,
            // porque las coaliciones cambian de nombre en cada elección)
            $cambio = 0.0;
            if ($p && $p['hubo']) {
                if ($u['bloque'] && $p['bloque']) {
                    $cambio = $u['bloque'] !== $p['bloque'] ? 1.0 : 0.0;
                } elseif ($u['ganador'] !== $p['ganador']) {
                    $cambio = 0.25;
                }
            }

            // Volatilidad (índice de Pedersen) entre bloques: cuántos puntos pasaron de izquierda, centro
            // o derecha a otro bloque. Por partido no sirve: las coaliciones cambian de nombre cada vez.
            $volatilidad = null;
            $bu = $tendencia[$m['id']]['por_anio'][$ultimo]['porcentaje'] ?? null;
            $bp = $previo ? ($tendencia[$m['id']]['por_anio'][$previo]['porcentaje'] ?? null) : null;
            if ($p && $p['hubo'] && $bu && $bp) {
                $volatilidad = round(array_sum(array_map(fn ($k) => abs(($bu[$k] ?? 0) - ($bp[$k] ?? 0)), array_keys($bu))) / 2, 1);
            }

            // Ganadores distintos (por partido) en todas las elecciones
            $ganadores = collect($porAnio)->where('hubo', true)->pluck('ganador')->filter()->unique()->count();

            $puntaje = (int) round(100 * (
                0.60 * max(0, 1 - $u['margen'] / 30)
                + 0.25 * $cambio
                + 0.15 * min(1, ($volatilidad ?? 0) / 50)
            ));

            $mismoSiempre = $ganadores === 1 && collect($porAnio)->where('hubo', true)->count() > 1;
            $estado = match (true) {
                $puntaje >= 55 || $u['margen'] < 5 => 'disputado',
                // Fortín: ganó holgado, no cambió de bloque y la vez anterior tampoco estuvo reñido
                $u['margen'] >= 20 && $cambio < 1 && ($p['margen'] ?? 99) >= 10 => 'fortin',
                default => 'inclinado',
            };

            $filas[] = [
                'id' => $m['id'],
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $m['potencial'],
                'ganador' => $u['ganador'],
                'ganador_nombre' => $d['partidos'][$u['ganador']]['nombre'] ?? $u['ganador'],
                'bloque' => $u['bloque'],
                'segundo_nombre' => $u['segundo'] ? ($d['partidos'][$u['segundo']]['nombre'] ?? $u['segundo']) : null,
                'margen' => $u['margen'],
                'margen_previo' => $p['margen'] ?? null,
                'cambio' => $cambio,
                'bloque_previo' => $p['bloque'] ?? null,
                'ganador_previo' => $p && $p['ganador'] ? ($d['partidos'][$p['ganador']]['nombre'] ?? $p['ganador']) : null,
                'volatilidad' => $volatilidad,
                'ganadores' => $ganadores,
                'mismo_siempre' => $mismoSiempre,
                'puntaje' => $puntaje,
                'estado' => $estado,
            ];
        }
        usort($filas, fn ($a, $b) => [$b['puntaje'], $b['potencial'] ?? 0] <=> [$a['puntaje'], $a['potencial'] ?? 0]);

        $conteo = ['disputado' => 0, 'inclinado' => 0, 'fortin' => 0];
        foreach ($filas as $f) {
            $conteo[$f['estado']]++;
        }

        return [
            'ultimo' => $ultimo,
            'previo' => $previo,
            'conteo' => $conteo,
            'municipios' => $filas,
        ];
    }

    // ===================== Prioridades =====================

    /**
     * Orden de "dónde ir primero": cada criterio se lleva a 0–1 entre los
     * municipios del departamento y se pondera según el enfoque.
     *
     * @param  string|null  $bloque  izquierda, centro o derecha del candidato (null = sin afinidad)
     */
    public function prioridades(int $departamentoId, string $cargo, ?string $bloque, string $enfoque = 'equilibrado',
        ?array $registros = null, ?int $metaManual = null, ?int $curulesManual = null, ?array $ideologias = null, ?int $anio = null): array
    {
        $ideologias ??= PartidoIdeologia::mapa();
        $pesos = (self::ENFOQUES[$enfoque] ?? self::ENFOQUES['equilibrado'])[1];

        $meta = $this->meta($departamentoId, $cargo, $registros, $metaManual, $curulesManual, $anio);
        $conquistar = $this->conquistar($departamentoId, $cargo, $anio);
        $disputa = $this->disputa($departamentoId, $cargo, $ideologias, $anio);
        $tendencia = $this->analisis->tendencia($departamentoId, $cargo, $ideologias, $anio);

        $metaPor = collect($meta['municipios'] ?? [])->keyBy('id');
        $conqPor = collect($conquistar['municipios'])->keyBy('id');
        $dispPor = collect($disputa['municipios'])->keyBy('id');
        $tendPor = collect($tendencia['municipios'])->keyBy('id');
        $ultimo = $tendencia['ultimo'];
        $deptoBloque = $bloque && $ultimo ? ($tendencia['departamento'][$ultimo]['porcentaje'][$bloque] ?? null) : null;

        $hayMeta = $metaPor->filter(fn ($m) => $m['meta'] !== null)->isNotEmpty();
        $hayAfinidad = $bloque !== null && $deptoBloque !== null;

        // Lo que aplica en este caso: un criterio sin datos no pesa
        $disponibles = array_filter([
            'tamano' => true,
            'falta' => $hayMeta,
            'disputa' => $dispPor->isNotEmpty(),
            'afinidad' => $hayAfinidad,
            'conquistar' => (bool) $conquistar['hay_potencial'],
        ]);
        $pesos = array_intersect_key($pesos, $disponibles);
        $sumaPesos = array_sum($pesos) ?: 1;

        $maxTamano = max(1, (int) $conqPor->max(fn ($c) => $c['potencial'] ?? $c['votaron']));
        $maxFalta = max(1, (int) $metaPor->max('falta'));
        $maxConq = max(1, (int) $conqPor->max('conquistables'));

        $filas = [];
        foreach ($conqPor as $id => $c) {
            $diferencia = null;
            if ($hayAfinidad) {
                $pct = $tendPor[$id]['por_anio'][$ultimo]['porcentaje'][$bloque] ?? null;
                $diferencia = $pct !== null ? round($pct - $deptoBloque, 1) : null;
            }

            $criterios = [
                'tamano' => sqrt(($c['potencial'] ?? $c['votaron']) / $maxTamano),
                'falta' => ($metaPor[$id]['falta'] ?? 0) / $maxFalta,
                'disputa' => ($dispPor[$id]['puntaje'] ?? 0) / 100,
                // −20 pts frente al departamento → 0; +20 pts → 1
                'afinidad' => $diferencia !== null ? max(0, min(1, ($diferencia + 20) / 40)) : 0.5,
                'conquistar' => ($c['conquistables'] ?? 0) / $maxConq,
            ];

            $puntaje = 0;
            $aportes = [];
            foreach ($pesos as $k => $w) {
                $aportes[$k] = $w * $criterios[$k];
                $puntaje += $aportes[$k];
            }
            arsort($aportes);

            $filas[] = [
                'id' => $id,
                'municipio' => $c['municipio'],
                'codigo_dane' => $c['codigo_dane'],
                'potencial' => $c['potencial'],
                'puntaje' => (int) round($puntaje * 100 / $sumaPesos),
                'criterios' => array_map(fn ($v) => round($v, 2), array_intersect_key($criterios, $pesos)),
                'razones' => array_slice(array_keys(array_filter($aportes)), 0, 2),
                'accion' => self::accion($criterios, $hayAfinidad),
                'afinidad' => $diferencia,
                'meta' => $metaPor[$id]['meta'] ?? null,
                'registros' => $metaPor[$id]['registros'] ?? null,
                'falta' => $metaPor[$id]['falta'] ?? null,
                'estado' => $dispPor[$id]['estado'] ?? null,
                'margen' => $dispPor[$id]['margen'] ?? null,
                'conquistables' => $c['conquistables'],
            ];
        }
        usort($filas, fn ($a, $b) => [$b['puntaje'], $b['potencial'] ?? 0] <=> [$a['puntaje'], $a['potencial'] ?? 0]);
        foreach ($filas as $i => &$f) {
            $f['puesto'] = $i + 1;
        }
        unset($f);

        return [
            'enfoque' => $enfoque,
            'pesos' => $pesos,
            'bloque' => $hayAfinidad ? $bloque : null,
            'bloque_depto' => $deptoBloque,
            'ultimo' => $ultimo,
            'con_registros' => $registros !== null,
            'municipios' => $filas,
            'acciones' => collect($filas)->countBy('accion')->all(),
        ];
    }

    /** Qué hacer en el municipio según lo que más pesa. */
    public static function accion(array $c, bool $hayAfinidad): string
    {
        return match (true) {
            $c['disputa'] >= 0.6 => 'persuadir',
            $hayAfinidad && $c['afinidad'] >= 0.6 => 'movilizar',
            $c['conquistar'] >= 0.6 => 'captar',
            $hayAfinidad && $c['afinidad'] <= 0.3 => 'dificil',
            default => 'consolidar',
        };
    }

    public const ESTADOS = [
        'disputado' => 'Disputado',
        'inclinado' => 'Inclinado',
        'fortin' => 'Fortín',
    ];

    /** Acción sugerida: [nombre, qué hacer, color]. */
    public const ACCIONES = [
        'persuadir' => ['Persuadir', 'Municipio disputado: convencer indecisos y votantes del rival.', '#ea580c'],
        'movilizar' => ['Movilizar', 'Zona afín: asegurar que los simpatizantes se registren y voten.', '#16a34a'],
        'captar' => ['Captar nuevos', 'Mucha gente que no vota o vota en blanco.', '#7c3aed'],
        'consolidar' => ['Consolidar', 'Sin un rasgo fuerte: presencia constante y registros.', '#2563eb'],
        'dificil' => ['Terreno difícil', 'Zona del bloque contrario: presencia mínima, sin gastar de más.', '#94a3b8'],
    ];
}
