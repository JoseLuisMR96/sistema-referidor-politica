<?php

namespace App\Console\Commands;

use App\Services\Historico\HistoricoElectoralStats;
use App\Services\Historico\ImportadorHistorico;
use App\Services\Historico\ImportadorPuestos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga el potencial electoral (cédulas habilitadas) de cada municipio desde
 * el DIVIPOL de la Registraduría ("Estructuras Básicas"): suma hombres,
 * mujeres y mesas de todos los puestos del municipio. También llena el
 * potencial de cada puesto (puestos_votacion).
 *
 *   php artisan municipios:potencial "ruta/DIVIPOL_….TXT" --anio=2026
 */
class CargarPotencialElectoralCommand extends Command
{
    protected $signature = 'municipios:potencial
        {divipol : Archivo DIVIPOL (ancho fijo)}
        {--anio=2026 : Año del censo electoral}';

    protected $description = 'Carga el potencial electoral por municipio desde el DIVIPOL de la Registraduría';

    public function handle(ImportadorHistorico $importador): int
    {
        $ruta = $this->argument('divipol');
        if (! is_file($ruta)) {
            $this->error("No existe el archivo: {$ruta}");

            return self::FAILURE;
        }

        // dep 2 · mun 3 · zona 2 · puesto 2 · depto 12 · municipio 30 · puesto 40 · indicador 1 · potencial 8 · potencial 8 · mesas 6 …
        // Ojo: el PDF dice "hombres, mujeres", pero en el archivo de 2026 la primera
        // columna es la de mujeres (da 51 % del total, como el censo electoral real).
        $suma = [];
        $f = fopen($ruta, 'r');
        while (($l = fgets($f)) !== false) {
            $l = rtrim($l, "\r\n");
            $l = mb_check_encoding($l, 'UTF-8') ? $l : mb_convert_encoding($l, 'UTF-8', 'Windows-1252');
            if (mb_strlen($l) < 114) {
                continue;
            }
            $clave = mb_substr($l, 0, 5);
            $suma[$clave] ??= ['dep' => trim(mb_substr($l, 9, 12)), 'mun' => trim(mb_substr($l, 21, 30)), 'h' => 0, 'm' => 0, 'mesas' => 0];
            $suma[$clave]['m'] += (int) mb_substr($l, 92, 8);
            $suma[$clave]['h'] += (int) mb_substr($l, 100, 8);
            $suma[$clave]['mesas'] += (int) mb_substr($l, 108, 6);
        }
        fclose($f);

        $actualizados = 0;
        $sinLugar = [];
        $total = 0;

        foreach ($suma as $s) {
            if (str_starts_with(mb_strtoupper($s['dep']), 'CONSUL')) {
                continue; // exterior
            }

            [, $munId] = $importador->resolverLugar($s['dep'], $s['mun']);
            if (! $munId) {
                $sinLugar[] = "{$s['dep']} / {$s['mun']}";

                continue;
            }

            DB::table('municipios')->where('id', $munId)->update([
                'potencial_hombres' => $s['h'],
                'potencial_mujeres' => $s['m'],
                'potencial_electoral' => $s['h'] + $s['m'],
                'mesas' => $s['mesas'],
                'potencial_anio' => (int) $this->option('anio'),
                'updated_at' => now(),
            ]);
            $actualizados++;
            $total += $s['h'] + $s['m'];
        }

        HistoricoElectoralStats::invalidarCache();

        $this->info("{$actualizados} municipios actualizados · potencial total: ".number_format($total, 0, ',', '.').' cédulas.');
        foreach ($sinLugar as $l) {
            $this->warn("  Sin reconocer: {$l}");
        }

        // El mismo archivo trae cada puesto: potencial y mesas por puesto
        $p = app(ImportadorPuestos::class)->potencialDivipol($ruta, (int) $this->option('anio'));
        $this->info(number_format($p['puestos'], 0, ',', '.').' puestos de votación con potencial.');

        return self::SUCCESS;
    }
}
