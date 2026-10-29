<?php

namespace App\Console\Commands;

use App\Enums\CargoEnum;
use App\Services\Historico\HistoricoElectoralStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Llena la caché del Histórico electoral con las vistas que abre la
 * pantalla (todo el país y cada departamento, por año, cargo y vuelta),
 * para que nadie espere la primera consulta. Se corre solo al terminar
 * cada importación; también se puede correr a mano.
 */
class PrecalcularHistoricoCommand extends Command
{
    protected $signature = 'historico:precalcular';

    protected $description = 'Precalcula la caché del Histórico electoral';

    public function handle(): int
    {
        $inicio = microtime(true);
        $vistas = 0;

        $combinaciones = DB::table('historico_electoral')
            ->select('anio', 'cargo', 'vuelta')->distinct()
            ->orderByDesc('anio')->get();

        foreach ($combinaciones as $c) {
            $cargo = CargoEnum::tryFrom($c->cargo);
            if (! $cargo) {
                continue;
            }

            $stats = app(HistoricoElectoralStats::class);
            $stats->vuelta = (int) $c->vuelta;
            $anio = (int) $c->anio;

            $stats->anios();
            $stats->cargos($anio);
            $stats->vueltas($anio, $cargo->value);

            // Vista de todo el país
            if ($cargo->ambito() === CargoEnum::AMBITO_MUNICIPAL) {
                $stats->ganadoresPorMunicipio($anio, $cargo->value);
            } else {
                $stats->ganadoresPorDepartamento($anio, $cargo->value);
                if ($cargo->ambito() === CargoEnum::AMBITO_NACIONAL) {
                    $stats->contienda($anio, $cargo->value, null);
                }
            }

            // Cada departamento: mapa y, si es departamental o nacional, su contienda
            $deptos = DB::table('historico_electoral')
                ->where('anio', $anio)->where('cargo', $cargo->value)->where('vuelta', $c->vuelta)
                ->distinct()->pluck('departamento_id');

            foreach ($deptos as $depId) {
                $stats->ganadoresPorMunicipio($anio, $cargo->value, (int) $depId);
                if ($cargo->ambito() !== CargoEnum::AMBITO_MUNICIPAL) {
                    $stats->contienda($anio, $cargo->value, (int) $depId);
                }
                $vistas++;
            }

            $this->line("  {$anio} {$cargo->label()}".($c->vuelta > 1 ? " (vuelta {$c->vuelta})" : '').": {$deptos->count()} departamentos");
        }

        $this->info(sprintf('%d vistas precalculadas en %.0f s.', $vistas, microtime(true) - $inicio));

        return self::SUCCESS;
    }
}
