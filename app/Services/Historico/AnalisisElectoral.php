<?php

namespace App\Services\Historico;

use App\Models\PartidoIdeologia;
use App\Support\Partidos;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cálculos del "Análisis electoral" (sobre historico_electoral).
 *
 * - Fortines: dónde saca más votos cada partido y cómo cambió entre elecciones.
 * - Municipios: tamaño (potencial electoral), votos y participación.
 * - Tendencia: izquierda, centro o derecha, según la tabla partidos_ideologia.
 * - Alcaldes: el alcalde en funciones frente a los candidatos a gobernación.
 *
 * Los partidos se siguen entre años por Partidos::clave(). Solo primera vuelta.
 * Comparte la versión de caché del Histórico: se renueva al importar.
 */
class AnalisisElectoral
{
    /** Súbelo si cambia cómo se calcula (p. ej. Partidos::clave()), para no leer caché vieja. */
    private const VERSION_CALCULO = 4;

    private function recordar(string $metodo, array $args, \Closure $calcular): mixed
    {
        $version = (int) Cache::get(HistoricoElectoralStats::CACHE_VERSION, 1);

        return Cache::rememberForever("analisis:{$version}.".self::VERSION_CALCULO.":{$metodo}:".md5(serialize($args)), $calcular);
    }

    /** Cargos con datos en el departamento, en el orden del enum. */
    public function cargos(int $departamentoId): array
    {
        return $this->recordar('cargos', [$departamentoId], function () use ($departamentoId) {
            $hay = DB::table('historico_electoral')->where('departamento_id', $departamentoId)
                ->distinct()->pluck('cargo')->all();

            return collect(\App\Enums\CargoEnum::cases())
                ->filter(fn ($c) => in_array($c->value, $hay, true))
                ->map(fn ($c) => $c->value)->values()->all();
        });
    }

    /**
     * Votos por año, municipio y partido (por clave) de un cargo en un
     * departamento, con los totales por municipio y año.
     *
     * @return array{anios: int[], partidos: array, municipios: array, totales: array}
     */
    public function datos(int $departamentoId, string $cargo): array
    {
        return $this->recordar('datos', [$departamentoId, $cargo], function () use ($departamentoId, $cargo) {
            $filas = DB::table('historico_electoral as h')
                ->join('municipios as m', 'm.id', '=', 'h.municipio_id')
                ->where('h.departamento_id', $departamentoId)
                ->where('h.cargo', $cargo)
                ->where('h.vuelta', 1)
                ->groupBy('h.anio', 'h.municipio_id', 'm.nombre', 'm.codigo_dane', 'm.potencial_electoral', 'h.partido')
                ->selectRaw('h.anio, h.municipio_id, m.nombre, m.codigo_dane, m.potencial_electoral, h.partido, SUM(h.votos) as votos')
                ->get();

            $anios = [];
            $partidos = [];   // clave => [nombre, votos totales, votos por año]
            $municipios = []; // id => [nombre, codigo_dane, potencial, total[anio], partidos[clave][anio]]
            $totales = [];    // anio => votos válidos del departamento

            foreach ($filas as $f) {
                $anio = (int) $f->anio;
                $clave = Partidos::clave($f->partido);
                $votos = (int) $f->votos;
                $anios[$anio] = true;

                $partidos[$clave] ??= ['clave' => $clave, 'nombre' => $f->partido, 'ultimo' => 0, 'votos' => 0, 'por_anio' => []];
                $partidos[$clave]['votos'] += $votos;
                $partidos[$clave]['por_anio'][$anio] = ($partidos[$clave]['por_anio'][$anio] ?? 0) + $votos;
                // Nombre que se muestra: el de la elección más reciente
                if ($anio >= $partidos[$clave]['ultimo']) {
                    $partidos[$clave]['ultimo'] = $anio;
                    $partidos[$clave]['nombre'] = $f->partido;
                }

                $id = (int) $f->municipio_id;
                $municipios[$id] ??= [
                    'id' => $id, 'nombre' => $f->nombre, 'codigo_dane' => $f->codigo_dane,
                    'potencial' => $f->potencial_electoral !== null ? (int) $f->potencial_electoral : null,
                    'total' => [], 'partidos' => [],
                ];
                $municipios[$id]['total'][$anio] = ($municipios[$id]['total'][$anio] ?? 0) + $votos;
                $municipios[$id]['partidos'][$clave][$anio] = ($municipios[$id]['partidos'][$clave][$anio] ?? 0) + $votos;
                $totales[$anio] = ($totales[$anio] ?? 0) + $votos;
            }

            $anios = array_keys($anios);
            sort($anios);
            uasort($partidos, fn ($a, $b) => $b['votos'] <=> $a['votos']);

            return compact('anios', 'partidos', 'municipios', 'totales');
        });
    }

