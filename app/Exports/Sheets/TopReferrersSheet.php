<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TopReferrersSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Top referidores';
    }

    public function headings(): array
    {
        return ['Referidor', 'Código', 'Registros'];
    }

    public function collection(): Collection
    {
        return collect($this->data)->map(fn ($r) => [
            'Referidor' => $r['name'] ?? '',
            'Código' => $r['code'] ?? '',
            'Registros' => (int)($r['total'] ?? 0),
        ]);
    }
}
    