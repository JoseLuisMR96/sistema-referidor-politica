<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tema completo: además de botones y enlaces, el fondo, las tarjetas y los
 * textos de toda la app toman el color del candidato (ver App\Support\Marca).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('tema_completo')->default(false)->after('color_secundario');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('tema_completo');
        });
    }
};
