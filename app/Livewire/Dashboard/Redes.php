<?php

namespace App\Livewire\Dashboard;

use App\Services\Redes\RedesStats;
use App\Support\TenantContext;
use Livewire\Component;

/**
 * Dashboard de redes sociales (separado del de registros).
 *
 * - Con candidato elegido: totales, crecimiento semanal por cuenta y detalle.
 * - En "Todos los tenants": comparativo entre candidatos.
 */
class Redes extends Component
{
    public bool $esTodos = false;

    public ?string $candidato = null;

    public array $cuentas = [];

    public array $totales = [];

    public array $comparativo = [];

    public function mount(RedesStats $stats): void
    {
        abort_unless(auth()->user()->can('redes.ver'), 403);

        $contexto = app(TenantContext::class);
        $this->esTodos = $contexto->esTodos();

        if ($this->esTodos) {
            $this->comparativo = $stats->comparativo();

            return;
        }

        $this->candidato = $contexto->tenant()?->nombre;
        $this->cuentas = $stats->resumen();
        $this->totales = $stats->totales($this->cuentas);
    }

    public function render()
    {
        return view('livewire.dashboard.redes');
    }
}
