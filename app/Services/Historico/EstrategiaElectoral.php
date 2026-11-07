<?php

namespace App\Services\Historico;

use App\Support\Partidos;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lecturas de estrategia sobre el histórico:
 *
 * - alianza(): simulador: qué pasa si varios partidos van juntos.
 * - maquinaria(): voto de opinión (Senado) frente a estructura local (concejo, asamblea).
 * - lideres(): candidatos con más votos y si sus votos están concentrados o repartidos.
 * - testigos(): municipios con resultados atípicos, para priorizar testigos electorales.
 *
 * Todo por municipio y con datos públicos de la Registraduría.
 */
class EstrategiaElectoral
{
    /** Cargo de opinión y cargos de estructura local, para maquinaria(). */
    public const OPINION = 'senado';

    public const ESTRUCTURA = ['concejo', 'asamblea'];

    public function __construct(
        private AnalisisElectoral $analisis,
        private PlanCampania $plan,
    ) {}

    private function recordar(string $metodo, array $args, \Closure $calcular): mixed
    {
        $version = (int) Cache::get(HistoricoElectoralStats::CACHE_VERSION, 1);

        return Cache::rememberForever("estrategia:{$version}.1:{$metodo}:".md5(serialize($args)), $calcular);
    }

    /**
     * Reparto de curules por cifra repartidora (D'Hondt) con umbral del 50 %
     * del cuociente (30 % con 2 curules o menos).
     *
     * @param  array<string, int|float>  $listas  lista => votos (sin el blanco)
     * @return array<string, int> lista => curules (solo las que sacaron)
     */
    public static function reparto(array $listas, int $total, int $curules): array
    {
        if ($curules < 1 || $total < 1) {
            return [];
        }

        $umbral = $total / $curules * ($curules > 2 ? 0.5 : 0.3);
        $cocientes = [];
        foreach ($listas as $lista => $v) {
            if ($v < $umbral || $v <= 0) {
                continue;
            }
            for ($k = 1; $k <= $curules; $k++) {
                $cocientes[] = [$v / $k, (string) $lista];
            }
        }
        usort($cocientes, fn ($a, $b) => $b[0] <=> $a[0]);

        $curulesPor = [];
        foreach (array_slice($cocientes, 0, $curules) as [, $lista]) {
            $curulesPor[$lista] = ($curulesPor[$lista] ?? 0) + 1;
        }

        return $curulesPor;
    }

    // ===================== Simulador de alianzas =====================

