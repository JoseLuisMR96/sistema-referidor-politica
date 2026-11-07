<?php

namespace App\Console\Commands;

use App\Services\Historico\ImportadorPuestos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga los archivos de historico:exportar-puestos (por defecto, todos los de
 * database/data/puestos). Cruza los municipios por código DANE. Reimportar
 * reemplaza por año + cargo + municipio, así que se puede correr varias veces.
 *
 *   php artisan historico:cargar-puestos
 *
 * El despliegue automático lo corre con --si-cambiaron: basta con subir el
 * archivo al repo (historico:exportar-puestos) para que llegue a producción.
 */
class CargarPuestosCommand extends Command
{
    protected $signature = 'historico:cargar-puestos
        {archivos?* : Archivos .jsonl.gz (por defecto, los de database/data/puestos)}
        {--si-cambiaron : Solo los que cambiaron desde la última carga (lo usa el despliegue)}';

    protected $description = 'Carga puestos de votación y sus resultados exportados con historico:exportar-puestos';

    public function handle(ImportadorPuestos $importador): int
    {
        $archivos = $this->argument('archivos') ?: glob(base_path(ExportarPuestosCommand::CARPETA.'/*.jsonl.gz'));
        if (! $archivos) {
            $this->warn('No hay archivos para cargar.');

            return self::SUCCESS;
        }

        // Huella de cada archivo ya cargado, para no recargar en cada despliegue
        $registro = storage_path('app/puestos-cargados.json');
        $cargados = is_file($registro) ? (json_decode((string) file_get_contents($registro), true) ?: []) : [];
        if ($this->option('si-cambiaron')) {
            $archivos = array_values(array_filter($archivos, fn ($a) => ($cargados[basename($a)] ?? null) !== md5_file($a)));
            if (! $archivos) {
                $this->line('Sin cambios en los puestos.');

                return self::SUCCESS;
            }
        }

        $municipios = DB::table('municipios')->whereNotNull('codigo_dane')->pluck('id', 'codigo_dane');

        foreach ($archivos as $archivo) {
            $this->info('Cargando '.basename($archivo).'…');
            $grupos = [];   // "anio|vuelta" => filas
            $puestos = 0;
            $sinMunicipio = [];

            $gz = gzopen($archivo, 'rb');
            while (($linea = gzgets($gz)) !== false) {
                $x = json_decode($linea, true);
                $munId = $municipios[$x['dane'] ?? ''] ?? null;
                if (! $munId) {
                    $sinMunicipio[$x['dane'] ?? '?'] = true;

                    continue;
                }

                if ($x['t'] === 'p') {
                    $id = $importador->puesto($munId, $x['zona'], $x['codigo'], $x['nombre'], $x['comuna']);
                    DB::table('puestos_votacion')->where('id', $id)->update([
                        'nombre' => $x['nombre'], 'potencial' => $x['potencial'], 'potencial_mujeres' => $x['mujeres'],
                        'mesas' => $x['mesas'], 'potencial_anio' => $x['anio'], 'updated_at' => now(),
                    ]);
                    $puestos++;

                    continue;
                }

                $grupos["{$x['anio']}|{$x['vuelta']}"][] = [
                    'municipio_id' => $munId, 'zona' => $x['zona'], 'puesto' => $x['codigo'], 'nombre_puesto' => '',
                    'comuna' => null, 'cargo' => $x['cargo'], 'partido' => $x['partido'], 'votos' => (int) $x['votos'],
                ];
            }
            gzclose($gz);

            $filas = 0;
            foreach ($grupos as $clave => $lista) {
                [$anio, $vuelta] = array_map('intval', explode('|', $clave));
                $filas += $importador->guardar($lista, $anio, $vuelta)['insertadas'];
            }

            $this->info("  {$puestos} puestos y {$filas} filas de resultados.");
            $cargados[basename($archivo)] = md5_file($archivo);
            file_put_contents($registro, json_encode($cargados, JSON_PRETTY_PRINT));
            if ($sinMunicipio) {
                $this->warn('  Sin municipio para los códigos DANE: '.implode(', ', array_keys($sinMunicipio)));
            }
        }

        return self::SUCCESS;
    }
}
