<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultados electorales históricos (datos públicos de la Registraduría).
 * Son globales: no llevan tenant_id, todos los candidatos ven lo mismo.
 *
 * Una fila = votos de un candidato o de una lista (partido) en un lugar.
 * - Gobernación / Asamblea: una fila por municipio; el total del
 *   departamento es la suma.
 * - Alcaldía / Concejo: una fila por municipio.
 * - JAL: una fila por comuna del municipio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_electoral', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->string('cargo', 20); // CargoEnum
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->string('comuna', 120)->nullable();
            $table->string('partido', 190);
            $table->string('candidato', 190)->nullable(); // vacío = voto por la lista
            $table->unsignedInteger('votos');
            $table->boolean('elegido')->nullable();
            $table->unsignedSmallInteger('curules')->nullable();
            $table->timestamps();

            $table->index(['anio', 'cargo', 'departamento_id', 'municipio_id'], 'historico_busqueda');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_electoral');
    }
};