    /**
     * Fortines de un partido: por municipio, sus votos y porcentaje en cada
     * año, el cambio frente a la elección anterior y si ganó ahí.
     */
    public function fortines(int $departamentoId, string $cargo, string $clave): array
    {
        $d = $this->datos($departamentoId, $cargo);
        $anios = $d['anios'];

        // Años en que el partido se presentó en el departamento. Las comparaciones
        // usan su última elección y la anterior a esa, no las del departamento:
        // si no, un partido que no fue en la última elección sale vacío.
        $participo = array_values(array_filter($anios, fn ($a) => ($d['partidos'][$clave]['por_anio'][$a] ?? 0) > 0));
        $ultimo = end($participo) ?: (end($anios) ?: null);
        $previo = count($participo) > 1 ? $participo[count($participo) - 2] : null;

        $filas = [];
        foreach ($d['municipios'] as $m) {
            $porAnio = [];
            foreach ($anios as $a) {
                $votos = $m['partidos'][$clave][$a] ?? 0;
                $total = $m['total'][$a] ?? 0;
                // ¿Ganó? = el partido más votado del municipio ese año
                $gano = false;
                if ($votos > 0) {
                    $maximo = max(array_map(fn ($p) => $p[$a] ?? 0, $m['partidos']));
                    $gano = $votos === $maximo;
                }
                $porAnio[$a] = [
                    'votos' => $votos,
                    'porcentaje' => $total ? round($votos * 100 / $total, 1) : null,
                    'gano' => $gano,
                    'hubo' => $total > 0,
                    // false = el partido no tuvo candidato ahí ese año (sin votos en el municipio)
                    'participo' => $votos > 0,
                ];
            }

            $pUlt = $ultimo ? $porAnio[$ultimo]['porcentaje'] : null;
            $pPrev = $previo ? $porAnio[$previo]['porcentaje'] : null;

            $filas[] = [
                'id' => $m['id'],
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $m['potencial'],
                'por_anio' => $porAnio,
                'cambio' => $pUlt !== null && $pPrev !== null && ($porAnio[$previo]['votos'] > 0 || $porAnio[$ultimo]['votos'] > 0)
                    ? round($pUlt - $pPrev, 1) : null,
            ];
        }

        $departamento = [];
        foreach ($anios as $a) {
            $votos = $d['partidos'][$clave]['por_anio'][$a] ?? 0;
            $departamento[$a] = [
                'votos' => $votos,
                'porcentaje' => ($d['totales'][$a] ?? 0) ? round($votos * 100 / $d['totales'][$a], 1) : null,
                'ganados' => collect($filas)->where("por_anio.{$a}.gano", true)->count(),
                'participo' => in_array($a, $participo, true),
            ];
        }

        return [
            'anios' => $anios,
            'ultimo' => $ultimo,
            'previo' => $previo,
            'ultimo_depto' => end($anios) ?: null,
            'partido' => $d['partidos'][$clave] ?? null,
            'departamento' => $departamento,
            'municipios' => $filas,
        ];
    }

