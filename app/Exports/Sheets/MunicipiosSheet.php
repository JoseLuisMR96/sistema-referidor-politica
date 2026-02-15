<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MunicipiosSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Municipios votan';
    }

    public function headings(): array
    {
        return ['Municipio', 'Registros'];
    }

    public function collection(): Collection
    {
        return collect($this->data)->map(fn ($m) => [
            'Municipio' => $m['label'] ?? '',
            'Registros' => (int)($m['value'] ?? 0),
        ]);
    }
}
