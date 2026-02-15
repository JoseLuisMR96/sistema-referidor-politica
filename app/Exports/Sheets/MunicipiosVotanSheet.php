<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCharts;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;

class MunicipiosVotanSheet implements FromArray, WithTitle, WithHeadings, WithCharts
{
    public function __construct(public array $rows) {}

    public function title(): string
    {
        return 'Municipios';
    }

    public function headings(): array
    {
        return ['Municipio', 'Registros'];
    }

    public function array(): array
    {
        // rows: [ ['label' => 'Villavicencio', 'value' => 10], ... ]
        return array_map(
            fn ($r) => [ (string)($r['label'] ?? ''), (int)($r['value'] ?? 0) ],
            $this->rows
        );
    }

    public function charts()
    {
        // Si no hay datos reales, no generes gráfico
        $count = count($this->rows);
        if ($count <= 0) {
            return [];
        }

        $sheetName = $this->title(); // "Municipios"
        $startRow = 2;
        $endRow = $count + 1;

        // Serie: encabezado B1 ("Registros")
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
            'municipios_chart',
            new Title('Municipio donde votan (Top)'),
            $legend,
            $plotArea
        );

        // Ubicación del gráfico
        $chart->setTopLeftPosition('D2');
        $chart->setBottomRightPosition('L20');

        return $chart; // o [ $chart ] si vas a meter más
    }
}
