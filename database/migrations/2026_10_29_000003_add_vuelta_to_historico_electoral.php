<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presidencia puede ir a segunda vuelta: sin esta columna, cargar la
 * segunda borraría la primera. El resto de cargos queda en 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historico_electoral', function (Blueprint $table) {
            $table->unsignedTinyInteger('vuelta')->default(1)->after('cargo');
        });
    }

    public function down(): void
    {
        Schema::table('historico_electoral', function (Blueprint $table) {
            $table->dropColumn('vuelta');
        });
    }
};
