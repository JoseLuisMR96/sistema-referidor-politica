<?php

namespace App\Livewire\Eventos;

use App\Services\Territorio\RetornoEventos;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Retorno de los eventos: registros nuevos en el municipio después de cada
 * evento y costo por registro, por evento, quién fue, persona, municipio y tipo.
 */
class Retorno extends Component
{
    #[Url]
    public int $ventana = 14;

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('eventos.ver'), 403);
    }

    public function render(RetornoEventos $retorno)
    {
        $tenant = app(TenantContext::class)->tenant();
        $fecha = fn ($v) => $v !== '' && strtotime($v) ? Carbon::parse($v) : null;

        return view('livewire.eventos.retorno', [
            'tenant' => $tenant,
            'r' => $tenant ? $retorno->calcular($this->ventana, $fecha($this->desde), $fecha($this->hasta)) : null,
        ])->title('Retorno de los eventos');
    }
}
