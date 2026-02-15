<?php

namespace App\Exports;

use App\Exports\Sheets\EdadesSheet;
use App\Exports\Sheets\GenerosSheet;
use App\Exports\Sheets\KpisSheet;
use App\Exports\Sheets\MunicipiosSheet;
use App\Exports\Sheets\TopReferrersSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DashboardStatsExport implements WithMultipleSheets
{
    public function __construct(public array $data) {}

    public function sheets(): array
    {
        return [
            new KpisSheet($this->data['kpis'] ?? []),
            new TopReferrersSheet($this->data['topReferrers'] ?? []),
            new MunicipiosSheet($this->data['municipiosVotan'] ?? []),
            new GenerosSheet($this->data['generos'] ?? []),
            new EdadesSheet($this->data['rangosEdad'] ?? []),
        ];
    }
}
