<?php

namespace App\Livewire\Concerns;

use App\Support\TenantContext;

/**
 * Para pantallas que CREAN datos: en modo "Todos los tenants" no se sabe a
 * quién pertenece lo que se crea.
 *
 * La pantalla se abre igual y la vista muestra <x-tenant-requerido /> con un
 * selector para elegir el candidato ahí mismo. Antes se redirigía al
 * dashboard y parecía que el menú no llevaba a la pantalla.
 */
trait RequiereTenant
{
    /**
     * False si no hay un tenant concreto elegido (y deja un error 'tenant').
     * Uso en las acciones que guardan: `if (! $this->exigirTenant()) return;`
     */
    protected function exigirTenant(): bool
    {
        if (! app(TenantContext::class)->esTodos()) {
            return true;
        }

        $this->addError('tenant', 'Elige un candidato antes de guardar: en "Todos los tenants" solo se puede consultar.');

        return false;
    }
}
