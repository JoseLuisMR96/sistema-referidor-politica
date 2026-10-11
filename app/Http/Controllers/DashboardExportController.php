<?php

namespace App\Http\Controllers;

use App\Exports\DashboardExport;
use App\Services\DashboardStats;
use Maatwebsite\Excel\Facades\Excel;

class DashboardExportController extends Controller
{
    public function export(DashboardStats $stats)
    {
        abort_unless(auth()->user()?->can('dashboard.exportar'), 403);

        $export = new DashboardExport(
            $stats->topMunicipios(10),
            $stats->generos(),
            $stats->rangosEdad(),
        );

        return Excel::download($export, 'dashboard.xlsx');
    }
}
