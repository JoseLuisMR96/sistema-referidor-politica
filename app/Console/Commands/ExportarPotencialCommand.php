<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Exporta el potencial electoral de cada municipio (lo que cargó
 * municipios:potencial desde el DIVIPOL) a database/data/potencial_municipios.csv.
 *
 *   php artisan municipios:exportar-potencial
 *
 * El DIVIPOL no está en el servidor: se carga en local, se exporta y se sube el
 * CSV al repo. El despliegue lo carga con municipios:cargar-potencial. Son
 * cifras públicas de la Registraduría por municipio, sin datos personales.
 */
class ExportarPotencialCommand extends Command
{
    public const ARCHIVO = 'database/data/potencial_municipios.csv';

    public const COLUMNAS = ['codigo_dane', 'potencial_electoral', 'potencial_mujeres', 'potencial_hombres', 'mesas', 'potencial_anio'];

    protected $signature = 'municipios:exportar-potencial';

    protected $description = 'Exporta el potencial electoral por municipio al repo (para cargarlo en producción)';

    public function handle(): int
    {
        $filas = DB::table('municipios')
            ->whereNotNull('codigo_dane')
            ->whereNotNull('potencial_electoral')
            ->orderBy('codigo_dane')
            ->get(self::COLUMNAS);

        if ($filas->isEmpty()) {
            $this->error('No hay potencial cargado: corre primero municipios:potencial con el DIVIPOL.');

            return self::FAILURE;
        }

        $f = fopen(base_path(self::ARCHIVO), 'wb');
        fwrite($f, implode(';', self::COLUMNAS)."\n");
        foreach ($filas as $m) {
            fwrite($f, implode(';', array_map(fn ($c) => $m->{$c}, self::COLUMNAS))."\n");
        }
        fclose($f);

        $this->info("{$filas->count()} municipios exportados a ".self::ARCHIVO.'. Súbelo al repo para que llegue a producción.');

        return self::SUCCESS;
    }
}
