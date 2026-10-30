<?php

namespace App\Console\Commands;

use App\Services\Historico\ImportadorHistorico;
use Illuminate\Console\Command;

/**
 * Importa el escrutinio "mesa a mesa" con códigos (formato Registraduría 2026,
 * "Estructuras Básicas"): MMV_9999.csv trae solo códigos separados por ";" y
 * los nombres salen de los archivos básicos (DIVIPOL, PARTIDOS, CANDIDATOS,
 * CORPORACION).
 *
 *   php artisan historico:importar-escrutinio "ruta/mmvESCRUTINIOCongreso2026.zip" "ruta/ArchivosBasicos..." --anio=2026
 *
 * - Lee el CSV directo del zip (descomprimido son ~10 GB) y suma por municipio.
 * - Presidencia (con --vuelta), Senado y Cámara, en circunscripción nacional (0) y departamental (1):
 *   fuera indígenas, afro, CITREP, consultas y consulados.
 * - Partido 0000 con candidato 996/997/998 = blanco / nulos / no marcados.
 * - De CANDIDATOS solo se usan nombre y apellido: la cédula no se lee.
 */
class ImportarEscrutinioMmvCommand extends Command
{
    protected $signature = 'historico:importar-escrutinio
        {mmv : MMV_9999.csv o el zip que lo contiene}
        {basicos : Carpeta con los archivos básicos (DIVIPOL, PARTIDOS, CANDIDATOS, CORPORACION)}
        {--anio=2026 : Año de la elección}
        {--vuelta=1 : Vuelta (presidencia: 1 o 2)}
        {--sin-precalcular : No llenar la caché al final}';

    protected $description = 'Importa el escrutinio mesa a mesa con códigos (formato Registraduría 2026)';

    private const CIRCUNSCRIPCIONES = ['0', '1'];

    public function handle(ImportadorHistorico $importador): int
    {
        $anio = (int) $this->option('anio');
        $basicos = rtrim($this->argument('basicos'), '/\\');

        $corporaciones = $this->corporaciones($basicos);
        $lugares = $this->divipol($basicos);
        $partidos = $this->partidos($basicos);
        $candidatos = $this->candidatos($basicos);

        if (! $corporaciones || ! $lugares || ! $partidos) {
            $this->error('No encontré los archivos básicos (CORPORACION, DIVIPOL, PARTIDOS) en '.$basicos);

            return self::FAILURE;
        }

        $f = $this->abrir($this->argument('mmv'));
        if (! $f) {
            $this->error('No pude abrir '.$this->argument('mmv'));

            return self::FAILURE;
        }

        $this->info('Leyendo y sumando por municipio…');
        $inicio = microtime(true);
        $suma = [];   // dep => ["mun|corp|circ|partido|cand" => votos]
        $lineas = 0;

        while (($linea = fgets($f)) !== false) {
            $lineas++;
            $c = explode(';', rtrim($linea, "\r\n;"));

            // 0 9999 · 1 dep · 2 mun · 3 zona · 4 puesto · 5 mesa · 6 comuna · 7 corp · 8 circ · 9 partido · 10 cand · 11 votos
            if (count($c) < 12 || ! isset($corporaciones[$c[7]]) || ! in_array($c[8], self::CIRCUNSCRIPCIONES, true)) {
                continue;
            }

            $votos = (int) $c[11];
            if ($votos === 0) {
                continue;
            }

            $clave = "{$c[2]}|{$c[7]}|{$c[8]}|{$c[9]}|{$c[10]}";
            $suma[$c[1]][$clave] = ($suma[$c[1]][$clave] ?? 0) + $votos;

            if ($lineas % 20_000_000 === 0) {
                $this->line(sprintf('  %s líneas (%.0f s)', number_format($lineas, 0, ',', '.'), microtime(true) - $inicio));
            }
        }
        fclose($f);

        $this->info(sprintf('%s líneas leídas en %.0f s. Guardando por departamento…', number_format($lineas, 0, ',', '.'), microtime(true) - $inicio));

        $total = ['insertadas' => 0, 'reemplazadas' => 0, 'omitidas' => 0, 'sin_lugar' => [], 'errores' => []];

        $nombresDep = [];
        foreach ($lugares as $k => $l) {
            $nombresDep[strtok($k, '|')] ??= $l[0];
        }

        foreach ($suma as $dep => $claves) {
            $nombreDep = $nombresDep[$dep] ?? (string) $dep;

            // Votos en el exterior: sin municipio ni mapa
            if (str_starts_with(mb_strtoupper($nombreDep), 'CONSUL')) {
                continue;
            }

            $filas = (function () use ($dep, $claves, $lugares, $corporaciones, $partidos, $candidatos) {
                foreach ($claves as $clave => $votos) {
                    [$mun, $corp, $circ, $partido, $cand] = explode('|', $clave);
                    [$depNombre, $munNombre] = $lugares["{$dep}|{$mun}"] ?? ['', ''];
                    $codPartido = str_pad($partido, 5, '0', STR_PAD_LEFT);

                    if ((int) $partido === 0) {
                        // 996 blanco, 997 nulos, 998 no marcados (el importador omite los dos últimos)
                        $nombrePartido = match ((int) $cand) {
                            996 => 'VOTO EN BLANCO',
                            997 => 'VOTOS NULOS',
                            default => 'VOTOS NO MARCADOS',
                        };
                        $nombreCandidato = '';
                    } else {
                        $nombrePartido = $partidos[$codPartido] ?? "PARTIDO {$codPartido}";
                        $nombreCandidato = (int) $cand === 0 ? '' : ($candidatos["{$corp}|{$circ}|{$dep}|{$codPartido}|{$cand}"]
                            ?? $candidatos["{$corp}|{$circ}|00|{$codPartido}|{$cand}"]
                            ?? "CANDIDATO {$cand}");
                    }

                    yield [
                        'anio' => '',
                        'cargo' => $corporaciones[$corp],
                        'departamento' => $depNombre,
                        'municipio' => $munNombre,
                        'partido' => $nombrePartido,
                        'candidato' => $nombreCandidato,
                        'codigo_candidato' => (int) $cand === 0 ? '000' : $cand,
                        'votos' => (string) $votos,
                    ];
                }
            })();

            $total = $importador->guardar($filas, $anio, null, $total, (int) $this->option('vuelta'));
            $this->line(sprintf('  %-20s %s filas', $nombreDep, number_format($total['insertadas'], 0, ',', '.')));
        }

        $this->newLine();
        $this->info(number_format($total['insertadas'], 0, ',', '.').' filas importadas, '
            .number_format($total['reemplazadas'], 0, ',', '.').' reemplazadas, '
            .number_format($total['omitidas'], 0, ',', '.').' omitidas (nulos, no marcados, exterior).');

        foreach (array_slice($total['sin_lugar'], 0, 30, true) as $lugar => $n) {
            $this->warn("  Sin reconocer: {$lugar} ({$n})");
        }
        foreach (array_slice($total['errores'], 0, 10) as $e) {
            $this->error($e);
        }

        if ($total['insertadas'] && ! $this->option('sin-precalcular')) {
            $this->call('historico:precalcular');
        }

        return self::SUCCESS;
    }

