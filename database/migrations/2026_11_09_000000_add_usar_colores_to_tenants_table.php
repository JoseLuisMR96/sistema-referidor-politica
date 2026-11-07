<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apariencia del candidato: con usar_colores en false la app se ve con los
 * colores por defecto (fondos blancos, índigo) aunque el candidato tenga los
 * suyos guardados; así se pueden volver a usar sin escribirlos de nuevo.
 * Los candidatos que ya existen siguen con sus colores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('usar_colores')->default(true)->after('tema_completo');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('usar_colores');
        });
    }
};