    /**
     * Tendencia política: por municipio y año, quién ganó, de qué ideología,
     * y cómo se reparten los votos entre izquierda, centro y derecha.
     *
     * No se guarda en caché porque la clasificación de los partidos se edita;
     * parte de datos(), que sí está en caché. El voto en blanco no cuenta.
     *
     * @param  array<string, string>|null  $ideologias  clave => ideología (por defecto, la tabla)
     */
    public function tendencia(int $departamentoId, string $cargo, ?array $ideologias = null): array
    {
        $ideologias ??= PartidoIdeologia::mapa();
        $d = $this->datos($departamentoId, $cargo);

        // Coaliciones sin clasificar: se infiere por sus partidos, si coinciden de bloque
        $inferidas = [];
        foreach ($d['partidos'] as $clave => $p) {
            if (! isset($ideologias[$clave]) && $clave !== 'VOTO EN BLANCO'
                && ($i = self::inferir($p['nombre'], $ideologias))) {
                $inferidas[$clave] = $i;
            }
        }
        $ideologias += $inferidas;
        $anios = $d['anios'];
        $ultimo = end($anios) ?: null;
        $previo = count($anios) > 1 ? $anios[count($anios) - 2] : null;

        $vacio = fn () => ['izquierda' => 0, 'centro' => 0, 'derecha' => 0, 'sin_clasificar' => 0];
        $deptoVotos = [];
        $deptoPos = [];
        $filas = [];

        foreach ($d['municipios'] as $m) {
            $porAnio = [];
            foreach ($anios as $a) {
                $votos = $vacio();
                $suma = 0;
                $pesos = 0;
                $ganador = null;
                $maximo = 0;

                foreach ($m['partidos'] as $clave => $porAnioPartido) {
                    $v = $porAnioPartido[$a] ?? 0;
                    if ($v <= 0 || $clave === 'VOTO EN BLANCO') {
                        continue;
                    }
                    $ideologia = $ideologias[$clave] ?? null;
                    $votos[PartidoIdeologia::bloque($ideologia) ?? 'sin_clasificar'] += $v;
                    if ($ideologia) {
                        $suma += $v * PartidoIdeologia::IDEOLOGIAS[$ideologia][1];
                        $pesos += $v;
                    }
                    if ($v > $maximo) {
                        $maximo = $v;
                        $ganador = $clave;
                    }
                }

                $total = array_sum($votos);
                $ideologiaGanador = $ganador ? ($ideologias[$ganador] ?? null) : null;
                $porAnio[$a] = [
                    'hubo' => $total > 0,
                    'votos' => $votos,
                    'porcentaje' => array_map(fn ($v) => $total ? round($v * 100 / $total, 1) : null, $votos),
                    // De -2 (todo izquierda) a 2 (todo derecha), solo con los votos clasificados
                    'indice' => $pesos ? round($suma / $pesos, 2) : null,
                    'ganador' => $ganador,
                    'ganador_nombre' => $ganador ? ($d['partidos'][$ganador]['nombre'] ?? $ganador) : null,
                    'ideologia' => $ideologiaGanador,
                    'bloque' => PartidoIdeologia::bloque($ideologiaGanador),
                ];

                foreach ($votos as $b => $v) {
                    $deptoVotos[$a][$b] = ($deptoVotos[$a][$b] ?? 0) + $v;
                }
                $deptoPos[$a] = [($deptoPos[$a][0] ?? 0) + $suma, ($deptoPos[$a][1] ?? 0) + $pesos];
            }

            $u = $ultimo ? $porAnio[$ultimo] : null;
            $p = $previo ? $porAnio[$previo] : null;

            $filas[] = [
                'id' => $m['id'],
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $m['potencial'],
                'por_anio' => $porAnio,
                // Giro = el bloque del ganador cambió entre las dos últimas elecciones
                'giro' => $u && $p && $u['bloque'] && $p['bloque'] && $u['bloque'] !== $p['bloque'],
                'cambio_indice' => $u && $p && $u['indice'] !== null && $p['indice'] !== null
                    ? round($u['indice'] - $p['indice'], 2) : null,
            ];
        }

        $departamento = [];
        foreach ($anios as $a) {
            $votos = $deptoVotos[$a] ?? $vacio();
            $total = array_sum($votos);
            $ganados = $vacio();
            foreach ($filas as $f) {
                if ($f['por_anio'][$a]['hubo']) {
                    $ganados[$f['por_anio'][$a]['bloque'] ?? 'sin_clasificar']++;
                }
            }
            $departamento[$a] = [
                'votos' => $votos,
                'porcentaje' => array_map(fn ($v) => $total ? round($v * 100 / $total, 1) : null, $votos),
                'indice' => ($deptoPos[$a][1] ?? 0) ? round($deptoPos[$a][0] / $deptoPos[$a][1], 2) : null,
                'ganados' => $ganados,
            ];
        }

        // Partidos con votos que aún no tienen ideología, de más a menos votados
        $sinClasificar = collect($d['partidos'])
            ->reject(fn ($p, $clave) => $clave === 'VOTO EN BLANCO' || isset($ideologias[$clave]))
            ->map(fn ($p) => ['clave' => $p['clave'], 'nombre' => $p['nombre'], 'votos' => $p['votos']])
            ->values()->all();

        return [
            'anios' => $anios,
            'ultimo' => $ultimo,
            'previo' => $previo,
            'departamento' => $departamento,
            'municipios' => $filas,
            'sin_clasificar' => $sinClasificar,
            'inferidas' => $inferidas,
        ];
    }

