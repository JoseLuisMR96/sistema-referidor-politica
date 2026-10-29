<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Selector de tenant del menú. Solo para usuarios globales: a los
 * restringidos TenantContext siempre les devuelve su tenant.
 */
class TenantSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->esGlobal(), 403);

        $tenantId = $request->input('tenant_id');

        if ($tenantId === null || $tenantId === '') {
            // "Todos los tenants"
            $request->session()->forget(TenantContext::SESSION_KEY);

            return back();
        }

        $tenant = Tenant::whereKey((int) $tenantId)->where('activo', true)->firstOrFail();
        $request->session()->put(TenantContext::SESSION_KEY, $tenant->id);

        return back();
    }
}
