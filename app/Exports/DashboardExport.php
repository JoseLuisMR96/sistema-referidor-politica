<?php

namespace App\Exports;

use App\Exports\Sheets\EdadesSheet;
use App\Exports\Sheets\GenerosSheet;
use App\Exports\Sheets\MunicipiosVotanSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DashboardExport implements WithMultipleSheets
{
    public function __construct(
        public array $municipiosVotan,
        public array $generos,
        public array $rangosEdad,
    ) {}

    public function sheets(): array
    {
        return [
            new MunicipiosVotanSheet($this->municipiosVotan),
            new GenerosSheet($this->generos),
            new EdadesSheet($this->rangosEdad),
        ];
    }
}
