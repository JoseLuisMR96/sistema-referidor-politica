<?php

namespace App\Console\Commands;

use App\Services\Historico\HistoricoElectoralStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Exporta los puestos de votación y sus resultados de uno o varios
 * departamentos a un archivo pequeño (JSON por líneas, comprimido), para
 * llevarlos a producción sin los "mesa a mesa" de 3 GB. Los municipios van
 * por código DANE, no por id.
 *
 *   php artisan historico:exportar-puestos --depto=META
 *
 * Se carga con historico:cargar-puestos.
 */
class ExportarPuestosCommand extends Command
{
    public const CARPETA = 'database/data/puestos';

    protected $signature = 'historico:exportar-puestos
        {--depto=* : Departamentos (nombre)}
        {--archivo= : Ruta de salida (por defecto database/data/puestos/<DEPTO>.jsonl.gz)}';

    protected $description = 'Exporta puestos de votación y sus resultados para cargarlos en otro servidor';

    public function handle(): int
    {
        $deptos = (array) $this->option('depto');
        if (! $deptos) {
            $this->error('Indica al menos un --depto.');

            return self::FAILURE;
        }

        $ids = DB::table('departamentos')->get(['id', 'nombre'])
            ->filter(fn ($d) => in_array(HistoricoElectoralStats::normalizar($d->nombre), array_map(fn ($x) => HistoricoElectoralStats::normalizar($x), $deptos), true))
            ->pluck('id');
        if ($ids->isEmpty()) {
            $this->error('No encontré esos departamentos.');

            return self::FAILURE;
        }

        $ruta = $this->option('archivo') ?: base_path(self::CARPETA.'/'.HistoricoElectoralStats::normalizar(implode('-', $deptos)).'.jsonl.gz');
        @mkdir(dirname($ruta), 0775, true);
        $gz = gzopen($ruta, 'wb9');

        $puestos = DB::table('puestos_votacion as p')->join('municipios as m', 'm.id', '=', 'p.municipio_id')
            ->whereIn('m.departamento_id', $ids)->whereNotNull('m.codigo_dane')
            ->get(['p.id', 'm.codigo_dane', 'p.zona', 'p.codigo', 'p.nombre', 'p.comuna', 'p.potencial', 'p.potencial_mujeres', 'p.mesas', 'p.potencial_anio']);
        foreach ($puestos as $p) {
            gzwrite($gz, json_encode(['t' => 'p', 'dane' => $p->codigo_dane, 'zona' => $p->zona, 'codigo' => $p->codigo, 'nombre' => $p->nombre,
                'comuna' => $p->comuna, 'potencial' => $p->potencial, 'mujeres' => $p->potencial_mujeres, 'mesas' => $p->mesas,
                'anio' => $p->potencial_anio], JSON_UNESCAPED_UNICODE)."\n");
        }

        $filas = 0;
        DB::table('historico_puestos as h')->join('puestos_votacion as p', 'p.id', '=', 'h.puesto_id')
            ->join('municipios as m', 'm.id', '=', 'h.municipio_id')
            ->whereIn('m.departamento_id', $ids)->whereNotNull('m.codigo_dane')
            ->orderBy('h.id')
            ->select(['h.id', 'h.anio', 'h.cargo', 'h.vuelta', 'm.codigo_dane', 'p.zona', 'p.codigo', 'h.partido', 'h.votos'])
            ->chunk(5000, function ($lote) use ($gz, &$filas) {
                foreach ($lote as $h) {
                    gzwrite($gz, json_encode(['t' => 'v', 'anio' => $h->anio, 'cargo' => $h->cargo, 'vuelta' => $h->vuelta, 'dane' => $h->codigo_dane,
                        'zona' => $h->zona, 'codigo' => $h->codigo, 'partido' => $h->partido, 'votos' => $h->votos], JSON_UNESCAPED_UNICODE)."\n");
                    $filas++;
                }
            });
        gzclose($gz);

        $this->info(sprintf('%s puestos y %s filas de resultados en %s (%s KB).', number_format($puestos->count(), 0, ',', '.'),
            number_format($filas, 0, ',', '.'), $ruta, number_format(filesize($ruta) / 1024, 0, ',', '.')));

        return self::SUCCESS;
    }
}
