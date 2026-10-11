<?php

namespace App\Livewire\Concerns;

use App\Support\TenantContext;

/**
 * Para pantallas que CREAN datos: en modo "Todos los tenants" no se sabe a
 * quién pertenece lo que se crea, así que se manda al usuario a elegir uno.
 */
trait RequiereTenant
{
    /**
     * Devuelve false (y redirige) si no hay un tenant concreto elegido.
     * Uso: `if (! $this->exigirTenant()) return;`
     */
    protected function exigirTenant(): bool
    {
        if (! app(TenantContext::class)->esTodos()) {
            return true;
        }

        session()->flash('tenant_aviso', 'Elige un tenant en el menú lateral antes de crear datos: en "Todos los tenants" solo se puede consultar.');
        $this->redirectRoute('dashboard');

        return false;
    }
}
