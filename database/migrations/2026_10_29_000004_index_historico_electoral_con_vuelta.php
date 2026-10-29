<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice que cubre los filtros del módulo (año, cargo, vuelta, lugar). El
 * anterior no tenía la vuelta y las consultas recorrían toda la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historico_electoral', function (Blueprint $table) {
            $table->index(['anio', 'cargo', 'vuelta', 'departamento_id', 'municipio_id', 'partido'], 'historico_filtros');
        });

        Schema::table('historico_electoral', function (Blueprint $table) {
            $table->dropIndex('historico_busqueda');
        });
    }

    public function down(): void
    {
        Schema::table('historico_electoral', function (Blueprint $table) {
            $table->index(['anio', 'cargo', 'departamento_id', 'municipio_id'], 'historico_busqueda');
        });

        Schema::table('historico_electoral', function (Blueprint $table) {
            $table->dropIndex('historico_filtros');
        });
    }
};