    /**
     * Si los partidos elegidos hubieran ido juntos en la elección del año:
     * dónde ganaría la alianza, cuánto le falta en el resto y, en
     * corporaciones, cuántas curules sacarían juntos frente a separados.
     *
     * El primer partido aporta todos sus votos; los demás, el porcentaje de
     * transferencia (en una alianza no todos los votos se pasan).
     *
     * @param  string[]  $claves  claves de Partidos::clave(); la primera es el partido principal
     */
    public function alianza(int $departamentoId, string $cargo, int $anio, array $claves, int $transferencia = 100, ?int $curulesManual = null): array
    {
        $d = $this->analisis->datos($departamentoId, $cargo);
        $claves = array_values(array_filter(array_unique($claves),
            fn ($c) => $c !== 'VOTO EN BLANCO' && ($d['partidos'][$c]['por_anio'][$anio] ?? 0) > 0));
        $factor = max(0, min(100, $transferencia)) / 100;
        $peso = fn ($clave) => $clave === ($claves[0] ?? null) ? 1.0 : $factor;

        $corporacion = PlanCampania::esCorporacion($cargo);
        $municipal = PlanCampania::esMunicipal($cargo);
        $curules = $corporacion ? $this->plan->curules($departamentoId, $cargo) : null;

        $filas = [];
        $depto = [];
        $deptoTotal = 0;
        $curulesSeparados = 0;
        $curulesJuntos = 0;

        foreach ($d['municipios'] as $m) {
            $total = $m['total'][$anio] ?? 0;
            if ($total <= 0) {
                continue;
            }

            $listas = [];
            foreach ($m['partidos'] as $clave => $x) {
                if ($clave !== 'VOTO EN BLANCO' && ($x[$anio] ?? 0) > 0) {
                    $listas[$clave] = $x[$anio];
                    $depto[$clave] = ($depto[$clave] ?? 0) + $x[$anio];
                }
            }
            $deptoTotal += $total;

            $fila = $this->comparar($listas, $claves, $peso, $total);
            $fila += [
                'id' => $m['id'],
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $m['potencial'],
                'rival_nombre' => $fila['rival'] ? ($d['partidos'][$fila['rival']]['nombre'] ?? $fila['rival']) : null,
            ];

            // Concejo: cada municipio reparte sus curules
            if ($corporacion && $municipal && ($n = $curules['municipios'][$m['id']] ?? null)) {
                $fila['curules_separados'] = array_sum(array_intersect_key(self::reparto($listas, $total, $n), array_flip($claves)));
                $fila['curules_juntos'] = self::reparto($this->unir($listas, $claves, $peso), $total, $n)['ALIANZA'] ?? 0;
                $curulesSeparados += $fila['curules_separados'];
                $curulesJuntos += $fila['curules_juntos'];
            }
            $filas[] = $fila;
        }

        $resumen = $this->comparar($depto, $claves, $peso, $deptoTotal);
        $resumen['rival_nombre'] = $resumen['rival'] ? ($d['partidos'][$resumen['rival']]['nombre'] ?? $resumen['rival']) : null;

        // Asamblea y Cámara: curules del departamento
        $nDepto = $corporacion && ! $municipal ? ($curulesManual ?: ($curules['departamento'] ?? null)) : null;
        if ($nDepto) {
            $curulesSeparados = array_sum(array_intersect_key(self::reparto($depto, $deptoTotal, $nDepto), array_flip($claves)));
            $curulesJuntos = self::reparto($this->unir($depto, $claves, $peso), $deptoTotal, $nDepto)['ALIANZA'] ?? 0;
        }

        usort($filas, fn ($a, $b) => [$b['gana'], $b['margen']] <=> [$a['gana'], $a['margen']]);

        return [
            'anio' => $anio,
            'corporacion' => $corporacion,
            'municipal' => $municipal,
            'transferencia' => (int) round($factor * 100),
            'partidos' => array_map(fn ($c) => [
                'clave' => $c,
                'nombre' => $d['partidos'][$c]['nombre'] ?? $c,
                'votos' => $depto[$c] ?? 0,
                'porcentaje' => $deptoTotal ? round(($depto[$c] ?? 0) * 100 / $deptoTotal, 1) : null,
            ], $claves),
            'resumen' => $resumen,
            'curules' => $corporacion ? [
                'total' => $nDepto ?? ($curules['municipios'] ? array_sum(array_intersect_key($curules['municipios'], array_flip(array_column($filas, 'id')))) : null),
                'separados' => $curulesSeparados,
                'juntos' => $curulesJuntos,
                'anio' => $curules['anio'],
            ] : null,
            'ganados_antes' => collect($filas)->where('ganaba', true)->count(),
            'ganados_juntos' => collect($filas)->where('gana', true)->count(),
            'municipios' => $filas,
        ];
    }

    /** Votos de la alianza frente al mejor rival en una circunscripción. */
    private function comparar(array $listas, array $claves, \Closure $peso, int $total): array
    {
        $alianza = 0.0;
        foreach ($claves as $c) {
            $alianza += ($listas[$c] ?? 0) * $peso($c);
        }
        $rivales = array_diff_key($listas, array_flip($claves));
        arsort($rivales);
        $rival = array_key_first($rivales);
        $votosRival = $rival ? $rivales[$rival] : 0;
        arsort($listas);
        $alianza = (int) round($alianza);

        return [
            'votos' => $alianza,
            'porcentaje' => $total ? round($alianza * 100 / $total, 1) : null,
            'rival' => $rival,
            'rival_votos' => $votosRival,
            'gana' => $alianza > 0 && $alianza > $votosRival,
            // Antes: alguno de los partidos ganó solo
            'ganaba' => in_array(array_key_first($listas), $claves, true),
            'margen' => $total ? round(($alianza - $votosRival) * 100 / $total, 1) : 0.0,
            'falta' => max(0, $votosRival - $alianza + 1),
        ];
    }

