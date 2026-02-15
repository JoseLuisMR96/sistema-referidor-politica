<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportedPeopleSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/seed/imported_people.csv');

        if (!file_exists($path)) {
            $this->command?->error("No existe el archivo: {$path}");
            return;
        }

        $batchId = 'EXCEL_' . now()->format('Ymd_His');

        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->command?->error("No se pudo abrir el archivo: {$path}");
            return;
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            $this->command?->error("El archivo está vacío.");
            fclose($handle);
            return;
        }

        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);

        $rows = [];
        $line = 0;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            if ($line === 1 && $this->looksLikeHeader($data)) {
                continue;
            }

            $fullName = $data[0] ?? null;
            $phone    = $data[1] ?? null;
            $doc      = $data[2] ?? null;
            $place    = $data[3] ?? null;

            $fullName = $this->cleanText($fullName);
            $place    = $this->cleanText($place);

            $doc = $this->digitsOnly($doc);
            if (!$doc) {
                continue; 
            }

            $phone = $this->normalizePhone($phone);

            $rows[] = [
                'full_name' => $fullName ?: 'SIN NOMBRE',
                'phone' => $phone,
                'document_number' => $doc,
                'voting_place' => $place,
                'batch_id' => $batchId,
                'created_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($rows) >= 500) {
                $this->upsertChunk($rows);
                $rows = [];
            }
        }

        fclose($handle);

        if (!empty($rows)) {
            $this->upsertChunk($rows);
        }

        $this->command?->info("Seeder OK. Batch: {$batchId}");
    }

    private function upsertChunk(array $rows): void
    {
        DB::table('imported_people')->upsert(
            $rows,
            ['document_number'],
            ['full_name', 'phone', 'voting_place', 'batch_id', 'updated_at']
        );
    }

    private function detectDelimiter(string $line): string
    {
        $commas = substr_count($line, ',');
        $semis  = substr_count($line, ';');

        return $semis > $commas ? ';' : ',';
    }

    private function looksLikeHeader(array $row): bool
    {
        $joined = Str::lower(implode(' ', $row));
        return Str::contains($joined, ['nombre', 'cedula', 'cédula', 'telefono', 'teléfono', 'puesto', 'votacion', 'votación']);
    }

    private function digitsOnly(?string $value): ?string
    {
        if ($value === null) return null;
        $value = trim($value);
        if ($value === '') return null;

        $digits = preg_replace('/\D+/', '', $value);
        return $digits !== '' ? $digits : null;
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) return null;

        $value = trim($value);
        if ($value === '') return null;

        $value = str_replace(["\xEF\xBB\xBF", "\u{FEFF}"], '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return $value;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) return null;

        $phone = trim($phone);
        if ($phone === '' || Str::contains(Str::lower($phone), 'no indic')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits || strlen($digits) < 7) return null;

        return $digits;
    }
}
