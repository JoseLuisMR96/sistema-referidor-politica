<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pasa los estados heredados de campaigns.status a casos de CampaignStatusEnum.
 *
 * CampaignComposer guardaba 'active', que no es un caso del enum: leer
 * ->status con el modelo Campaign lanzaba ValueError. El ENUM de MySQL
 * conserva los valores viejos, así que esto solo actualiza filas.
 */
return new class extends Migration
{
    private const MAPA = [
        'active' => 'sending',
        'resumed' => 'sending',
        'pending' => 'queued',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('campaigns')) {
            return;
        }

        foreach (self::MAPA as $viejo => $nuevo) {
            DB::table('campaigns')->where('status', $viejo)->update(['status' => $nuevo]);
        }
    }

    public function down(): void
    {
        // Irreversible: no se sabe qué filas tenían cada valor heredado.
    }
};
