<?php

namespace Database\Seeders;

use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\Referrer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga a nombre de un referidor que ya existe las personas que él recogió
 * en planilla de papel (con la autorización firmada, como el formulario).
 *
 * Los datos NO van en el repositorio: se leen de
 * storage/app/seed/referidos-{CODIGO}.csv (separado por ;), con las columnas
 * nombre;cedula;telefono;dane_votacion. Puesto, dirección y mesa no se cargan:
 * el registro no tiene dónde guardarlos.
 *
 *   php artisan db:seed --class=ReferidosPlanillaSeeder            (TD2DA7MO)
 *   SEED_REF=OTRO php artisan db:seed --class=ReferidosPlanillaSeeder
 *
 * Se puede repetir: salta las cédulas que el candidato ya tiene.
 */
class ReferidosPlanillaSeeder extends Seeder
{
    public const CODIGO = 'TD2DA7MO';

    public function run(): void
    {
        $codigo = strtoupper((string) (env('SEED_REF') ?: self::CODIGO));
        $ruta = storage_path("app/seed/referidos-{$codigo}.csv");

        if (! is_file($ruta)) {
            $this->command?->error("No existe el archivo: {$ruta}");

            return;
        }

        // Sin usuario no hay contexto de tenant: se busca sin el scope y el
        // tenant_id del registro es el del referidor, explícito.
        $referidor = Referrer::withoutGlobalScope('tenant')->with('tenant:id,nombre')->where('code', $codigo)->first();
        if (! $referidor) {
            $this->command?->error("No existe el referidor con código {$codigo}.");

            return;
        }
        $this->command?->info("Referidor: {$referidor->name} · candidato: ".($referidor->tenant?->nombre ?? '¿sin candidato?'));

        $municipios = Municipio::query()->whereNotNull('codigo_dane')->pluck('id', 'codigo_dane');
        $filas = $this->leer($ruta);

        $creados = $repetidos = 0;
        $avisos = [];

        DB::transaction(function () use ($filas, $referidor, $codigo, $municipios, &$creados, &$repetidos, &$avisos) {
            foreach ($filas as $n => $f) {
                $cedula = preg_replace('/\D+/', '', $f['cedula']);
                if ($f['nombre'] === '' || $cedula === '') {
                    $avisos[] = "Fila {$n}: sin nombre o sin cédula, no se carga.";

                    continue;
                }

                $existe = PublicRegistration::withoutGlobalScope('tenant')
                    ->where('tenant_id', $referidor->tenant_id)
                    ->where('document_type', 'CC')->where('document_number', $cedula)
                    ->exists();
                if ($existe) {
                    $repetidos++;

                    continue;
                }

                $municipioId = null;
                if ($f['dane'] !== '') {
                    $municipioId = $municipios[$f['dane']] ?? null;
                    if (! $municipioId) {
                        $avisos[] = "Fila {$n}: el código DANE {$f['dane']} no está en el catálogo; queda sin municipio.";
                    }
                }

                PublicRegistration::create([
                    'tenant_id' => $referidor->tenant_id,
                    'full_name' => mb_substr($f['nombre'], 0, 150),
                    'document_type' => 'CC',
                    'document_number' => $cedula,
                    'age' => null,
                    'gender' => 'NR', // la planilla no lo trae y la columna no admite nulo
                    'phone' => preg_replace('/\D+/', '', $f['telefono']) ?: null,
                    'voting_municipality_id' => $municipioId,
                    'referrer_id' => $referidor->id,
                    'ref_code_used' => $codigo,
                    'status' => 'pendiente',
                    'origen' => 'planilla',
                ]);
                $creados++;
            }
        });

        foreach ($avisos as $a) {
            $this->command?->warn($a);
        }
        $this->command?->info("Creados: {$creados} · ya estaban: {$repetidos} · de {$filas->count()} filas.");
    }

    /** @return \Illuminate\Support\Collection<int, array{nombre:string, cedula:string, telefono:string, dane:string}> */
    private function leer(string $ruta)
    {
        $h = fopen($ruta, 'r');
        $filas = collect();
        $n = 0;
        while (($d = fgetcsv($h, 0, ';')) !== false) {
            $n++;
            if ($n === 1 || $d === [null]) {
                continue; // encabezado o línea vacía
            }
            $d = array_map(fn ($v) => trim((string) $v), array_pad($d, 4, ''));
            $filas[$n] = ['nombre' => $d[0], 'cedula' => $d[1], 'telefono' => $d[2], 'dane' => $d[3]];
        }
        fclose($h);

        return $filas;
    }
}
