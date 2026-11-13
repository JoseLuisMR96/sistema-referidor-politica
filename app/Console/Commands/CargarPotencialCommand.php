<?php

namespace App\Console\Commands;

use App\Services\Historico\HistoricoElectoralStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga database/data/potencial_municipios.csv (de municipios:exportar-potencial)
 * en los municipios, cruzando por código DANE.
 *
 *   php artisan municipios:cargar-potencial
 *
 * El despliegue automático lo corre con --si-cambio: solo recarga si el archivo
 * cambió (huella en storage/app/potencial-cargado.json).
 */
class CargarPotencialCommand extends Command
{
    protected $signature = 'municipios:cargar-potencial
        {--si-cambio : Solo si el archivo cambió desde la última carga (lo usa el despliegue)}';

    protected $description = 'Carga el potencial electoral por municipio exportado al repo';

    public function handle(): int
    {
        $archivo = base_path(ExportarPotencialCommand::ARCHIVO);
        if (! is_file($archivo)) {
            $this->line('No hay archivo de potencial en el repo.');

            return self::SUCCESS;
        }

        $registro = storage_path('app/potencial-cargado.json');
        $huella = md5_file($archivo);
        if ($this->option('si-cambio') && is_file($registro)
            && (json_decode((string) file_get_contents($registro), true)['huella'] ?? null) === $huella) {
            $this->line('Sin cambios en el potencial.');

            return self::SUCCESS;
        }

        $municipios = DB::table('municipios')->whereNotNull('codigo_dane')->pluck('id', 'codigo_dane');
        $f = fopen($archivo, 'rb');
        $encabezado = str_getcsv(trim((string) fgets($f)), ';');
        if ($encabezado !== ExportarPotencialCommand::COLUMNAS) {
            fclose($f);
            $this->error('El archivo no tiene las columnas esperadas: '.implode(';', ExportarPotencialCommand::COLUMNAS));

            return self::FAILURE;
        }

        $actualizados = 0;
        $sinMunicipio = [];
        DB::transaction(function () use ($f, $municipios, &$actualizados, &$sinMunicipio) {
            while (($linea = fgets($f)) !== false) {
                if (trim($linea) === '') {
                    continue;
                }
                $x = array_combine(ExportarPotencialCommand::COLUMNAS, str_getcsv(trim($linea), ';'));
                $id = $municipios[$x['codigo_dane']] ?? null;
                if (! $id) {
                    $sinMunicipio[] = $x['codigo_dane'];

                    continue;
                }

                $n = fn ($v) => $v === '' ? null : (int) $v;
                DB::table('municipios')->where('id', $id)->update([
                    'potencial_electoral' => $n($x['potencial_electoral']),
                    'potencial_mujeres' => $n($x['potencial_mujeres']),
                    'potencial_hombres' => $n($x['potencial_hombres']),
                    'mesas' => $n($x['mesas']),
                    'potencial_anio' => $n($x['potencial_anio']),
                    'updated_at' => now(),
                ]);
                $actualizados++;
            }
        });
        fclose($f);

        // El Análisis y el Histórico guardan el potencial en su caché
        HistoricoElectoralStats::invalidarCache();
        file_put_contents($registro, json_encode(['huella' => $huella, 'fecha' => now()->toIso8601String()]));

        $this->info("{$actualizados} municipios con potencial electoral.");
        if ($sinMunicipio) {
            $this->warn('Códigos DANE sin municipio: '.implode(', ', array_slice($sinMunicipio, 0, 20)));
        }

        return self::SUCCESS;
    }
}
