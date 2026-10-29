<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cargo y lugar al que se postula cada candidato (su circunscripción).
 * Todo nullable: el "Tenant inicial" y los tenants viejos no lo tienen y se
 * completa desde la pantalla de tenants. Las reglas por cargo (qué es
 * obligatorio) viven en CargoEnum y en Livewire\Admin\Tenants\Index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('cargo', 30)->nullable()->after('descripcion');
            $table->foreignId('departamento_id')->nullable()->after('cargo')->constrained('departamentos')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->after('departamento_id')->constrained('municipios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('municipio_id');
            $table->dropConstrainedForeignId('departamento_id');
            $table->dropColumn('cargo');
        });
    }
};
