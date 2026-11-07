<?php

namespace App\Console\Commands;

use App\Services\Historico\HistoricoElectoralStats;
use App\Services\Historico\ImportadorHistorico;
use App\Services\Historico\ImportadorPuestos;
use Illuminate\Console\Command;

/**
 * Importa los resultados por puesto de votación desde el "mesa a mesa"
 * territorial de la Registraduría (MMV_2019_NACIONAL.csv, MMV_2023_NACIONAL.csv):
 * suma las mesas de cada puesto por partido.
 *
 *   php -d memory_limit=4G artisan historico:importar-puestos MMV_2023_NACIONAL.csv --anio=2023 --depto=META
 *
 * Sin --depto carga todo el país (~800.000 filas por año). Solo circunscripciones
 * ordinarias (1 departamental, 2 municipal, 3 comuna).
 */
class ImportarPuestosCommand extends Command
{
    protected $signature = 'historico:importar-puestos
        {archivo : CSV mesa a mesa con nombres (Código Zona, Código Puesto, Nombre Puesto…)}
        {--anio= : Año de la elección}
        {--depto=* : Solo estos departamentos (nombre, se puede repetir)}
        {--vuelta=1 : Vuelta (presidencia)}';

    protected $description = 'Importa resultados por puesto de votación desde el mesa a mesa de la Registraduría';

    private const CIRCUNSCRIPCIONES = ['1', '2', '3'];

    private const COLUMNAS = [
        'departamento' => 'NOMBRE DEPARTAMENTO',
        'municipio' => 'NOMBRE MUNICIPIO',
        'zona' => 'CODIGO ZONA',
        'puesto' => 'CODIGO PUESTO',
        'nombre_puesto' => 'NOMBRE PUESTO',
        'comuna' => 'NOMBRE COMUNA',
        'cargo' => 'NOMBRE CORPORACION',
        'circunscripcion' => 'CODIGO CIRCUNSCRIPCION',
        'partido' => 'NOMBRE PARTIDO',
        'codigo_candidato' => 'CODIGO CANDIDATO',
        'votos' => 'TOTAL VOTOS',
    ];

    public function handle(ImportadorPuestos $puestos, ImportadorHistorico $historico): int
    {
        $ruta = $this->argument('archivo');
        $anio = (int) $this->option('anio');
        if (! is_file($ruta) || $anio < 1990) {
            $this->error('Falta el archivo o --anio.');

            return self::FAILURE;
        }

        $f = fopen($ruta, 'r');
        if (fread($f, 3) !== "\xEF\xBB\xBF") {
            rewind($f);
        }
        $encabezado = array_map(fn ($t) => HistoricoElectoralStats::normalizar((string) $t), fgetcsv($f, 0, ',', '"', '') ?: []);
        $idx = [];
        foreach (self::COLUMNAS as $col => $titulo) {
            $i = array_search($titulo, $encabezado, true);
            if ($i === false) {
                $this->error("Falta la columna \"{$titulo}\".");

                return self::FAILURE;
            }
            $idx[$col] = $i;
        }

        $soloDeptos = array_map(fn ($d) => HistoricoElectoralStats::normalizar($d), (array) $this->option('depto'));
        $suma = [];     // dep|mun|zona|puesto|cargo|partido => votos
        $info = [];     // dep|mun|zona|puesto => [nombre, comuna]
        $cargos = [];
        $deptoOk = [];
        $lineas = 0;
        $inicio = microtime(true);
        $this->info('Leyendo y sumando por puesto…');

        while (($c = fgetcsv($f, 0, ',', '"', '')) !== false) {
            $lineas++;
            if (count($c) < count(self::COLUMNAS) || ! in_array($c[$idx['circunscripcion']], self::CIRCUNSCRIPCIONES, true)) {
                continue;
            }

            $dep = $c[$idx['departamento']];
            if ($soloDeptos && ! ($deptoOk[$dep] ??= in_array(HistoricoElectoralStats::normalizar($dep), $soloDeptos, true))) {
                continue;
            }
            if (str_starts_with(mb_strtoupper($dep), 'CONSUL')) {
                continue;
            }

            $votos = (int) $c[$idx['votos']];
            if ($votos === 0) {
                continue;
            }
            $cargo = $cargos[$c[$idx['cargo']]] ??= ($historico->cargo($c[$idx['cargo']]) ?? '');
            if ($cargo === '') {
                continue;
            }

            $lugar = "{$dep}|{$c[$idx['municipio']]}|{$c[$idx['zona']]}|{$c[$idx['puesto']]}";
            $info[$lugar] ??= [$c[$idx['nombre_puesto']], ImportadorPuestos::comuna($c[$idx['comuna']])];
            $partido = ImportadorPuestos::partido($c[$idx['partido']], $c[$idx['codigo_candidato']]);
            $clave = "{$lugar}|{$cargo}|{$partido}";
            $suma[$clave] = ($suma[$clave] ?? 0) + $votos;

            if ($lineas % 5_000_000 === 0) {
                $this->line(sprintf('  %s líneas (%.0f s)', number_format($lineas, 0, ',', '.'), microtime(true) - $inicio));
            }
        }
        fclose($f);

        $this->info(sprintf('%s líneas en %.0f s; %s puestos. Guardando…', number_format($lineas, 0, ',', '.'),
            microtime(true) - $inicio, number_format(count($info), 0, ',', '.')));

        $filas = (function () use ($suma, $info) {
            foreach ($suma as $clave => $votos) {
                [$dep, $mun, $zona, $puesto, $cargo, $partido] = explode('|', $clave, 6);
                [$nombre, $comuna] = $info["{$dep}|{$mun}|{$zona}|{$puesto}"];
                yield compact('cargo', 'partido', 'votos', 'zona', 'puesto', 'comuna') + [
                    'departamento' => $dep, 'municipio' => $mun, 'nombre_puesto' => $nombre,
                ];
            }
        })();

        $r = $puestos->guardar($filas, $anio, (int) $this->option('vuelta'));

        $this->info(number_format($r['insertadas'], 0, ',', '.').' filas en '.number_format($r['puestos'], 0, ',', '.')
            .' puestos ('.number_format($r['reemplazadas'], 0, ',', '.').' reemplazadas).');
        foreach (array_slice($r['sin_lugar'], 0, 20, true) as $lugar => $n) {
            $this->warn("  Sin reconocer: {$lugar} ({$n})");
        }

        return self::SUCCESS;
    }
}
