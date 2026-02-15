<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCharts;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;

class EdadesSheet implements FromCollection, WithHeadings, WithTitle, WithCharts
{
    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Edades';
    }

    public function headings(): array
    {
        return ['Rango de edad', 'Registros'];
    }

    public function collection(): Collection
    {
        // A1: Rango de edad | B1: Registros
        // A2.. labels, B2.. values
        return collect($this->data)->map(fn ($e) => [
            'Rango de edad' => (string)($e['label'] ?? ''),
            'Registros' => (int)($e['value'] ?? 0),
        ]);
    }

    public function charts()
    {
        $count = count($this->data);
        if ($count <= 0) {
            return [];
        }

        $sheetName = $this->title();
        $startRow = 2;
        $endRow = $count + 1;

        // Etiqueta de serie: B1 ("Registros")
        $labels = [
            new DataSeriesValues('String', "'{$sheetName}'!\$B\$1", null, 1)
        ];

        // Categorías: A2:A{n}
        $categories = [
            new DataSeriesValues('String', "'{$sheetName}'!\$A\${$startRow}:\$A\${$endRow}", null, $count)
        ];

        // Valores: B2:B{n}
        $values = [
            new DataSeriesValues('Number', "'{$sheetName}'!\$B\${$startRow}:\$B\${$endRow}", null, $count)
        ];

        $series = new DataSeries(
            DataSeries::TYPE_PIECHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($values) - 1),
            $labels,
            $categories,
            $values
        );

        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);

        $chart = new Chart(
            'edades_chart',
            new Title('Edades (Rangos)'),
            $legend,
            $plotArea
        );

        // Posición del gráfico en la hoja
        $chart->setTopLeftPosition('D2');
        $chart->setBottomRightPosition('L20');

        return $chart;
    }
}
