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
        private AnalisisPuestos $puestos,
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
    public function maquinaria(int $departamentoId, string $clave, ?int $hasta = null): array
    {
        $cargos = array_values(array_intersect([self::OPINION, ...self::ESTRUCTURA], $this->analisis->cargos($departamentoId)));
        $por = [];
        $anios = [];
        $nombre = $clave;
        foreach ($cargos as $cargo) {
            $d = $this->analisis->datos($departamentoId, $cargo);
            // La última elección de cada cargo que no sea posterior a $hasta
            $anio = collect($d['anios'])->filter(fn ($a) => ! $hasta || $a <= $hasta)->last();
            if (! $anio) {
                continue;
            }
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
    public function lideres(int $departamentoId, string $cargo, ?string $clave = null, int $limite = 40, ?int $anio = null): array
    {
        $todos = $this->recordar('lideres', [$departamentoId, $cargo, $anio], function () use ($departamentoId, $cargo, $anio) {
            $anio ??= DB::table('historico_electoral')->where('departamento_id', $departamentoId)->where('cargo', $cargo)->max('anio');
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
     * Prioridad de testigos por municipio en la última elección del cargo.
     *
     * Dos lecturas, ninguna prueba fraude:
     * - Señales fijas (alertas): participación de 85 % o más, salto de votos,
     *   casi unanimidad, subida del ganador, diferencia con los otros cargos
     *   del mismo día, y nulos o no marcados muy altos (con datos por puesto).
     * - Puntaje 0–100: qué tan raro es el municipio frente a los demás del
     *   departamento en cada indicador (desviación robusta: mediana y MAD).
     *   Así siempre hay un orden, aunque ninguno cruce una alerta.
     *
     * @param  int|null  $disponibles  testigos con que se cuenta: se reparten por mesa en orden de prioridad
     */
    public function testigos(int $departamentoId, string $cargo, ?int $disponibles = null, ?int $anio = null): array
    {
        $d = $this->analisis->datosHasta($departamentoId, $cargo, $anio);
        $anios = $d['anios'];
        $ultimo = end($anios) ?: null;
        $previo = count($anios) > 1 ? $anios[count($anios) - 2] : null;
        if (! $ultimo) {
            return ['ultimo' => null, 'municipios' => [], 'conteo' => [], 'puestos' => [], 'mismo_dia' => [], 'previo' => null];
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
        $mesas = DB::table('municipios')->where('departamento_id', $departamentoId)->pluck('mesas', 'id');
        $noValidos = $this->noValidos($departamentoId, $cargo, $ultimo);

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

            $otros = [];
            foreach ($mismoDia as $otro => $ms) {
                if ($v = ($ms[$m['id']]['total'][$ultimo] ?? 0)) {
                    $otros[$otro] = $v;
                }
            }
            $promedioOtros = $otros ? array_sum($otros) / count($otros) : null;
            $nv = $noValidos[$m['id']] ?? null;
            $crecio = $antes ? round(($total - $antes) * 100 / $antes, 1) : null;

            $filas[] = [
                'id' => $m['id'],
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $m['potencial'],
                'mesas' => isset($mesas[$m['id']]) ? (int) $mesas[$m['id']] : null,
                'votos' => $total,
                'participacion' => $m['potencial'] ? round($total * 100 / $m['potencial'], 1) : null,
                'crecio' => $crecio,
                'ganador_nombre' => $ganador ? ($d['partidos'][$ganador]['nombre'] ?? $ganador) : null,
                'pct_ganador' => $pctGanador,
                'pct_ganador_antes' => $pctGanadorAntes,
                'listas' => count($votos),
                'diferencia_mismo_dia' => $promedioOtros ? round(($total - $promedioOtros) * 100 / $promedioOtros, 1) : null,
                'nulos_pct' => $nv['nulos_pct'] ?? null,
                'no_marcados_pct' => $nv['no_marcados_pct'] ?? null,
                // Indicadores donde "más alto" es más raro, para el puntaje
                'metricas' => [
                    'participacion' => $m['potencial'] ? $total * 100 / $m['potencial'] : null,
                    'salto' => $crecio !== null && $crecDepto !== null ? $crecio - $crecDepto : null,
                    'unanimidad' => count($votos) > 1 ? $pctGanador : null,
                    'mismo_dia' => $promedioOtros ? abs(($total - $promedioOtros) * 100 / $promedioOtros) : null,
                    'nulos' => $nv['nulos_pct'] ?? null,
                    'no_marcados' => $nv['no_marcados_pct'] ?? null,
                ],
            ];
        }

        // Mediana y dispersión robusta de cada indicador en el departamento
        $estadisticas = [];
        foreach (array_keys(self::METRICAS) as $k) {
            $valores = array_values(array_filter(array_column(array_column($filas, 'metricas'), $k), fn ($v) => $v !== null));
            $estadisticas[$k] = self::robusta($valores);
        }
        $med = fn ($k) => round($estadisticas[$k]['mediana'] ?? 0, 1);

        foreach ($filas as &$f) {
            $senales = [];

            if ($f['participacion'] !== null && $f['participacion'] >= 85) {
                $senales['participacion'] = "Votó el {$f['participacion']}% del potencial";
            }
            if ($f['metricas']['salto'] !== null && $f['metricas']['salto'] >= 40 && $f['votos'] >= 500) {
                $senales['salto'] = "Los votos crecieron {$f['crecio']}% (el departamento, ".round($crecDepto, 1).'%)';
            }
            if ($f['pct_ganador'] !== null && $f['pct_ganador'] >= 80 && $f['listas'] > 1) {
                $senales['unanimidad'] = "El ganador sacó {$f['pct_ganador']}%";
            }
            // Solo si el partido ya se había presentado: las coaliciones nuevas siempre "suben" desde 0
            if ($f['pct_ganador_antes'] && $f['pct_ganador'] - $f['pct_ganador_antes'] >= 40) {
                $senales['subida'] = "El ganador pasó de {$f['pct_ganador_antes']}% a {$f['pct_ganador']}%";
            }
            if ($f['diferencia_mismo_dia'] !== null && abs($f['diferencia_mismo_dia']) >= 15 && $f['votos'] >= 500) {
                $senales['mismo_dia'] = 'Votos '.($f['diferencia_mismo_dia'] > 0 ? '+' : '')."{$f['diferencia_mismo_dia']}% frente a los otros cargos del mismo día";
            }
            if ($f['nulos_pct'] !== null && $f['nulos_pct'] >= max(2 * $med('nulos'), $med('nulos') + 3) && $f['votos'] >= 500) {
                $senales['nulos'] = "Nulos {$f['nulos_pct']}% (mediana del departamento {$med('nulos')}%)";
            }
            if ($f['no_marcados_pct'] !== null && $f['no_marcados_pct'] >= max(2 * $med('no_marcados'), $med('no_marcados') + 5) && $f['votos'] >= 500) {
                $senales['no_marcados'] = "No marcados {$f['no_marcados_pct']}% (mediana {$med('no_marcados')}%)";
            }

            // Puntaje: suma de desviaciones hacia arriba (cada una hasta 4), sobre 8
            $suma = 0;
            $atipicos = [];
            foreach (self::METRICAS as $k => $nombre) {
                $v = $f['metricas'][$k];
                $e = $estadisticas[$k];
                if ($v === null || ! $e['escala']) {
                    continue;
                }
                $z = ($v - $e['mediana']) / $e['escala'];
                if ($z > 0) {
                    $suma += min($z, 4);
                }
                if ($z >= 2) {
                    $atipicos[] = $nombre;
                }
            }

            $puntos = array_sum(array_intersect_key(self::PESOS_SENALES, $senales));
            $f['puntaje'] = (int) round(100 * min(1, $suma / 8));
            $f['senales'] = $senales;
            $f['atipicos'] = $atipicos;
            $f['puntos'] = $puntos;
            $f['nivel'] = match (true) {
                $puntos >= 4 || $f['puntaje'] >= 70 => 'alto',
                $puntos >= 2 || $f['puntaje'] >= 45 => 'medio',
                $puntos > 0 || $f['puntaje'] >= 25 => 'bajo',
                default => 'ninguno',
            };
            unset($f['metricas'], $f['pct_ganador_antes'], $f['listas']);
        }
        unset($f);

        usort($filas, fn ($a, $b) => [$b['puntos'] * 20 + $b['puntaje'], $b['votos']] <=> [$a['puntos'] * 20 + $a['puntaje'], $a['votos']]);

        // Reparto de testigos: uno por mesa, en orden de prioridad
        $restantes = (int) $disponibles;
        foreach ($filas as $i => &$f) {
            $f['prioridad'] = $i + 1;
            $f['testigos'] = $disponibles !== null && $f['mesas'] ? min($f['mesas'], max(0, $restantes)) : null;
            $restantes -= $f['testigos'] ?? 0;
        }
        unset($f);

        $enRiesgo = collect($filas)->whereIn('nivel', ['alto', 'medio']);

        return [
            'ultimo' => $ultimo,
            'previo' => $previo,
            'crecimiento_depto' => $crecDepto !== null ? round($crecDepto, 1) : null,
            'mismo_dia' => array_keys($mismoDia),
            'hay_nulos' => (bool) $noValidos,
            'mediana_nulos' => $estadisticas['nulos']['mediana'] !== null ? $med('nulos') : null,
            'conteo' => collect($filas)->countBy('nivel')->all(),
            'mesas' => array_sum(array_map(fn ($f) => $f['mesas'] ?? 0, $filas)),
            'mesas_riesgo' => (int) $enRiesgo->sum('mesas'),
            'disponibles' => $disponibles,
            'cubiertas_riesgo' => $disponibles !== null ? (int) $enRiesgo->sum('testigos') : null,
            'municipios' => $filas,
            'puestos' => $this->puestosVigilar($departamentoId, $cargo, $ultimo),
        ];
    }

    /** Mediana y escala robusta (1,4826 × MAD; si es 0, la desviación estándar). */
    public static function robusta(array $valores): array
    {
        if (count($valores) < 3) {
            return ['mediana' => null, 'escala' => null];
        }
        $mediana = self::mediana($valores);
        $escala = 1.4826 * self::mediana(array_map(fn ($v) => abs($v - $mediana), $valores));
        if ($escala < 1e-9) {
            $media = array_sum($valores) / count($valores);
            $escala = sqrt(array_sum(array_map(fn ($v) => ($v - $media) ** 2, $valores)) / count($valores));
        }

        return ['mediana' => $mediana, 'escala' => $escala > 1e-9 ? $escala : null];
    }

    private static function mediana(array $v): float
    {
        sort($v);
        $n = count($v);

        return $n % 2 ? (float) $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }

    /**
     * % de nulos y no marcados por municipio (sobre el total de tarjetas),
     * desde los datos por puesto. Vacío si no están cargados.
     *
     * @return array<int, array{nulos_pct: float, no_marcados_pct: float}>
     */
    private function noValidos(int $departamentoId, string $cargo, int $anio): array
    {
        return $this->recordar('noValidos', [$departamentoId, $cargo, $anio], function () use ($departamentoId, $cargo, $anio) {
            $filas = DB::table('historico_puestos as h')
                ->join('municipios as m', 'm.id', '=', 'h.municipio_id')
                ->where('m.departamento_id', $departamentoId)
                ->where('h.cargo', $cargo)->where('h.anio', $anio)->where('h.vuelta', 1)
                ->groupBy('h.municipio_id')
                ->selectRaw('h.municipio_id, SUM(h.votos) as total,
                    SUM(CASE WHEN h.partido = ? THEN h.votos ELSE 0 END) as nulos,
                    SUM(CASE WHEN h.partido = ? THEN h.votos ELSE 0 END) as no_marcados', [ImportadorPuestos::NULOS, ImportadorPuestos::NO_MARCADOS])
                ->get();

            $out = [];
            foreach ($filas as $f) {
                if ($f->total > 0) {
                    $out[(int) $f->municipio_id] = [
                        'nulos_pct' => round($f->nulos * 100 / $f->total, 1),
                        'no_marcados_pct' => round($f->no_marcados * 100 / $f->total, 1),
                    ];
                }
            }

            return $out;
        });
    }

    /** Puestos del departamento con señales (de los municipios con datos por puesto). */
    public function puestosVigilar(int $departamentoId, string $cargo, int $anio, int $limite = 25): array
    {
        $municipios = $this->puestos->municipios($departamentoId);
        $lista = [];
        foreach ($municipios as $munId => $mun) {
            if (! in_array($anio, $this->puestos->cargos($munId)[$cargo] ?? [], true)) {
                continue;
            }
            foreach ($this->puestos->puestos($munId, $cargo, $anio)['puestos'] as $p) {
                if ($p['senales']) {
                    $lista[] = $p + ['municipio' => $mun['nombre']];
                }
            }
        }
        usort($lista, fn ($a, $b) => [count($b['senales']), $b['votos']] <=> [count($a['senales']), $a['votos']]);

        return array_slice($lista, 0, $limite);
    }

    /** Indicadores del puntaje (más alto = más raro). */
    public const METRICAS = [
        'participacion' => 'participación',
        'salto' => 'crecimiento de votos',
        'unanimidad' => '% del ganador',
        'mismo_dia' => 'diferencia con los otros cargos del día',
        'nulos' => 'votos nulos',
        'no_marcados' => 'tarjetas no marcadas',
    ];

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
        'nulos' => 2,
        'no_marcados' => 2,
    ];

    public const NIVELES = [
        'alto' => ['Alto', '#dc2626'],
        'medio' => ['Medio', '#f59e0b'],
        'bajo' => ['Bajo', '#facc15'],
        'ninguno' => ['Sin señales', '#94a3b8'],
    ];
}