    /**
     * Ideología de una coalición según los partidos conocidos que nombra:
     * solo si todos caen en el mismo bloque (izquierda, centro o derecha).
     * Liberal + Alianza Verde = centro-izquierda; Liberal + Conservador = nada.
     */
    public static function inferir(string $nombre, array $ideologias): ?string
    {
        $posiciones = [];
        $bloques = [];
        foreach (Partidos::miembros($nombre) as $miembro) {
            if ($miembro === 'VOTO EN BLANCO' || ! isset($ideologias[$miembro])) {
                continue;
            }
            $posiciones[] = PartidoIdeologia::IDEOLOGIAS[$ideologias[$miembro]][1];
            $bloques[PartidoIdeologia::bloque($ideologias[$miembro])] = true;
        }

        if (! $posiciones || count($bloques) > 1) {
            return null;
        }

        $media = (int) round(array_sum($posiciones) / count($posiciones));

        return collect(PartidoIdeologia::IDEOLOGIAS)->search(fn ($v) => $v[1] === $media) ?: null;
    }

    /**
     * Tamaño de cada municipio: potencial electoral, su peso en el
     * departamento y los votos válidos de la última elección del cargo.
     */
    public function municipios(int $departamentoId, string $cargo): array
    {
        $d = $this->datos($departamentoId, $cargo);
        $ultimo = end($d['anios']) ?: null;

        $catalogo = DB::table('municipios')->where('departamento_id', $departamentoId)
            ->orderByDesc('potencial_electoral')
            ->get(['id', 'nombre', 'codigo_dane', 'potencial_electoral', 'potencial_mujeres', 'mesas', 'potencial_anio']);

        $totalPotencial = (int) $catalogo->sum('potencial_electoral');
        $acumulado = 0;
        $filas = [];

        foreach ($catalogo as $m) {
            $potencial = (int) $m->potencial_electoral;
            $acumulado += $potencial;
            $votos = $ultimo ? ($d['municipios'][$m->id]['total'][$ultimo] ?? null) : null;

            $filas[] = [
                'id' => (int) $m->id,
                'municipio' => $m->nombre,
                'codigo_dane' => $m->codigo_dane,
                'potencial' => $m->potencial_electoral !== null ? $potencial : null,
                'peso' => $totalPotencial ? round($potencial * 100 / $totalPotencial, 1) : null,
                'acumulado' => $totalPotencial ? round($acumulado * 100 / $totalPotencial, 1) : null,
                'mujeres' => $potencial ? round($m->potencial_mujeres * 100 / $potencial, 1) : null,
                'mesas' => $m->mesas !== null ? (int) $m->mesas : null,
                'votos' => $votos,
                // Aproximada: votos válidos (sin nulos ni no marcados) sobre el potencial actual
                'participacion' => $votos && $potencial ? round($votos * 100 / $potencial, 1) : null,
            ];
        }

        return [
            'ultimo' => $ultimo,
            'anio_potencial' => $catalogo->max('potencial_anio'),
            'total_potencial' => $totalPotencial,
            'municipios' => $filas,
        ];
    }

