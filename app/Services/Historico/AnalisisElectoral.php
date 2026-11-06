<?php

namespace App\Services\Historico;

use App\Support\Partidos;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cálculos del "Análisis electoral" (sobre historico_electoral).
 *
 * - Fortines: dónde saca más votos cada partido y cómo cambió entre elecciones.
 * - Municipios: tamaño (potencial electoral), votos y participación.
 *
 * Los partidos se siguen entre años por Partidos::clave(). Solo primera vuelta.
 * Comparte la versión de caché del Histórico: se renueva al importar.
 */
class AnalisisElectoral
{
    private function recordar(string $metodo, array $args, \Closure $calcular): mixed
    {
        $version = (int) Cache::get(HistoricoElectoralStats::CACHE_VERSION, 1);

        return Cache::rememberForever("analisis:{$version}:{$metodo}:".md5(serialize($args)), $calcular);
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
        $ultimo = end($anios) ?: null;
        $previo = count($anios) > 1 ? $anios[count($anios) - 2] : null;

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
            ];
        }

        return [
            'anios' => $anios,
            'ultimo' => $ultimo,
            'previo' => $previo,
            'partido' => $d['partidos'][$clave] ?? null,
            'departamento' => $departamento,
            'municipios' => $filas,
        ];
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
}
