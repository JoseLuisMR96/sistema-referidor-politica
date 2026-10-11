<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solo sqlite (la suite de tests).
 *
 * En sqlite, ->enum() crea un CHECK con la lista de valores. Las columnas
 * status de campaigns y campaign_messages se crearon con la lista vieja
 * (sin 'pending', 'accepted', 'batched'…) y 2026_03_17_expand_message_status_enum
 * solo amplía el ENUM en MySQL. Resultado: en los tests fallaban inserts
 * que en producción son válidos. Aquí se cambian a texto libre; en MySQL
 * no se toca nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (Schema::hasTable('campaign_messages')) {
            Schema::table('campaign_messages', function (Blueprint $table) {
                $table->string('status', 30)->default('pending')->change();
            });
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->string('status', 30)->default('draft')->change();
            });
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: solo afecta la base de pruebas en memoria.
    }
};
