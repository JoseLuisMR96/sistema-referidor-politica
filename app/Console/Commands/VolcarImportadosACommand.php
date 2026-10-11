<?php

namespace App\Console\Commands;

use App\Models\ImportedPerson;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\Referrer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vuelca imported_people (padrón cargado desde Excel) a public_registrations.
 *
 * Es idempotente: omite las cédulas que ya existen en el destino, así que se
 * puede volver a correr sin duplicar. La atribución de referidores es aleatoria
 * entre los códigos indicados, por eso las filas quedan con origen = 'importado'.
 */
class VolcarImportadosACommand extends Command
{
    protected $signature = 'referidos:volcar-importados
        {--referrers= : Códigos de referidores separados por coma, se reparten al azar}
        {--municipio=685 : ID del municipio de residencia y votación}
        {--gender=NR : Valor para la columna gender (es NOT NULL y el origen no lo trae)}
        {--document-type=CC : Tipo de documento a asignar}
        {--status=pendiente : Estado inicial de los registros}
        {--batch= : Procesar solo un batch_id de imported_people}
        {--dry-run : No escribe nada, solo reporta lo que haría}';

    protected $description = 'Copia imported_people a public_registrations atribuyendo referidores al azar';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $municipio = Municipio::with('departamento')->find((int) $this->option('municipio'));

        if (! $municipio) {
            $this->error("No existe el municipio con id {$this->option('municipio')}.");

            return self::FAILURE;
        }

        $referrers = $this->resolverReferidores();

        if ($referrers === null) {
            return self::FAILURE;
        }

        $origen = ImportedPerson::query()
            ->when($this->option('batch'), fn ($q, $batch) => $q->where('batch_id', $batch));

        $total = (clone $origen)->count();

        if ($total === 0) {
            $this->warn('No hay filas en imported_people para procesar.');

            return self::SUCCESS;
        }

        // Idempotencia: el destino tiene un índice único (document_type, document_number)
        $documentType = (string) $this->option('document-type');

        $yaExisten = PublicRegistration::query()
            ->where('document_type', $documentType)
            ->pluck('document_number')
            ->flip();

        $this->mostrarResumen($municipio, $referrers, $total, $yaExisten->count(), $dryRun);

        if (! $dryRun && ! $this->confirm('¿Insertar los registros en public_registrations?', false)) {
            $this->line('Cancelado, no se escribió nada.');

            return self::SUCCESS;
        }

        $insertados = 0;
        $omitidos = 0;
        $porReferidor = array_fill_keys($referrers->pluck('id')->all(), 0);

        $barra = $this->output->createProgressBar($total);
        $barra->start();

        $procesar = function ($filas) use (
            $referrers, $municipio, $documentType, $yaExisten, $dryRun,
            &$insertados, &$omitidos, &$porReferidor, $barra
        ) {
            $lote = [];
            $ahora = now();

            foreach ($filas as $persona) {
                $barra->advance();

                if ($yaExisten->has($persona->document_number)) {
                    $omitidos++;

                    continue;
                }

                $referidor = $referrers->random();
                $porReferidor[$referidor->id]++;

                $lote[] = [
                    'full_name' => $persona->full_name,
                    'document_type' => $documentType,
                    'document_number' => $persona->document_number,
                    'age' => null,
                    'gender' => (string) $this->option('gender'),
                    'residence_municipality' => $municipio->nombre,
                    'voting_municipality' => $municipio->nombre,
                    'residence_municipality_id' => $municipio->id,
                    'voting_municipality_id' => $municipio->id,
                    'phone' => $persona->phone ?: null,
                    'referrer_id' => $referidor->id,
                    'ref_code_used' => $referidor->code,
                    'status' => (string) $this->option('status'),
                    // Marca para distinguirlos de los registros reales del formulario
                    'origen' => 'importado',
                    'created_by_user_id' => $persona->created_by_user_id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];

                $insertados++;
            }

            if ($lote && ! $dryRun) {
                PublicRegistration::insert($lote);
            }
        };

        if ($dryRun) {
            $origen->chunkById(200, $procesar);
        } else {
            DB::transaction(fn () => $origen->chunkById(200, $procesar));
        }

        $barra->finish();
        $this->newLine(2);

        $this->table(
            ['Resultado', 'Filas'],
            [
                [$dryRun ? 'Se insertarían' : 'Insertados', $insertados],
                ['Omitidos (cédula ya registrada)', $omitidos],
            ],
        );

        $this->line('Reparto por referidor:');
        foreach ($referrers as $referidor) {
            $this->line(sprintf(
                '  %-10s %-20s %d',
                $referidor->code,
                mb_substr($referidor->name, 0, 18),
                $porReferidor[$referidor->id],
            ));
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Simulación: no se escribió nada. Quita --dry-run para aplicarlo.');
        }

        return self::SUCCESS;
    }

    /**
     * Resuelve los referidores por código y aborta si alguno no existe.
     *
     * Se buscan por `code` y no por id a propósito: el código es el
     * identificador estable entre entornos.
     */
    private function resolverReferidores(): ?\Illuminate\Support\Collection
    {
        $codigos = collect(explode(',', (string) $this->option('referrers')))
            ->map(fn (string $c) => trim($c))
            ->filter()
            ->unique()
            ->values();

        if ($codigos->isEmpty()) {
            $this->error('Indica los referidores con --referrers=CODIGO1,CODIGO2,...');

            return null;
        }

        $referrers = Referrer::query()->whereIn('code', $codigos)->get();
        $faltantes = $codigos->diff($referrers->pluck('code'));

        if ($faltantes->isNotEmpty()) {
            $this->error('Estos códigos de referidor no existen en la base actual ('
                .DB::connection()->getDatabaseName().'):');

            foreach ($faltantes as $codigo) {
                $this->line("  - {$codigo}");
            }

            $this->newLine();
            $this->line('Códigos disponibles:');

            foreach (Referrer::query()->orderBy('id')->get() as $r) {
                $this->line(sprintf('  id=%-4d %-10s %s', $r->id, $r->code, $r->name));
            }

            return null;
        }

        return $referrers;
    }

    private function mostrarResumen(
        Municipio $municipio,
        \Illuminate\Support\Collection $referrers,
        int $total,
        int $yaEnDestino,
        bool $dryRun,
    ): void {
        $this->newLine();
        $this->line($dryRun ? '<comment>SIMULACIÓN (--dry-run)</comment>' : '<info>EJECUCIÓN REAL</info>');

        $this->table(['Parámetro', 'Valor'], [
            ['Base de datos', DB::connection()->getDatabaseName()],
            ['Filas en imported_people', $total],
            ['Ya en public_registrations', $yaEnDestino],
            ['Municipio (residencia y voto)', "{$municipio->nombre} (id {$municipio->id})"],
            ['Departamento', $municipio->departamento?->nombre ?? '—'],
            ['Tipo de documento', $this->option('document-type')],
            ['Género', $this->option('gender')],
            ['Estado', $this->option('status')],
            ['Referidores', $referrers->pluck('code')->implode(', ')],
        ]);
    }
}