    // ===================== Alcaldes y gobernación =====================

    /**
     * Candidatos por municipio de una elección, de más a menos votados, con el
     * total de votos del municipio (incluye el blanco). El voto en blanco no
     * va en la lista.
     */
    public function candidatos(int $departamentoId, string $cargo, int $anio): array
    {
        return $this->recordar('candidatos', [$departamentoId, $cargo, $anio], function () use ($departamentoId, $cargo, $anio) {
            $filas = DB::table('historico_electoral as h')
                ->join('municipios as m', 'm.id', '=', 'h.municipio_id')
                ->where('h.departamento_id', $departamentoId)
                ->where('h.cargo', $cargo)
                ->where('h.anio', $anio)
                ->where('h.vuelta', 1)
                ->groupBy('h.municipio_id', 'm.nombre', 'm.codigo_dane', 'm.potencial_electoral', 'h.partido', 'h.candidato')
                ->selectRaw('h.municipio_id, m.nombre, m.codigo_dane, m.potencial_electoral, h.partido, h.candidato, SUM(h.votos) as votos')
                ->get();

            $municipios = [];
            foreach ($filas as $f) {
                $mid = (int) $f->municipio_id;
                $municipios[$mid] ??= [
                    'id' => $mid, 'nombre' => $f->nombre, 'codigo_dane' => $f->codigo_dane,
                    'potencial' => $f->potencial_electoral !== null ? (int) $f->potencial_electoral : null,
                    'total' => 0, 'candidatos' => [],
                ];
                $votos = (int) $f->votos;
                $municipios[$mid]['total'] += $votos;

                $clave = Partidos::clave($f->partido);
                if ($clave === 'VOTO EN BLANCO' || $votos <= 0) {
                    continue;
                }
                $candidato = trim((string) $f->candidato);
                // Un candidato = partido + nombre (en gobernación y alcaldía hay uno por partido)
                $id = $clave.'|'.$candidato;
                $actual = $municipios[$mid]['candidatos'][$id] ?? ['clave' => $clave, 'partido' => $f->partido, 'candidato' => $candidato, 'votos' => 0];
                $actual['votos'] += $votos;
                $municipios[$mid]['candidatos'][$id] = $actual;
            }

            foreach ($municipios as &$m) {
                uasort($m['candidatos'], fn ($a, $b) => $b['votos'] <=> $a['votos']);
            }
            unset($m);

            return $municipios;
        });
    }

    /** Años de gobernación que se pueden cruzar con los alcaldes elegidos 4 años antes. */
    public function aniosAlcaldes(int $departamentoId): array
    {
        return $this->recordar('aniosAlcaldes', [$departamentoId], function () use ($departamentoId) {
            $anios = fn ($cargo) => DB::table('historico_electoral')->where('departamento_id', $departamentoId)
                ->where('cargo', $cargo)->distinct()->pluck('anio')->map(fn ($a) => (int) $a)->all();
            $alcaldia = $anios('alcaldia');
            $gobernacion = array_values(array_filter($anios('gobernacion'), fn ($a) => in_array($a - 4, $alcaldia, true)));
            sort($gobernacion);

            return $gobernacion;
        });
    }

    /** ¿Comparten algún partido conocido? (el mismo, o uno está dentro de la coalición del otro) */
    public static function aliados(string $partidoA, string $partidoB): bool
    {
        $claveA = Partidos::clave($partidoA);
        if ($claveA === Partidos::clave($partidoB)) {
            return $claveA !== 'VOTO EN BLANCO';
        }

        $a = array_diff(Partidos::miembros($partidoA), ['VOTO EN BLANCO']);
        $b = array_diff(Partidos::miembros($partidoB), ['VOTO EN BLANCO']);

        return (bool) array_intersect($a, $b);
    }

