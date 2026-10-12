<?php

namespace Tests;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fija el tenant con el que se crean datos en el test (por defecto el
     * "Tenant inicial" que crea la migración).
     *
     * OJO: cada petición HTTP del test ($this->get(...)) vuelve a fijar el
     * contexto desde la sesión del usuario; si después se crean datos, hay
     * que volver a llamar a esto.
     */
    protected function usarTenant(?Tenant $tenant = null): Tenant
    {
        $tenant ??= Tenant::where('slug', 'tenant-inicial')->firstOrFail();

        app(TenantContext::class)->set($tenant->id);

        return $tenant;
    }
}
