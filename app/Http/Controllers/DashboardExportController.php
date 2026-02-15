<?php

namespace App\Http\Controllers;

use App\Exports\DashboardExport;
use App\Models\PublicRegistration;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class DashboardExportController extends Controller
{
    public function export()
    {
        // Municipios votan (Top 10)
        $municipiosVotan = PublicRegistration::query()
            ->join('municipios as m', 'm.id', '=', 'public_registrations.voting_municipality_id')
            ->select('m.nombre as municipio', DB::raw('COUNT(*) as total'))
            ->groupBy('m.nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($row) => ['label' => $row->municipio, 'value' => (int)$row->total])
            ->values()
            ->all();

        // Género
        $generos = PublicRegistration::query()
            ->select('gender', DB::raw('COUNT(*) as total'))
            ->whereNotNull('gender')
            ->where('gender', '!=', '')
            ->groupBy('gender')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'label' => match ($row->gender) {
                    'M' => 'Masculino',
                    'F' => 'Femenino',
                    'O' => 'Otro',
                    'NR' => 'Prefiero no responder',
                    default => (string) $row->gender,
                },
                'value' => (int)$row->total
            ])
            ->values()
            ->all();

        // Edades (rangos)
        $rangosEdad = PublicRegistration::query()
            ->selectRaw("
                CASE
                    WHEN age IS NULL THEN 'Sin dato'
                    WHEN age BETWEEN 18 AND 25 THEN '18-25'
                    WHEN age BETWEEN 26 AND 35 THEN '26-35'
                    WHEN age BETWEEN 36 AND 45 THEN '36-45'
                    WHEN age BETWEEN 46 AND 60 THEN '46-60'
                    ELSE '61+'
                END as rango, COUNT(*) as total
            ")
            ->groupBy('rango')
            ->orderByRaw("
                CASE rango
                    WHEN '18-25' THEN 1
                    WHEN '26-35' THEN 2
                    WHEN '36-45' THEN 3
                    WHEN '46-60' THEN 4
                    WHEN '61+' THEN 5
                    WHEN 'Sin dato' THEN 6
                    ELSE 7
                END
            ")
            ->get()
            ->map(fn($row) => ['label' => $row->rango, 'value' => (int)$row->total])
            ->values()
            ->all();

        $export = new DashboardExport($municipiosVotan, $generos, $rangosEdad);

        return Excel::download($export, 'dashboard.xlsx');
    }
}