    /**
     * Alcalde en funciones (elegido 4 años antes) frente a lo que sacó cada
     * candidato a gobernación en su municipio.
     *
     * "Sobre su promedio" = % del candidato en el municipio − su % en todo el
     * departamento. Es una coincidencia estadística: no prueba que el alcalde
     * haya hecho campaña por nadie.
     *
     * @param  array<string, string>|null  $ideologias  clave => ideología (por defecto, la tabla)
     */
    public function alcaldes(int $departamentoId, int $anioGobernacion, ?array $ideologias = null): array
    {
        $ideologias ??= PartidoIdeologia::mapa();
        $anioAlcaldia = $anioGobernacion - 4;
        $alcaldias = $this->candidatos($departamentoId, 'alcaldia', $anioAlcaldia);
        $gobernacion = $this->candidatos($departamentoId, 'gobernacion', $anioGobernacion);

        // Candidatos a gobernación con su % en todo el departamento
        $totalDepto = array_sum(array_column($gobernacion, 'total'));
        $candidatos = [];
        foreach ($gobernacion as $m) {
            foreach ($m['candidatos'] as $id => $c) {
                $candidatos[$id] ??= ['id' => $id, 'clave' => $c['clave'], 'partido' => $c['partido'], 'candidato' => $c['candidato'], 'votos_depto' => 0];
                $candidatos[$id]['votos_depto'] += $c['votos'];
            }
        }
        foreach ($candidatos as $id => $c) {
            $candidatos[$id]['porcentaje'] = $totalDepto ? round($c['votos_depto'] * 100 / $totalDepto, 1) : 0.0;
        }
        uasort($candidatos, fn ($a, $b) => $b['votos_depto'] <=> $a['votos_depto']);

        $filas = [];
        foreach ($gobernacion as $mid => $m) {
            $alc = $alcaldias[$mid] ?? null;
            $lista = $alc ? array_values($alc['candidatos']) : [];
            $alcalde = $lista[0] ?? null;
            $ideologia = $alcalde ? ($ideologias[$alcalde['clave']] ?? self::inferir($alcalde['partido'], $ideologias)) : null;

            $porCandidato = [];
            foreach ($candidatos as $id => $c) {
                $votos = $m['candidatos'][$id]['votos'] ?? 0;
                $pct = $m['total'] ? round($votos * 100 / $m['total'], 1) : null;
                $porCandidato[$id] = [
                    'votos' => $votos,
                    'porcentaje' => $pct,
                    'sobre' => $pct !== null ? round($pct - $c['porcentaje'], 1) : null,
                    'aliado' => $alcalde !== null && self::aliados($alcalde['partido'], $c['partido']),
                ];
            }

            $filas[] = [
                'id' => $mid,
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $m['potencial'],
                'alcalde' => $alcalde ? [
                    'candidato' => $alcalde['candidato'],
                    'partido' => $alcalde['partido'],
                    'clave' => $alcalde['clave'],
                    'porcentaje' => $alc['total'] ? round($alcalde['votos'] * 100 / $alc['total'], 1) : null,
                    'ideologia' => $ideologia,
                    'bloque' => PartidoIdeologia::bloque($ideologia),
                ] : null,
                'ganador' => array_key_first($m['candidatos']),
                // El candidato que más sacó por encima de su promedio departamental
                'favorito' => collect($porCandidato)->filter(fn ($x) => $x['sobre'] !== null)->sortByDesc('sobre')->keys()->first(),
                'candidatos' => $porCandidato,
            ];
        }

        // Por candidato: municipios con alcalde aliado frente a los demás
        $resumen = [];
        foreach ($candidatos as $id => $c) {
            $conAlcalde = collect($filas)->filter(fn ($f) => $f['alcalde'] !== null);
            $con = $conAlcalde->filter(fn ($f) => $f['candidatos'][$id]['aliado']);
            $sin = $conAlcalde->reject(fn ($f) => $f['candidatos'][$id]['aliado']);
            $media = fn ($col) => $col->count() ? round($col->avg(fn ($f) => $f['candidatos'][$id]['sobre']), 1) : null;

            $resumen[$id] = $c + [
                'ganados' => collect($filas)->where('ganador', $id)->count(),
                'favorito_en' => collect($filas)->where('favorito', $id)->count(),
                'con_aliado' => $con->count(),
                'sobre_con_aliado' => $media($con),
                'sobre_sin_aliado' => $media($sin),
            ];
        }

        // Por bloque del alcalde: cuánto sacó cada candidato sobre su promedio
        $porBloque = [];
        foreach (['izquierda', 'centro', 'derecha', 'sin_clasificar'] as $bloque) {
            $grupo = collect($filas)->filter(fn ($f) => $f['alcalde'] !== null && ($f['alcalde']['bloque'] ?? 'sin_clasificar') === $bloque);
            $porBloque[$bloque] = [
                'municipios' => $grupo->count(),
                'sobre' => collect($candidatos)->map(fn ($c, $id) => $grupo->count()
                    ? round($grupo->avg(fn ($f) => $f['candidatos'][$id]['sobre']), 1) : null)->all(),
            ];
        }

        // Por partido del alcalde (2 o más municipios): quién sobresalió ahí
        $porPartido = collect($filas)->filter(fn ($f) => $f['alcalde'] !== null)
            ->groupBy(fn ($f) => $f['alcalde']['clave'])
            ->filter(fn ($g) => $g->count() >= 2)
            ->map(function ($g, $clave) use ($candidatos) {
                $sobre = collect($candidatos)->map(fn ($c, $id) => round($g->avg(fn ($f) => $f['candidatos'][$id]['sobre']), 1));
                $mejor = $sobre->sortDesc()->keys()->first();

                return [
                    'clave' => $clave,
                    'partido' => $g->first()['alcalde']['partido'],
                    'municipios' => $g->count(),
                    'mejor' => $mejor,
                    'mejor_sobre' => $mejor !== null ? $sobre[$mejor] : null,
                    'favorito_veces' => $mejor !== null ? $g->where('favorito', $mejor)->count() : 0,
                    'aliado' => $mejor !== null && self::aliados($g->first()['alcalde']['partido'], $candidatos[$mejor]['partido']),
                ];
            })->sortByDesc('municipios')->values()->all();

        return [
            'anio_gobernacion' => $anioGobernacion,
            'por_partido' => $porPartido,
            'anio_alcaldia' => $anioAlcaldia,
            'candidatos' => $resumen,
            'por_bloque' => $porBloque,
            'municipios' => $filas,
        ];
    }