    /** @return resource|false */
    private function abrir(string $ruta)
    {
        if (str_ends_with(strtolower($ruta), '.zip')) {
            $zip = new \ZipArchive;
            if ($zip->open($ruta) !== true) {
                return false;
            }
            $interno = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombre = $zip->getNameIndex($i);
                if (preg_match('/MMV_\d+\.csv$/i', $nombre)) {
                    $interno = $nombre;
                }
            }
            $zip->close();

            return $interno ? fopen('zip://'.realpath($ruta).'#'.$interno, 'r') : false;
        }

        return fopen($ruta, 'r');
    }

    private function archivo(string $carpeta, string $prefijo): ?string
    {
        return collect(glob($carpeta.DIRECTORY_SEPARATOR.$prefijo.'*.TXT') ?: glob($carpeta.DIRECTORY_SEPARATOR.$prefijo.'*.txt') ?: [])->first();
    }

    /** Líneas en UTF-8 (los básicos vienen en Latin-1). */
    private function lineas(?string $ruta): \Generator
    {
        if (! $ruta || ! is_file($ruta)) {
            return;
        }
        $f = fopen($ruta, 'r');
        while (($l = fgets($f)) !== false) {
            $l = rtrim($l, "\r\n");
            yield mb_check_encoding($l, 'UTF-8') ? $l : mb_convert_encoding($l, 'UTF-8', 'Windows-1252');
        }
        fclose($f);
    }

    /** código (3) => cargo; solo Presidencia, Senado y Cámara. */
    private function corporaciones(string $carpeta): array
    {
        $out = [];
        foreach ($this->lineas($this->archivo($carpeta, 'CORPORACION')) as $l) {
            $nombre = trim(substr($l, 3));
            if (str_contains($nombre, 'PRESIDEN')) {
                $out[substr($l, 0, 3)] = 'presidencia';
            } elseif (str_contains($nombre, 'SENADO')) {
                $out[substr($l, 0, 3)] = 'senado';
            } elseif (str_contains($nombre, 'CAMARA') || str_contains($nombre, 'CÁMARA')) {
                $out[substr($l, 0, 3)] = 'camara';
            }
        }

        return $out;
    }

    /** "dep|mun" => [departamento, municipio] (posiciones fijas del DIVIPOL). */
    private function divipol(string $carpeta): array
    {
        $out = [];
        foreach ($this->lineas($this->archivo($carpeta, 'DIVIPOL')) as $l) {
            $clave = mb_substr($l, 0, 2).'|'.mb_substr($l, 2, 3);
            $out[$clave] ??= [trim(mb_substr($l, 9, 12)), trim(mb_substr($l, 21, 30))];
        }

        return $out;
    }

    /** código (5) => nombre. */
    private function partidos(string $carpeta): array
    {
        $out = [];
        foreach ($this->lineas($this->archivo($carpeta, 'PARTIDOS')) as $l) {
            $out[mb_substr($l, 0, 5)] = trim(mb_substr($l, 5, 200));
        }

        return $out;
    }

    /** "corp|circ|dep|partido|cand" => "Nombre Apellido". Nunca se lee la cédula. */
    private function candidatos(string $carpeta): array
    {
        $out = [];
        foreach ($this->lineas($this->archivo($carpeta, 'CANDIDATOS')) as $l) {
            // corp 3 · circ 1 · dep 2 · mun 3 · comuna 2 · partido 5 · cand 3 · pref 1 · nombre 50 · apellido 50 · (cédula 15: no se usa)
            $clave = mb_substr($l, 0, 3).'|'.mb_substr($l, 3, 1).'|'.mb_substr($l, 4, 2).'|'.mb_substr($l, 11, 5).'|'.mb_substr($l, 16, 3);
            $nombre = trim(trim(mb_substr($l, 20, 50)).' '.trim(mb_substr($l, 70, 50)));
            if ($nombre !== '') {
                $out[$clave] = $nombre;
            }
        }

        return $out;
    }
}
