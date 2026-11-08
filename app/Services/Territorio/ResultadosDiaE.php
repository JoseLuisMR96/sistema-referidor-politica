<?php

namespace App\Services\Territorio;

use App\Models\Eleccion;
use App\Models\PuestoVotacion;
use App\Models\ReporteMesa;

/**
 * Conteo propio del día E: suma de los E-14 que reportaron los testigos.
 *
 * - Votos por candidato o lista (y blanco, nulos, no marcados).
 * - Cobertura: mesas reportadas frente a las mesas asignadas a testigos y a
 *   las mesas de los puestos cubiertos (DIVIPOL).
 * - Por municipio y por puesto.
 *
 * Es el conteo de la campaña, parcial y sin validar: el oficial es el escrutinio.
 */
class ResultadosDiaE
{
    public function calcular(Eleccion $eleccion): array
    {
        $opciones = $eleccion->opciones()->get();
        $reportes = ReporteMesa::query()->where('eleccion_id', $eleccion->id)
            ->get(['id', 'puesto_id', 'mesa', 'votos', 'blanco', 'nulos', 'no_marcados', 'total', 'foto_ruta', 'created_at', 'updated_at', 'testigo_id']);
        $testigos = $eleccion->testigos()->where('activo', true)->get(['id', 'puesto_id', 'mesas']);

        $puestoIds = $reportes->pluck('puesto_id')->merge($testigos->pluck('puesto_id'))->unique()->values();
        $puestos = PuestoVotacion::query()->with('municipio:id,nombre')->whereIn('id', $puestoIds)->get()->keyBy('id');

        $votos = array_fill_keys($opciones->pluck('id')->all(), 0);
        $especiales = ['blanco' => 0, 'nulos' => 0, 'no_marcados' => 0];
        $porPuesto = [];
        foreach ($reportes as $r) {
            foreach ($r->votos as $opcionId => $n) {
                if (isset($votos[$opcionId])) {
                    $votos[$opcionId] += (int) $n;
                }
            }
            foreach ($especiales as $k => $_) {
                $especiales[$k] += $r->{$k};
            }

            $p = $porPuesto[$r->puesto_id] ??= ['reportadas' => 0, 'votos' => array_fill_keys($opciones->pluck('id')->all(), 0), 'total' => 0];
            $p['reportadas']++;
            $p['total'] += $r->total;
            foreach ($r->votos as $opcionId => $n) {
                if (isset($p['votos'][$opcionId])) {
                    $p['votos'][$opcionId] += (int) $n;
                }
            }
            $porPuesto[$r->puesto_id] = $p;
        }

        $validos = array_sum($votos) + $especiales['blanco'];
        $asignadas = $testigos->sum(fn ($t) => count($t->listaMesas()));

        // Por puesto y por municipio
        $filasPuesto = [];
        $porMunicipio = [];
        foreach ($puestos as $id => $pu) {
            $d = $porPuesto[$id] ?? ['reportadas' => 0, 'votos' => array_fill_keys($opciones->pluck('id')->all(), 0), 'total' => 0];
            $asig = $testigos->where('puesto_id', $id)->sum(fn ($t) => count($t->listaMesas()));
            $ganador = $d['total'] ? array_search(max($d['votos']), $d['votos'], true) : null;
            $filasPuesto[] = [
                'id' => $id,
                'nombre' => $pu->nombre,
                'municipio' => $pu->municipio?->nombre,
                'mesas' => $pu->mesas,
                'asignadas' => $asig,
                'reportadas' => $d['reportadas'],
                'total' => $d['total'],
                'votos' => $d['votos'],
                'ganador' => $ganador,
            ];

            $mun = $pu->municipio?->nombre ?? '—';
            $m = $porMunicipio[$mun] ??= ['municipio' => $mun, 'reportadas' => 0, 'asignadas' => 0, 'votos' => array_fill_keys($opciones->pluck('id')->all(), 0), 'total' => 0];
            $m['reportadas'] += $d['reportadas'];
            $m['asignadas'] += $asig;
            $m['total'] += $d['total'];
            foreach ($d['votos'] as $o => $n) {
                $m['votos'][$o] += $n;
            }
            $porMunicipio[$mun] = $m;
        }
        usort($filasPuesto, fn ($a, $b) => [$b['reportadas'], $b['total']] <=> [$a['reportadas'], $a['total']]);

        return [
            'opciones' => $opciones->map(fn ($o) => [
                'id' => $o->id, 'nombre' => $o->nombre, 'partido' => $o->partido, 'propia' => $o->propia,
                'votos' => $votos[$o->id],
                'pct' => $validos ? round($votos[$o->id] * 100 / $validos, 1) : null,
            ])->sortByDesc('votos')->values()->all(),
            'especiales' => $especiales,
            'validos' => $validos,
            'total' => $validos + $especiales['nulos'] + $especiales['no_marcados'],
            'reportadas' => $reportes->count(),
            'asignadas' => $asignadas,
            'mesas_puestos' => (int) $puestos->sum('mesas'),
            'con_foto' => $reportes->whereNotNull('foto_ruta')->count(),
            'ultimo' => $reportes->max('updated_at'),
            'por_municipio' => collect($porMunicipio)->sortByDesc('total')->values()->all(),
            'por_puesto' => $filasPuesto,
            'recientes' => ReporteMesa::query()->where('eleccion_id', $eleccion->id)
                ->with(['puesto:id,nombre,municipio_id', 'testigo:id,nombre'])->latest('updated_at')->limit(10)->get(),
        ];
    }
}
