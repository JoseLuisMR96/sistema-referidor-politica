<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Completa el catálogo de municipios con el código DANE de los mapas.
 *
 * database/data/municipios_dane.csv se generó cruzando los GeoJSON de
 * public/data/deptos (que traen COD_MPIO) con municipios_colombia.csv.
 * Con el código, el mapa pinta cada municipio sin depender del nombre.
 *
 * - Actualiza por id + departamento (si no coinciden, no toca la fila).
 * - Corrige nombres dañados por codificación ("NARIÃ‘O", "GsICAN").
 * - Agrega los municipios que faltan (filas sin id), solo si el catálogo ya
 *   está cargado y existe su departamento.
 */
class CatalogoDane
{
    public static function aplicar(): int
    {
        $ruta = database_path('data/municipios_dane.csv');

        if (! is_file($ruta) || ! DB::table('municipios')->exists()) {
            return 0;
        }

        $f = fopen($ruta, 'r');
        fgetcsv($f); // encabezado: id, departamento_id, codigo_dane, nombre
        $cambios = 0;

        while (($fila = fgetcsv($f)) !== false) {
            [$id, $depId, $codigo, $nombre] = $fila + [null, null, null, null];

            if ($id === '' || $id === null) {
                $cambios += self::agregar((int) $depId, $codigo, $nombre);

                continue;
            }

            $actual = DB::table('municipios')
                ->where('id', (int) $id)
                ->where('departamento_id', (int) $depId)
                ->first(['nombre', 'codigo_dane']);

            if (! $actual) {
                continue;
            }

            $datos = [];

            if ($actual->codigo_dane !== $codigo) {
                $datos['codigo_dane'] = $codigo;
            }

            if (self::nombreDanado($actual->nombre)) {
                $datos['nombre'] = $nombre;
            }

            if ($datos) {
                DB::table('municipios')->where('id', (int) $id)->update($datos + ['updated_at' => now()]);
                $cambios++;
            }
        }

        fclose($f);

        return $cambios;
    }

    public static function nombreDanado(string $nombre): bool
    {
        return str_contains($nombre, 'Ã')
            || str_contains($nombre, '�')
            || preg_match('/[A-Z]s[A-Z]/', $nombre) === 1; // "GsICAN" = GÜICÁN
    }

    private static function agregar(int $depId, string $codigo, string $nombre): int
    {
        if (DB::table('municipios')->where('codigo_dane', $codigo)->exists()
            || ! DB::table('departamentos')->where('id', $depId)->exists()) {
            return 0;
        }

        DB::table('municipios')->insert([
            'codigo_dane' => $codigo,
            'departamento_id' => $depId,
            'nombre' => $nombre,
            'slug' => Str::slug(mb_strtolower($nombre)).'-'.$depId,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 1;
    }
}
