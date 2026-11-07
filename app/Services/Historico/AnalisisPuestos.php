<?php

namespace App\Services\Historico;

use App\Models\PartidoIdeologia;
use App\Support\Partidos;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Análisis por puesto de votación dentro de un municipio (historico_puestos):
 * dónde es fuerte un partido, dónde puede crecer, cómo votó cada puesto y
 * señales para testigos (nulos, no marcados, participación, unanimidad).
 */
class AnalisisPuestos
{
    private function recordar(string $metodo, array $args, \Closure $calcular): mixed
    {
        $version = (int) Cache::get(HistoricoElectoralStats::CACHE_VERSION, 1);

        return Cache::rememberForever("puestos:{$version}.1:{$metodo}:".md5(serialize($args)), $calcular);
    }

    /** Municipios del departamento con datos por puesto, de más a menos puestos. */
    public function municipios(int $departamentoId): array
    {
        return $this->recordar('municipios', [$departamentoId], fn () => DB::table('historico_puestos as h')
            ->join('municipios as m', 'm.id', '=', 'h.municipio_id')
            ->where('m.departamento_id', $departamentoId)
            ->groupBy('h.municipio_id', 'm.nombre', 'm.potencial_electoral')
            ->selectRaw('h.municipio_id as id, m.nombre, m.potencial_electoral as potencial, COUNT(DISTINCT h.puesto_id) as puestos')
            ->orderByDesc('m.potencial_electoral')
            ->get()->map(fn ($r) => (array) $r)->keyBy('id')->all());
    }

    /** Cargo => años con datos por puesto en el municipio. */
    public function cargos(int $municipioId): array
    {
        return $this->recordar('cargos', [$municipioId], function () use ($municipioId) {
            $out = [];
            foreach (DB::table('historico_puestos')->where('municipio_id', $municipioId)->where('vuelta', 1)
                ->distinct()->orderBy('anio')->get(['cargo', 'anio']) as $r) {
                $out[$r->cargo][] = (int) $r->anio;
            }

            return $out;
        });
    }

    /** Votos por puesto y partido (clave), con blanco, nulos y no marcados aparte. */
    private function votos(int $municipioId, string $cargo, int $anio): array
    {
        return $this->recordar('votos', [$municipioId, $cargo, $anio], function () use ($municipioId, $cargo, $anio) {
            $filas = DB::table('historico_puestos as h')
                ->join('puestos_votacion as p', 'p.id', '=', 'h.puesto_id')
                ->where('h.municipio_id', $municipioId)->where('h.cargo', $cargo)
                ->where('h.anio', $anio)->where('h.vuelta', 1)
                ->get(['h.puesto_id', 'p.nombre', 'p.zona', 'p.codigo', 'p.comuna', 'p.potencial', 'p.potencial_anio', 'p.mesas', 'h.partido', 'h.votos']);

            $puestos = [];
            $nombres = [];
            foreach ($filas as $f) {
                $id = (int) $f->puesto_id;
                $puestos[$id] ??= [
                    'id' => $id, 'nombre' => $f->nombre, 'zona' => $f->zona, 'codigo' => $f->codigo, 'comuna' => $f->comuna,
                    'potencial' => $f->potencial !== null ? (int) $f->potencial : null,
                    'potencial_anio' => $f->potencial_anio !== null ? (int) $f->potencial_anio : null,
                    'mesas' => $f->mesas !== null ? (int) $f->mesas : null,
                    'partidos' => [], 'blanco' => 0, 'nulos' => 0, 'no_marcados' => 0,
                ];
                $v = (int) $f->votos;
                match ($f->partido) {
                    ImportadorPuestos::NULOS => $puestos[$id]['nulos'] += $v,
                    ImportadorPuestos::NO_MARCADOS => $puestos[$id]['no_marcados'] += $v,
                    default => (function () use (&$puestos, &$nombres, $id, $f, $v) {
                        $clave = Partidos::clave($f->partido);
                        if ($clave === 'VOTO EN BLANCO') {
                            $puestos[$id]['blanco'] += $v;

                            return;
                        }
                        $puestos[$id]['partidos'][$clave] = ($puestos[$id]['partidos'][$clave] ?? 0) + $v;
                        $nombres[$clave] = $f->partido;
                    })(),
                };
            }

            return ['puestos' => $puestos, 'nombres' => $nombres];
        });
    }

