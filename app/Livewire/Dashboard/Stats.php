<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\PublicRegistration;
use App\Models\Referrer;
use App\Services\DashboardStats;
use App\Support\TenantContext;
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

    // Apoyo dentro de la circunscripción del candidato elegido (null en
    // "Todos los tenants" o si al tenant le falta cargo/lugar)
    public ?array $circunscripcion = null;

    // Departamento con el que abre el mapa
    public string $deptoMapa = 'META';

    public function mount(DashboardStats $stats): void
    {
        abort_unless(auth()->user()->can('dashboard.ver'), 403);

        $hoy = Carbon::today();

        $this->total = PublicRegistration::count();
        $this->hoy = PublicRegistration::whereDate('created_at', $hoy)->count();
        $this->ultimos7 = PublicRegistration::where('created_at', '>=', now()->subDays(7))->count();

        $this->topReferrers = Referrer::query()
            // El ranking solo cuenta registros reales del formulario: los
            // importados del padrón tienen el referidor asignado al azar.
            // La condición va en el JOIN (no en WHERE) para no perder a los
            // referidores con 0 registros.
            ->leftJoin('public_registrations', function ($join) {
                $join->on('referrers.id', '=', 'public_registrations.referrer_id')
                    ->where('public_registrations.origen', '!=', 'importado');
            })
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

        // Municipios, género y edades salen de DashboardStats, que también
        // usa el export a Excel: así pantalla y archivo dan las mismas cifras.
        $municipios = $stats->municipios();

        // Para mapa: key (nombre en mayúsculas) => total
        $this->conteoPorMunicipio = collect($municipios)
            ->mapWithKeys(fn ($row, $key) => [$key => (int) $row['total']])
            ->toArray();

        // Donut Top 10
        $this->municipiosVotan = $stats->topMunicipios(10, $municipios);

        $this->generos = $stats->generos();
        $this->rangosEdad = $stats->rangosEdad();

        $tenant = app(TenantContext::class)->tenant()?->loadMissing(['departamento', 'municipio']);

        if ($tenant) {
            $this->circunscripcion = $stats->circunscripcion($tenant);
            $this->deptoMapa = $tenant->departamento?->nombre ?? $this->deptoMapa;
        }
    }

    public function render()
    {
        return view('livewire.dashboard.stats');
    }
}
