<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Elimina un candidato (tenant) con todos sus datos.
 *
 * - Usa DB::table a propósito: el scope de BelongsToTenant filtraría por el
 *   candidato elegido en el menú, no por el que se borra.
 * - El orden importa: las tablas tienen FK RESTRICT entre sí y hacia tenants
 *   (los mensajes apuntan a referidores, las campañas a pregoneros...).
 *   campaign_metrics, campaign_batches y redes_comentarios_vistos caen solas
 *   por CASCADE.
 * - No borra si el candidato tiene usuarios restringidos a él: la FK
 *   users.tenant_id es SET NULL y, sin este freno, esos usuarios quedarían
 *   globales (verían a todos los candidatos).
 */
class EliminarTenant
{
    /** Tabla => etiqueta, en orden de borrado (primero lo que depende de otros). */
    public const TABLAS = [
        'whatsapp_campaign_responses' => 'Respuestas de WhatsApp',
        'campaign_messages' => 'Mensajes de WhatsApp',
        'campaigns' => 'Campañas de WhatsApp',
        'whatsapp_outbox' => 'Outbox de WhatsApp',
        'wpp_messages' => 'Mensajes WPP',
        'wpp_contacts' => 'Contactos WPP',
        'wpp_campaigns' => 'Campañas WPP',
        'public_registrations' => 'Registros',
        'referidos_pregoneros' => 'Referidos de pregoneros',
        'referidores_pregoneros' => 'Referidores pregoneros',
        'imported_people' => 'Importados (padrón)',
        'referrers' => 'Referidores',
        'redes_tono' => 'Tono de comentarios',
        'redes_metricas' => 'Métricas de redes',
        'redes_cuentas' => 'Cuentas de redes',
    ];

    /** Cuántos datos se borrarían, solo lo que tiene filas. */
    public function resumen(Tenant $tenant): array
    {
        $resumen = [];

        foreach (self::TABLAS as $tabla => $etiqueta) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            $total = DB::table($tabla)->where('tenant_id', $tenant->id)->count();
            if ($total > 0) {
                $resumen[$etiqueta] = $total;
            }
        }

        return $resumen;
    }

    public function usuarios(Tenant $tenant): int
    {
        return DB::table('users')->where('tenant_id', $tenant->id)->count();
    }

    public function eliminar(Tenant $tenant): void
    {
        if ($n = $this->usuarios($tenant)) {
            throw new RuntimeException("Tiene {$n} usuario(s) asignados. Muévelos a otro candidato o elimínalos en Usuarios antes de borrarlo.");
        }

        DB::transaction(function () use ($tenant) {
            foreach (array_keys(self::TABLAS) as $tabla) {
                if (Schema::hasTable($tabla)) {
                    DB::table($tabla)->where('tenant_id', $tenant->id)->delete();
                }
            }

            $tenant->delete();
        });

        foreach ([$tenant->logo_path, $tenant->foto_path, $tenant->fondo_path] as $ruta) {
            if ($ruta) {
                Storage::disk('public')->delete($ruta);
            }
        }

        // Si era el candidato elegido en el menú, se vuelve a "Todos"
        if (session(TenantContext::SESSION_KEY) == $tenant->id) {
            session()->forget(TenantContext::SESSION_KEY);
        }
    }
}