    /** Listas con los partidos de la alianza sumados en una sola ("ALIANZA"). */
    private function unir(array $listas, array $claves, \Closure $peso): array
    {
        $unidas = array_diff_key($listas, array_flip($claves));
        $unidas['ALIANZA'] = array_sum(array_map(fn ($c) => ($listas[$c] ?? 0) * $peso($c), $claves));

        return $unidas;
    }

    // ===================== Opinión vs. maquinaria =====================

    /**
     * Porcentaje de un partido en el Senado (voto de opinión: se vota por el
     * partido en todo el país) frente a concejo y asamblea (estructura local:
     * candidatos y líderes del municipio), en la última elección de cada uno.
     *
     * Estructura > opinión = el partido vive de sus líderes locales.
     * Opinión > estructura = tiene simpatizantes, pero le faltan candidatos fuertes.
     */
    public function maquinaria(int $departamentoId, string $clave): array
    {
        $cargos = array_values(array_intersect([self::OPINION, ...self::ESTRUCTURA], $this->analisis->cargos($departamentoId)));
        $por = [];
        $anios = [];
        $nombre = $clave;
        foreach ($cargos as $cargo) {
            $d = $this->analisis->datos($departamentoId, $cargo);
            $anio = end($d['anios']);
            $anios[$cargo] = $anio;
            $nombre = $d['partidos'][$clave]['nombre'] ?? $nombre;
            $por[$cargo] = ['datos' => $d, 'anio' => $anio];
        }

        $pct = function (string $cargo, ?int $municipioId) use ($por, $clave) {
            $x = $por[$cargo] ?? null;
            if (! $x) {
                return null;
            }
            $a = $x['anio'];
            if ($municipioId === null) {
                $total = $x['datos']['totales'][$a] ?? 0;
                $v = $x['datos']['partidos'][$clave]['por_anio'][$a] ?? 0;
            } else {
                $m = $x['datos']['municipios'][$municipioId] ?? null;
                $total = $m['total'][$a] ?? 0;
                $v = $m['partidos'][$clave][$a] ?? 0;
            }

            return $total ? round($v * 100 / $total, 1) : null;
        };

        $local = fn (?int $mid) => collect(self::ESTRUCTURA)->map(fn ($c) => $pct($c, $mid))->filter(fn ($v) => $v !== null)->avg();

        $filas = [];
        $base = $por[self::OPINION]['datos']['municipios'] ?? ($por[self::ESTRUCTURA[0]]['datos']['municipios'] ?? []);
        foreach ($base as $mid => $m) {
            $opinion = $pct(self::OPINION, $mid);
            $estructura = $local($mid);
            $diferencia = $opinion !== null && $estructura !== null ? round($estructura - $opinion, 1) : null;
            $filas[] = [
                'id' => $mid,
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $m['potencial'],
                'opinion' => $opinion,
                'por_cargo' => collect(self::ESTRUCTURA)->mapWithKeys(fn ($c) => [$c => $pct($c, $mid)])->all(),
                'estructura' => $estructura !== null ? round($estructura, 1) : null,
                'diferencia' => $diferencia,
                'tipo' => self::tipo($opinion, $estructura),
            ];
        }
        usort($filas, fn ($a, $b) => ($b['diferencia'] ?? -INF) <=> ($a['diferencia'] ?? -INF));

        $opinionDepto = $pct(self::OPINION, null);
        $estructuraDepto = $local(null);

        return [
            'clave' => $clave,
            'nombre' => $nombre,
            'anios' => $anios,
            'departamento' => [
                'opinion' => $opinionDepto,
                'por_cargo' => collect(self::ESTRUCTURA)->mapWithKeys(fn ($c) => [$c => $pct($c, null)])->all(),
                'estructura' => $estructuraDepto !== null ? round($estructuraDepto, 1) : null,
                'tipo' => self::tipo($opinionDepto, $estructuraDepto),
            ],
            'conteo' => collect($filas)->countBy('tipo')->all(),
            'municipios' => $filas,
        ];
    }

    /** maquinaria, opinion, equilibrado o ausente. */
    public static function tipo(?float $opinion, ?float $estructura): string
    {
        if (! $opinion && ! $estructura) {
            return 'ausente';
        }
        $o = (float) $opinion;
        $e = (float) $estructura;
        // Más del doble, o 5 puntos de diferencia, marca el carácter
        if ($e >= max($o * 1.5, $o + 5)) {
            return 'maquinaria';
        }
        if ($o >= max($e * 1.5, $e + 5)) {
            return 'opinion';
        }

        return 'equilibrado';
    }