    /**
     * Alcaldes elegidos en la última elección de alcaldía: los que estarán en
     * funciones en la siguiente de gobernación (los de 2023, en 2027).
     */
    public function alcaldesActuales(int $departamentoId, ?array $ideologias = null): array
    {
        $ideologias ??= PartidoIdeologia::mapa();
        $anio = (int) DB::table('historico_electoral')->where('departamento_id', $departamentoId)
            ->where('cargo', 'alcaldia')->max('anio');
        if (! $anio) {
            return ['anio' => null, 'municipios' => []];
        }

        $filas = [];
        foreach ($this->candidatos($departamentoId, 'alcaldia', $anio) as $m) {
            $lista = array_values($m['candidatos']);
            $a = $lista[0] ?? null;
            if (! $a) {
                continue;
            }
            $ideologia = $ideologias[$a['clave']] ?? self::inferir($a['partido'], $ideologias);
            $filas[] = [
                'id' => $m['id'],
                'municipio' => $m['nombre'],
                'codigo_dane' => $m['codigo_dane'],
                'potencial' => $m['potencial'],
                'candidato' => $a['candidato'],
                'partido' => $a['partido'],
                'porcentaje' => $m['total'] ? round($a['votos'] * 100 / $m['total'], 1) : null,
                'margen' => $m['total'] ? round(($a['votos'] - ($lista[1]['votos'] ?? 0)) * 100 / $m['total'], 1) : null,
                'ideologia' => $ideologia,
                'bloque' => PartidoIdeologia::bloque($ideologia),
            ];
        }
        usort($filas, fn ($x, $y) => ($y['potencial'] ?? 0) <=> ($x['potencial'] ?? 0));

        return ['anio' => $anio, 'municipios' => $filas];
    }
}
