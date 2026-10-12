<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datos que pertenecen a un tenant.
 *
 * - Scope global: si hay tenant en el contexto, toda consulta Eloquent queda
 *   filtrada por él (incluido el route model binding: un id de otro tenant da
 *   404). Sin tenant en el contexto ("Todos", webhooks, consola) no filtra.
 * - Al crear: si no viene tenant_id, toma el del contexto; si tampoco hay,
 *   falla. Nunca se guarda un dato huérfano.
 *
 * OJO: DB::table(), Model::insert() y upsert() se saltan ambas cosas; ahí hay
 * que poner tenant_id a mano.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId !== null) {
                $query->where($query->getModel()->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function ($model) {
            if (! empty($model->tenant_id)) {
                return;
            }

            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                throw new \LogicException(
                    'No se puede crear '.class_basename($model).' sin tenant. Elige un tenant antes de crear datos.'
                );
            }

            $model->tenant_id = $tenantId;
        });
    }

    public function initializeBelongsToTenant(): void
    {
        $this->mergeFillable(['tenant_id']);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