    public const TIPOS = [
        'maquinaria' => ['Estructura local', 'Saca más con sus candidatos locales que con la marca: depende de sus líderes.', '#b45309'],
        'opinion' => ['Voto de opinión', 'La marca saca más que sus candidatos locales: hay simpatizantes sin líder.', '#0891b2'],
        'equilibrado' => ['Equilibrado', 'Marca y estructura local van parejas.', '#64748b'],
        'ausente' => ['Sin votos', 'El partido no tiene votos en este municipio.', '#e2e8f0'],
    ];

    /** Partidos con votos en el Senado o en la estructura local (sin coaliciones). */
    public function partidosMaquinaria(int $departamentoId): array
    {
        $cargos = array_intersect([self::OPINION, ...self::ESTRUCTURA], $this->analisis->cargos($departamentoId));
        $partidos = [];
        foreach ($cargos as $cargo) {
            foreach ($this->analisis->datos($departamentoId, $cargo)['partidos'] as $clave => $p) {
                if ($clave === 'VOTO EN BLANCO' || Partidos::esCoalicion($p['nombre'])) {
                    continue;
                }
                $partidos[$clave] ??= ['nombre' => $p['nombre'], 'votos' => 0, 'cargos' => []];
                $partidos[$clave]['votos'] += $p['votos'];
                $partidos[$clave]['cargos'][$cargo] = true;
            }
        }
        // Solo los que están en el Senado y en algo local: si no, no hay con qué comparar
        $partidos = array_filter($partidos, fn ($p) => isset($p['cargos'][self::OPINION]) && count($p['cargos']) > 1);
        uasort($partidos, fn ($a, $b) => $b['votos'] <=> $a['votos']);

        return $partidos;
    }

    // ===================== Líderes locales =====================

    /**
     * Candidatos (voto preferente) de la última elección de un cargo:
     * cuántos votos, en cuántos municipios y qué tan concentrados.
     *
     * Concentración = % de sus votos en su municipio más fuerte. Municipios
     * para el 80 % = cuántos municipios suman el 80 % de sus votos.
     */
    public function lideres(int $departamentoId, string $cargo, ?string $clave = null, int $limite = 40): array
    {
        $todos = $this->recordar('lideres', [$departamentoId, $cargo], function () use ($departamentoId, $cargo) {
            $anio = DB::table('historico_electoral')->where('departamento_id', $departamentoId)->where('cargo', $cargo)->max('anio');
            if (! $anio) {
                return ['anio' => null, 'candidatos' => []];
            }

            $filas = DB::table('historico_electoral as h')
                ->join('municipios as m', 'm.id', '=', 'h.municipio_id')
                ->where('h.departamento_id', $departamentoId)->where('h.cargo', $cargo)
                ->where('h.anio', $anio)->where('h.vuelta', 1)
                ->whereNotNull('h.candidato')->where('h.candidato', '!=', '')
                ->groupBy('h.partido', 'h.candidato', 'h.municipio_id', 'm.nombre')
                ->selectRaw('h.partido, h.candidato, h.municipio_id, m.nombre, SUM(h.votos) as votos')
                ->get();

            $totalMunicipio = DB::table('historico_electoral')->where('departamento_id', $departamentoId)
                ->where('cargo', $cargo)->where('anio', $anio)->where('vuelta', 1)
                ->groupBy('municipio_id')->selectRaw('municipio_id, SUM(votos) as v')->pluck('v', 'municipio_id');

            $candidatos = [];
            foreach ($filas as $f) {
                $nombre = trim($f->candidato);
                // Blanco, nulos y no marcados llegan como candidato en algunos años
                if (Partidos::clave($f->partido) === 'VOTO EN BLANCO' || preg_match('/^(VOTOS? )?(EN BLANCO|NULOS?|NO MARCADOS?)$/u', mb_strtoupper($nombre))) {
                    continue;
                }
                $id = Partidos::clave($f->partido).'|'.$nombre;
                $candidatos[$id] ??= ['candidato' => $nombre, 'partido' => $f->partido, 'clave' => Partidos::clave($f->partido), 'votos' => 0, 'municipios' => []];
                $candidatos[$id]['votos'] += (int) $f->votos;
                $candidatos[$id]['municipios'][$f->nombre] = [
                    'votos' => (int) $f->votos,
                    'porcentaje' => ($t = (int) ($totalMunicipio[$f->municipio_id] ?? 0)) ? round($f->votos * 100 / $t, 1) : null,
                ];
            }

            foreach ($candidatos as &$c) {
                uasort($c['municipios'], fn ($a, $b) => $b['votos'] <=> $a['votos']);
                $acumulado = 0;
                $para80 = 0;
                foreach ($c['municipios'] as $m) {
                    $acumulado += $m['votos'];
                    $para80++;
                    if ($acumulado >= $c['votos'] * 0.8) {
                        break;
                    }
                }
                $fuerte = array_key_first($c['municipios']);
                $c['fuerte'] = $fuerte;
                $c['fuerte_pct'] = $c['municipios'][$fuerte]['porcentaje'] ?? null;
                $c['concentracion'] = $c['votos'] ? round(($c['municipios'][$fuerte]['votos'] ?? 0) * 100 / $c['votos'], 1) : null;
                $c['para80'] = $para80;
                $c['con_votos'] = count(array_filter($c['municipios'], fn ($m) => $m['votos'] > 0));
                // Solo los 5 municipios más fuertes, para no cargar la vista
                $c['top'] = array_slice($c['municipios'], 0, 5, true);
                unset($c['municipios']);
            }
            unset($c);
            uasort($candidatos, fn ($a, $b) => $b['votos'] <=> $a['votos']);

            return ['anio' => (int) $anio, 'candidatos' => array_values($candidatos)];
        });

        $lista = collect($todos['candidatos']);
        if ($clave) {
            $lista = $lista->where('clave', $clave);
        }

        return ['anio' => $todos['anio'], 'candidatos' => $lista->take($limite)->values()->all()];
    }

