<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\PublicRegistration;
use App\Models\Referrer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Stats extends Component
{
    public int $total = 0;
    public int $hoy = 0;
    public int $ultimos7 = 0;

    public array $topReferrers = [];
    public array $municipiosVotan = [];
    public array $generos = [];
    public array $rangosEdad = [];
    public array $conteoPorMunicipio = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('dashboard.ver'), 403);

        $hoy = Carbon::today();

        $this->total = PublicRegistration::count();
        $this->hoy = PublicRegistration::whereDate('created_at', $hoy)->count();
        $this->ultimos7 = PublicRegistration::where('created_at', '>=', now()->subDays(7))->count();

        $this->topReferrers = Referrer::query()
            ->leftJoin('public_registrations', 'referrers.id', '=', 'public_registrations.referrer_id')
            ->select(
                'referrers.id',
                'referrers.name',
                'referrers.code',
                DB::raw('COUNT(public_registrations.id) as total')
            )
            ->groupBy('referrers.id', 'referrers.name', 'referrers.code')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();

        /**
         * ===========================
         * MUNICIPIOS (NUEVO POR FK)
         * ===========================
         * - Donut: top 10 por voting_municipality_id
         * - Mapa: conteo por nombre normalizado (sale de municipios.nombre)
         * - Compatibilidad: suma registros legacy por voting_municipality (texto)
         */

        $byFkRows = PublicRegistration::query()
            ->join('municipios as m', 'm.id', '=', 'public_registrations.voting_municipality_id')
            ->leftJoin('departamentos as d', 'd.id', '=', 'm.departamento_id')
            ->whereNotNull('public_registrations.voting_municipality_id')
            ->selectRaw("
                TRIM(m.nombre) as nombre,
                UPPER(TRIM(m.nombre)) as muni_key,
                TRIM(d.nombre) as departamento,
                COUNT(*) as total
            ")
            ->groupBy('nombre', 'muni_key', 'departamento')
            ->get();

        // indexado por key
        $byFk = [];
        foreach ($byFkRows as $row) {
            $key = $row->muni_key;
            $byFk[$key] = [
                'label' => $row->nombre,
                'departamento' => $row->departamento ?: '—',
                'total' => (int) $row->total,
            ];
        }

        // 2) Conteo legacy (texto) SOLO si no hay FK
        $byLegacyRows = PublicRegistration::query()
            ->whereNull('voting_municipality_id')
            ->whereNotNull('voting_municipality')
            ->where('voting_municipality', '!=', '')
            ->selectRaw("TRIM(voting_municipality) as nombre, UPPER(TRIM(voting_municipality)) as muni_key, COUNT(*) as total")
            ->groupBy('nombre', 'muni_key')
            ->get();

        $byLegacy = [];
        foreach ($byLegacyRows as $row) {
            $key = $row->muni_key;
            $byLegacy[$key] = [
                'label' => $row->nombre,
                'departamento' => '—', // no hay FK, no sabemos el depto
                'total' => (int) $row->total,
            ];
        }

        // 3) Merge (suma por key). Si existe FK, gana el label FK.
        $merged = $byFk;
        foreach ($byLegacy as $key => $row) {
            if (!isset($merged[$key])) {
                $merged[$key] = $row;
            } else {
                $merged[$key]['total'] += $row['total'];
            }
        }

        // 4) Para mapa: pluck key=>total
        $this->conteoPorMunicipio = collect($merged)
            ->mapWithKeys(fn($row, $key) => [$key => (int) $row['total']])
            ->toArray();

        // 5) Donut Top 10: label bonito + value
        $this->municipiosVotan = collect($merged)
            ->sortByDesc(fn($row) => (int) $row['total'])
            ->take(10)
            ->map(fn($row) => [
                'label' => $row['label'],
                'departamento' => $row['departamento'] ?? '—',
                'value' => (int) $row['total'],
            ])
            ->values()
            ->all();


        // Género
        $this->generos = PublicRegistration::query()
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
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();

        // Rangos de edad
        $this->rangosEdad = PublicRegistration::query()
            ->selectRaw("
                CASE
                    WHEN age IS NULL THEN 'Sin dato'
                    WHEN age < 18 THEN '0-17'
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
                    WHEN '0-17' THEN 1
                    WHEN '18-25' THEN 2
                    WHEN '26-35' THEN 3
                    WHEN '36-45' THEN 4
                    WHEN '46-60' THEN 5
                    WHEN '61+' THEN 6
                    ELSE 7
                END
            ")
            ->get()
            ->map(fn($row) => [
                'label' => $row->rango,
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.dashboard.stats');
    }
}
