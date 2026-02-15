<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MunicipiosColombiaSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/seed/municipios_colombia.csv');

        if (!file_exists($path)) {
            $this->command?->error("No existe el archivo: {$path}");
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->command?->error("No se pudo abrir: {$path}");
            return;
        }

        // Lee header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->command?->error("CSV vacío o inválido.");
            return;
        }

        // Normaliza encabezados
        $header = array_map(fn($h) => $this->normHeader($h), $header);

        // Soportamos ambos estilos: nombre / nombre_municipio, departamento_id / id_departamento
        $idxId   = array_search('id', $header);
        $idxNom  = array_search('nombre', $header);
        if ($idxNom === false) $idxNom = array_search('nombre_municipio', $header);

        $idxDep  = array_search('departamento_id', $header);
        if ($idxDep === false) $idxDep = array_search('id_departamento', $header);

        if ($idxId === false || $idxNom === false || $idxDep === false) {
            fclose($handle);
            $this->command?->error("Header inválido. Debe incluir id, nombre (o nombre_municipio) y departamento_id (o id_departamento).");
            $this->command?->line("Header detectado: " . implode(', ', $header));
            return;
        }

        $count = 0;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rawId  = $row[$idxId]  ?? null;
                $rawNom = $row[$idxNom] ?? null;
                $rawDep = $row[$idxDep] ?? null;

                $id = (int) $rawId;
                $departamentoId = (int) $rawDep;

                $nombre = $this->fixEncoding((string)$rawNom);
                $nombre = trim($nombre);

                if ($id <= 0 || $departamentoId <= 0 || $nombre === '') {
                    continue;
                }

                // Slug estable: minúsculas + sin tildes (y sin caracteres raros)
                $slugBase = Str::slug(mb_strtolower($nombre));
                $slug = $slugBase . '-' . $departamentoId;

                DB::table('municipios')->updateOrInsert(
                    ['id' => $id],
                    [
                        'departamento_id' => $departamentoId,
                        'nombre' => $nombre,
                        'slug' => $slug,
                        'activo' => true,
                        'updated_at' => now(),
                    ] + (DB::table('municipios')->where('id', $id)->exists() ? [] : ['created_at' => now()])
                );

                $count++;
                // “Control de daños” en seeders grandes: libera “aire”
                if ($count % 500 === 0) {
                    $this->command?->info("Procesados: {$count}");
                }
            }

            DB::commit();
            fclose($handle);

            $this->command?->info("OK. Municipios cargados/actualizados: {$count}");
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            throw $e;
        }
    }

    private function normHeader(string $h): string
    {
        $h = $this->fixEncoding($h);
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
        $h = trim($h);
        $h = trim($h, "\"' \t\n\r\0\x0B");
        $h = mb_strtolower($h);
        $h = str_replace([' ', '-', '.'], '_', $h);
        $h = preg_replace('/_+/', '_', $h);

        return $h;
    }

    private function fixEncoding(string $s): string
    {
        $s = trim($s);

        // Corrige casos típicos de UTF-8 mal interpretado como ISO-8859-1
        // Ej: "EL PEÃ‘ON" -> "EL PEÑON"
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($converted !== false && $converted !== '') {
            $s = $converted;
        }

        // Si viene en ISO-8859-1, intenta convertir
        $try = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $s);
        if ($try !== false && $try !== '') {
            // Heurística: si arregla "Ã", "�" etc, úsalo
            if (str_contains($s, 'Ã') || str_contains($s, '�') || str_contains($s, '')) {
                $s = $try;
            }
        }

        // Limpia caracteres de control raros
        $s = preg_replace('/[[:cntrl:]]/u', '', $s);

        return $s;
    }
}