    /**
     * Puestos del municipio en una elección, con el partido elegido.
     *
     * @param  string|null  $clave  partido (Partidos::clave) a seguir; null = el más votado
     */
    public function puestos(int $municipioId, string $cargo, int $anio, ?string $clave = null, ?array $ideologias = null): array
    {
        $ideologias ??= PartidoIdeologia::mapa();
        $d = $this->votos($municipioId, $cargo, $anio);
        $anios = $this->cargos($municipioId)[$cargo] ?? [];
        $i = array_search($anio, $anios, true);
        $previo = $i !== false && $i > 0 ? $anios[$i - 1] : null;
        $antes = $previo ? $this->votos($municipioId, $cargo, $previo)['puestos'] : [];

        // Partidos del municipio, de más a menos votados
        $totales = [];
        foreach ($d['puestos'] as $p) {
            foreach ($p['partidos'] as $c => $v) {
                $totales[$c] = ($totales[$c] ?? 0) + $v;
            }
        }
        arsort($totales);
        $clave = $clave !== null && isset($totales[$clave]) ? $clave : array_key_first($totales);

        // El cambio solo cuenta si el partido ya se había presentado en el municipio
        $partidoAntes = $clave && collect($antes)->contains(fn ($a) => ($a['partidos'][$clave] ?? 0) > 0);

        $suma = fn ($k) => array_sum(array_column($d['puestos'], $k));
        $validosMun = array_sum($totales) + $suma('blanco');
        $totalMun = $validosMun + $suma('nulos') + $suma('no_marcados');
        $pctMun = $validosMun && $clave ? round($totales[$clave] * 100 / $validosMun, 1) : null;
        $nulosMun = $totalMun ? $suma('nulos') * 100 / $totalMun : 0;
        $noMarcMun = $totalMun ? $suma('no_marcados') * 100 / $totalMun : 0;
        $bloque = fn ($c) => PartidoIdeologia::bloque($ideologias[$c] ?? AnalisisElectoral::inferir($d['nombres'][$c] ?? $c, $ideologias));

        $filas = [];
        foreach ($d['puestos'] as $id => $p) {
            $listas = $p['partidos'];
            arsort($listas);
            $claves = array_keys($listas);
            $validos = array_sum($listas) + $p['blanco'];
            $total = $validos + $p['nulos'] + $p['no_marcados'];
            $pct = fn ($v) => $validos ? round($v * 100 / $validos, 1) : null;
            $ganador = $claves[0] ?? null;

            $pctPartido = $clave ? $pct($listas[$clave] ?? 0) : null;
            $pctAntes = null;
            if (isset($antes[$id]) && $clave && $partidoAntes) {
                $a = $antes[$id];
                $validosAntes = array_sum($a['partidos']) + $a['blanco'];
                $pctAntes = $validosAntes ? round(($a['partidos'][$clave] ?? 0) * 100 / $validosAntes, 1) : null;
            }

            // Solo con el potencial del mismo año: entre elecciones la gente cambia de puesto
            // y los códigos se reutilizan (con el de 2026, puestos de 2023 dan 800 %)
            $participacion = $p['potencial'] && $p['potencial_anio'] === $anio
                ? round($total * 100 / $p['potencial'], 1) : null;
            $nulos = $total ? round($p['nulos'] * 100 / $total, 1) : 0.0;
            $noMarcados = $total ? round($p['no_marcados'] * 100 / $total, 1) : 0.0;
            $pctGanador = $ganador ? $pct($listas[$ganador]) : null;

            $senales = [];
            if ($participacion !== null && $participacion >= 90 && $total >= 100) {
                $senales['participacion'] = "Votó el {$participacion}% del potencial";
            }
            if ($nulos >= max(2 * $nulosMun, $nulosMun + 4) && $total >= 100) {
                $senales['nulos'] = "Nulos {$nulos}% (municipio ".round($nulosMun, 1).'%)';
            }
            if ($noMarcados >= max(2 * $noMarcMun, $noMarcMun + 5) && $total >= 100) {
                $senales['no_marcados'] = "No marcados {$noMarcados}% (municipio ".round($noMarcMun, 1).'%)';
            }
            if ($pctGanador !== null && $pctGanador >= 85 && count($listas) > 1 && $total >= 100) {
                $senales['unanimidad'] = "El ganador sacó {$pctGanador}%";
            }

            $filas[] = [
                'id' => $id,
                'nombre' => $p['nombre'],
                'zona' => $p['zona'],
                'codigo' => $p['codigo'],
                'comuna' => $p['comuna'],
                'mesas' => $p['mesas'],
                'potencial' => $p['potencial'],
                'votos' => $total,
                'validos' => $validos,
                'participacion' => $participacion,
                'peso' => $totalMun ? round($total * 100 / $totalMun, 1) : null,
                'ganador' => $ganador,
                'ganador_nombre' => $ganador ? ($d['nombres'][$ganador] ?? $ganador) : null,
                'bloque' => $ganador ? $bloque($ganador) : null,
                'pct_ganador' => $pctGanador,
                'margen' => $ganador ? round($pctGanador - ($pct($listas[$claves[1] ?? ''] ?? 0) ?? 0), 1) : null,
                'votos_partido' => $clave ? ($listas[$clave] ?? 0) : null,
                'pct_partido' => $pctPartido,
                'cambio' => $pctPartido !== null && $pctAntes !== null ? round($pctPartido - $pctAntes, 1) : null,
                // Votos que le faltan para llegar a su promedio del municipio: dónde crecer
                'brecha' => $pctMun !== null && $pctPartido !== null && $pctPartido < $pctMun
                    ? (int) round(($pctMun - $pctPartido) * $validos / 100) : 0,
                'gana_partido' => $clave !== null && $ganador === $clave,
                'blanco_pct' => $pct($p['blanco']),
                'nulos_pct' => $nulos,
                'no_marcados_pct' => $noMarcados,
                'senales' => $senales,
                'nivel' => count($senales) >= 2 ? 'alto' : (count($senales) === 1 ? 'medio' : 'ninguno'),
            ];
        }

        return [
            'anio' => $anio,
            'previo' => $partidoAntes ? $previo : null,
            'hay_participacion' => collect($filas)->contains(fn ($f) => $f['participacion'] !== null),
            'anios' => $anios,
            'clave' => $clave,
            'partido_nombre' => $clave ? ($d['nombres'][$clave] ?? $clave) : null,
            'partidos' => collect($totales)->map(fn ($v, $c) => ['nombre' => $d['nombres'][$c] ?? $c, 'votos' => $v])->take(60)->all(),
            'municipio' => [
                'puestos' => count($filas),
                'mesas' => array_sum(array_map(fn ($f) => $f['mesas'] ?? 0, $filas)),
                'votos' => $totalMun,
                'potencial' => array_sum(array_map(fn ($f) => $f['potencial'] ?? 0, $filas)),
                'pct_partido' => $pctMun,
                'votos_partido' => $clave ? $totales[$clave] : null,
                'ganados_partido' => collect($filas)->where('gana_partido', true)->count(),
                'nulos_pct' => round($nulosMun, 1),
                'no_marcados_pct' => round($noMarcMun, 1),
            ],
            'conteo' => collect($filas)->countBy('nivel')->all(),
            'puestos' => $filas,
        ];
    }
}
