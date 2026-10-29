<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\DemoCandidatoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra los datos de demostración de un candidato (los que creó
 * DemoCandidatoSeeder). Los datos reales no se tocan: solo lo marcado como
 * demo (origen = 'demo', referidores DEMO…, pregoneros con cédula 99…).
 */
class BorrarDemoCommand extends Command
{
    protected $signature = 'demo:borrar {slug=henry-palma : Slug del candidato} {--force : Sin pedir confirmación}';

    protected $description = 'Borra los datos de demostración de un candidato';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('slug'))->first();

        if (! $tenant) {
            $this->error('No existe el candidato '.$this->argument('slug'));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("¿Borrar los datos de demostración de {$tenant->nombre}?")) {
            return self::SUCCESS;
        }

        $borrado = DB::transaction(fn () => DemoCandidatoSeeder::borrar($tenant));

        foreach ($borrado as $que => $n) {
            $this->line("  {$que}: {$n}");
        }
        $this->info('Demo borrada.');

        return self::SUCCESS;
    }
}
