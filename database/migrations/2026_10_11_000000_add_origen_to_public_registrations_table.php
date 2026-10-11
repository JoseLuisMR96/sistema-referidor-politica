<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de dónde salió cada registro.
 *
 * 'formulario' = lo llenó la persona por el link del referidor.
 * 'importado'  = vino del padrón (imported_people) vía referidos:volcar-importados,
 *                con el referidor asignado al azar.
 *
 * No se rellenan las filas viejas: no hay forma segura de distinguir un
 * volcado anterior de un registro orgánico. Ver el bloque 0 de
 * database/sql/volcar-importados.sql para marcarlas a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('public_registrations', 'origen')) {
            return;
        }

        Schema::table('public_registrations', function (Blueprint $table) {
            $table->string('origen', 20)->default('formulario')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('public_registrations', 'origen')) {
            return;
        }

        Schema::table('public_registrations', function (Blueprint $table) {
            $table->dropIndex(['origen']);
            $table->dropColumn('origen');
        });
    }
};
