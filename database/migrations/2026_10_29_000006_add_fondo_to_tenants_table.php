<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imagen de fondo del candidato (p. ej. la bandera o el tigre con la
 * bandera): se ve detrás de las tarjetas en toda la app y en sus formularios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('fondo_path')->nullable()->after('foto_path');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('fondo_path');
        });
    }
};
