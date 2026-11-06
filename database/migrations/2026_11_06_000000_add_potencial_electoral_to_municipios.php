<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potencial electoral (cédulas habilitadas para votar) por municipio, sumado
 * de los puestos del DIVIPOL de la Registraduría. Lo usa el Análisis
 * electoral para saber dónde está la gente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipios', function (Blueprint $table) {
            $table->unsignedInteger('potencial_electoral')->nullable()->after('codigo_dane');
            $table->unsignedInteger('potencial_mujeres')->nullable()->after('potencial_electoral');
            $table->unsignedInteger('potencial_hombres')->nullable()->after('potencial_mujeres');
            $table->unsignedInteger('mesas')->nullable()->after('potencial_hombres');
            $table->unsignedSmallInteger('potencial_anio')->nullable()->after('mesas');
        });
    }

    public function down(): void
    {
        Schema::table('municipios', function (Blueprint $table) {
            $table->dropColumn(['potencial_electoral', 'potencial_mujeres', 'potencial_hombres', 'mesas', 'potencial_anio']);
        });
    }
};