    // ===================== Testigos electorales =====================

    /**
     * Señales de resultados atípicos por municipio en la última elección del
     * cargo, para decidir dónde poner testigos. No prueban fraude: marcan
     * dónde conviene mirar con más cuidado.
     *
     * - participación muy alta (sobre el potencial actual);
     * - salto de votos frente a la elección anterior, mucho mayor que el del departamento;
     * - casi unanimidad (el ganador con 80 % o más);
     * - el ganador subió muchísimo frente a la anterior;
     * - el mismo día, este cargo tuvo muchos más o menos votos que los otros
     *   (gobernación, asamblea, alcaldía y concejo se votan juntos).
     */
    public function testigos(int $departamentoId, string $cargo): array
    {
        $d = $this->analisis->datos($departamentoId, $cargo);
        $anios = $d['anios'];
        $ultimo = end($anios) ?: null;
        $previo = count($anios) > 1 ? $anios[count($anios) - 2] : null;
        if (! $ultimo) {
            return ['ultimo' => null, 'municipios' => [], 'conteo' => []];
        }

        // Otros cargos votados el mismo año (mismo día)
        $mismoDia = [];
        $jornada = collect(self::JORNADAS)->first(fn ($g) => in_array($cargo, $g, true)) ?? [];
        foreach ($this->analisis->cargos($departamentoId) as $otro) {
            if ($otro === $cargo || ! in_array($otro, $jornada, true)) {
                continue;
            }
            $o = $this->analisis->datos($departamentoId, $otro);
            if (in_array($ultimo, $o['anios'], true)) {
                $mismoDia[$otro] = $o['municipios'];
            }
        }

        $crecDepto = $previo && ($d['totales'][$previo] ?? 0) ? ($d['totales'][$ultimo] - $d['totales'][$previo]) * 100 / $d['totales'][$previo] : null;

        $filas = [];
        foreach ($d['municipios'] as $m) {
            $total = $m['total'][$ultimo] ?? 0;
            if ($total <= 0) {
                continue;
            }
            $antes = $previo ? ($m['total'][$previo] ?? 0) : 0;
            $votos = [];
            foreach ($m['partidos'] as $clave => $x) {
                if ($clave !== 'VOTO EN BLANCO' && ($x[$ultimo] ?? 0) > 0) {
                    $votos[$clave] = $x[$ultimo];
                }
            }
            arsort($votos);
            $ganador = array_key_first($votos);
            $pctGanador = $ganador ? round($votos[$ganador] * 100 / $total, 1) : null;
            $pctGanadorAntes = $ganador && $antes ? round(($m['partidos'][$ganador][$previo] ?? 0) * 100 / $antes, 1) : null;

            $participacion = $m['potencial'] ? round($total * 100 / $m['potencial'], 1) : null;
            $crecio = $antes ? round(($total - $antes) * 100 / $antes, 1) : null;

            $otros = [];
            foreach ($mismoDia as $otro => $ms) {
                if ($v = ($ms[$m['id']]['total'][$ultimo] ?? 0)) {
                    $otros[$otro] = $v;
                }
            }
            $diferenciaMismoDia = $otros ? round(($total - array_sum($otros) / count($otros)) * 100 / (array_sum($otros) / count($otros)), 1) : null;

            $senales = [];
            if ($participacion !== null && $participacion >= 85) {
                $senales['participacion'] = "Votó el {$participacion}% del potencial";
            }
            if ($crecio !== null && $crecDepto !== null && $crecio - $crecDepto >= 40 && $total >= 500) {
                $senales['salto'] = "Los votos crecieron {$crecio}% (el departamento, ".round($crecDepto, 1).'%)';
            }
            if ($pctGanador !== null && $pctGanador >= 80 && count($votos) > 1) {
                $senales['unanimidad'] = "El ganador sacó {$pctGanador}%";
            }
            // Solo si el partido ya se había presentado: las coaliciones nuevas siempre "suben" desde 0
            if ($pctGanadorAntes && $pctGanador - $pctGanadorAntes >= 40) {
                $senales['subida'] = "El ganador pasó de {$pctGanadorAntes}% a {$pctGanador}%";
            }
            if ($diferenciaMismoDia !== null && abs($diferenciaMismoDia) >= 15 && $total >= 500) {
                $senales['mismo_dia'] = 'Votos '.($diferenciaMismoDia > 0 ? '+' : '')."{$diferenciaMismoDia}% frente a los otros cargos del mismo día";
            }

            $puntos = array_sum(array_intersect_key(self::PESOS_SENALES, $senales));
            $filas[] = [
                'id' => $m['id'],
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $m['potencial'],
                'votos' => $total,
                'participacion' => $participacion,
                'crecio' => $crecio,
                'ganador_nombre' => $ganador ? ($d['partidos'][$ganador]['nombre'] ?? $ganador) : null,
                'pct_ganador' => $pctGanador,
                'diferencia_mismo_dia' => $diferenciaMismoDia,
                'senales' => $senales,
                'puntos' => $puntos,
                'nivel' => $puntos >= 4 ? 'alto' : ($puntos >= 2 ? 'medio' : ($puntos > 0 ? 'bajo' : 'ninguno')),
            ];
        }
        usort($filas, fn ($a, $b) => [$b['puntos'], $b['votos']] <=> [$a['puntos'], $a['votos']]);

        return [
            'ultimo' => $ultimo,
            'previo' => $previo,
            'crecimiento_depto' => $crecDepto !== null ? round($crecDepto, 1) : null,
            'mismo_dia' => array_keys($mismoDia),
            'conteo' => collect($filas)->countBy('nivel')->all(),
            'municipios' => $filas,
        ];
    }

    /** Cargos que se votan el mismo día (la presidencial va en otra fecha). */
    public const JORNADAS = [
        ['gobernacion', 'asamblea', 'alcaldia', 'concejo'],
        ['senado', 'camara'],
    ];

    /** Peso de cada señal en el nivel del municipio. */
    public const PESOS_SENALES = [
        'participacion' => 2,
        'salto' => 2,
        'unanimidad' => 2,
        'subida' => 1,
        'mismo_dia' => 2,
    ];

    public const NIVELES = [
        'alto' => ['Alto', '#dc2626'],
        'medio' => ['Medio', '#f59e0b'],
        'bajo' => ['Bajo', '#facc15'],
        'ninguno' => ['Sin señales', '#94a3b8'],
    ];
}
