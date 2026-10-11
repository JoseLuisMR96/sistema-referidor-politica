<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el tenant de la petición a partir del usuario logueado.
 *
 * Va en el grupo `web`, así que también corre en las peticiones posteriores
 * de Livewire (/livewire/update), no solo en la carga inicial de la página.
 * Los usuarios restringidos no dependen de esto: TenantContext::id() siempre
 * devuelve su tenant.
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = app(TenantContext::class);

        if ($user?->tenant_id) {
            abort_unless(
                Tenant::whereKey($user->tenant_id)->where('activo', true)->exists(),
                403,
                'Tu tenant está desactivado. Contacta al administrador.'
            );
        }

        if ($user && ! $user->tenant_id) {
            $elegido = $request->session()->get(TenantContext::SESSION_KEY);

            // El tenant elegido pudo borrarse o desactivarse
            if ($elegido && ! Tenant::whereKey($elegido)->where('activo', true)->exists()) {
                $request->session()->forget(TenantContext::SESSION_KEY);
                $elegido = null;
            }

            $context->set($elegido ? (int) $elegido : null);
        }

        return $next($request);
    }
}
