<?php

namespace App\Livewire\Dashboard;

use App\Models\RedCuenta;
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

    // Instagram del candidato frente a sus rivales
    public array $rivales = [];

    // Tono de los comentarios (solo con token del candidato)
    public array $tono = [];

    // ¿Alguna cuenta del candidato tiene su token? Sin eso no hay comentarios
    public bool $hayTokenCandidato = false;

    // Aviso del token de Meta (vencido, por vencer o rechazado)
    public ?string $avisoToken = null;

    public function mount(RedesStats $stats): void
    {
        abort_unless(auth()->user()->can('redes.ver'), 403);

        $this->avisoToken = $this->avisoToken();

        $contexto = app(TenantContext::class);
        $this->esTodos = $contexto->esTodos();

        if ($this->esTodos) {
            $this->comparativo = $stats->comparativo();

            return;
        }

        $this->candidato = $contexto->tenant()?->nombre;
        $this->cuentas = $stats->resumen();
        $this->totales = $stats->totales($this->cuentas);
        $this->rivales = $stats->rivales();
        $this->tono = $stats->tono();
        $this->hayTokenCandidato = RedCuenta::query()
            ->where('tipo', RedCuenta::TIPO_PROPIA)
            ->whereNotNull('access_token')
            ->exists();
    }

    private function avisoToken(): ?string
    {
        $estado = (new \App\Services\Redes\MetaGraphClient)->tokenPorVencer(10);

        if ($estado && $estado['vencido']) {
            return 'El token de Meta venció el '.$estado['vence']->locale('es')->isoFormat('D [de] MMMM').': no se están tomando las fotos diarias de seguidores.';
        }
        if ($estado) {
            return "El token de Meta vence en {$estado['dias']} días (".$estado['vence']->locale('es')->isoFormat('D [de] MMMM').'). Renuévalo para no perder días de seguimiento.';
        }

        $rechazado = RedCuenta::query()->where('ultimo_error', 'like', '%access token%')->exists();

        return $rechazado ? 'Meta rechazó el token (venció o se cambió la clave): no se están tomando las fotos diarias de seguidores.' : null;
    }

    public function render()
    {
        return view('livewire.dashboard.redes');
    }
}
