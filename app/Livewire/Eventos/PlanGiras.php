<?php

namespace App\Livewire\Eventos;

use App\Services\Historico\PlanCampania;
use App\Services\Territorio\PlanGiras as Servicio;
use App\Support\TenantContext;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Plan de giras: a dónde ir pronto según la prioridad electoral y la
 * presencia de la campaña. "Agendar" abre el registro de un evento planeado.
 */
class PlanGiras extends Component
{
    #[Url]
    public string $enfoque = 'equilibrado';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('eventos.ver'), 403);
    }

    public function render(Servicio $plan)
    {
        if (! array_key_exists($this->enfoque, PlanCampania::ENFOQUES)) {
            $this->enfoque = 'equilibrado';
        }
        $tenant = app(TenantContext::class)->tenant();

        return view('livewire.eventos.plan-giras', [
            'tenant' => $tenant,
            'p' => $tenant ? $plan->calcular($tenant, $this->enfoque) : null,
        ])->title('Plan de giras');
    }
}
