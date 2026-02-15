<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class KpisSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(protected array $kpis) {}

    public function title(): string
    {
        return 'KPIs';
    }

    public function headings(): array
    {
        return ['Indicador', 'Valor'];
    }

    public function collection(): Collection
    {
        return collect([
            ['Total registros', (int)($this->kpis['total'] ?? 0)],
            ['Registros hoy', (int)($this->kpis['hoy'] ?? 0)],
            ['Últimos 7 días', (int)($this->kpis['ultimos7'] ?? 0)],
        ]);
    }
}
