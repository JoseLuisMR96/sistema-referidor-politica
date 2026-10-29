<?php

namespace App\Console\Commands;

use App\Enums\CargoEnum;
use App\Services\Historico\ImportadorHistorico;
use Illuminate\Console\Command;

/**
 * Importa un CSV o Excel de resultados al histórico desde la consola (para
 * archivos grandes, como el "mesa a mesa" de la Registraduría: Resultados.csv).
 * Hace lo mismo que "Importar resultados" en la pantalla.
 */
class ImportarHistoricoArchivoCommand extends Command
{
    protected $signature = 'historico:importar-archivo
        {ruta : CSV o Excel}
        {--anio= : Año, si el archivo no lo trae}
        {--cargo= : Cargo (gobernacion, alcaldia, presidencia...), si el archivo no lo trae}
        {--vuelta= : 1 o 2 (presidencia), si el archivo no lo trae}';

    protected $description = 'Importa un archivo de resultados electorales al histórico';

    public function handle(ImportadorHistorico $importador): int
    {
        $ruta = $this->argument('ruta');

        if (! is_file($ruta)) {
            $this->error("No existe el archivo: {$ruta}");

            return self::FAILURE;
        }

        $cargo = $this->option('cargo');
        if ($cargo && ! CargoEnum::tryFrom($cargo)) {
            $this->error('Cargo inválido. Opciones: '.implode(', ', array_column(CargoEnum::cases(), 'value')));

            return self::FAILURE;
        }

        $r = $importador->importar(
            $ruta,
            $this->option('anio') ? (int) $this->option('anio') : null,
            $cargo,
            $ruta,
            $this->option('vuelta') ? (int) $this->option('vuelta') : null,
        );

        $this->info(number_format($r['insertadas'], 0, ',', '.').' filas importadas, '
            .number_format($r['reemplazadas'], 0, ',', '.').' reemplazadas, '
            .number_format($r['omitidas'], 0, ',', '.').' omitidas (nulos, no marcados, exterior).');

        foreach (array_slice($r['sin_lugar'], 0, 40, true) as $lugar => $n) {
            $this->warn("  Sin reconocer: {$lugar} ({$n})");
        }
        foreach (array_slice($r['errores'], 0, 10) as $e) {
            $this->error($e);
        }

        return $r['errores'] && ! $r['insertadas'] ? self::FAILURE : self::SUCCESS;
    }
}
