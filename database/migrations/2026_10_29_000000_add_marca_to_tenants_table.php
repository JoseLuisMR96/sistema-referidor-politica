<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidad visual de cada candidato: con su tenant elegido, toda la app y
 * sus formularios públicos se pintan con sus colores, logo, foto y slogan.
 * Todo nullable: sin marca, la app se ve con el diseño por defecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('partido', 150)->nullable()->after('descripcion');
            $table->string('slogan', 200)->nullable()->after('partido');
            $table->char('color_primario', 7)->nullable()->after('slogan');   // #RRGGBB
            $table->char('color_secundario', 7)->nullable()->after('color_primario');
            $table->string('logo_path', 255)->nullable()->after('color_secundario');
            $table->string('foto_path', 255)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['partido', 'slogan', 'color_primario', 'color_secundario', 'logo_path', 'foto_path']);
        });
    }
};
