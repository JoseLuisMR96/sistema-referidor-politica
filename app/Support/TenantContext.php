<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Tenant con el que se está trabajando en esta petición o comando.
 *
 * - Usuario restringido (users.tenant_id con valor): SIEMPRE su tenant, sin
 *   importar lo que diga la sesión. Así ni la URL ni un estado viejo lo sacan
 *   de su tenant.
 * - Usuario global (users.tenant_id nulo): el tenant que eligió en el menú, o
 *   null = "Todos los tenants" (solo para ver: crear exige uno concreto).
 * - Sin usuario (formularios públicos, webhooks, colas, consola): lo que se
 *   haya fijado con set(); por defecto null y los modelos no filtran.
 *
 * Se registra como singleton "scoped": se reinicia entre peticiones y jobs.
 */
class TenantContext
{
    public const SESSION_KEY = 'tenant_id';

    private ?int $tenantId = null;

    private ?Tenant $tenant = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->tenant = null;
    }

    public function id(): ?int
    {
        $restringido = auth()->user()?->tenant_id;

        if ($restringido) {
            return (int) $restringido;
        }

        return $this->tenantId;
    }

    /** "Todos los tenants": solo posible para usuarios globales o sin usuario. */
    public function esTodos(): bool
    {
        return $this->id() === null;
    }

    public function tenant(): ?Tenant
    {
        $id = $this->id();

        if ($id === null) {
            return null;
        }

        if ($this->tenant?->id !== $id) {
            $this->tenant = Tenant::find($id);
        }

        return $this->tenant;
    }

    /**
     * Ejecuta $callback con un tenant fijado y luego restaura el anterior.
     * Útil en consola y jobs.
     */
    public function con(?int $tenantId, callable $callback): mixed
    {
        $anterior = $this->tenantId;
        $this->set($tenantId);

        try {
            return $callback();
        } finally {
            $this->set($anterior);
        }
    }
}
