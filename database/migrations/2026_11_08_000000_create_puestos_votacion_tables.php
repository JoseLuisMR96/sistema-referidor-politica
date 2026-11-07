<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultados por puesto de votación (globales, sin tenant_id: son datos
 * públicos de la Registraduría, como historico_electoral).
 *
 * - puestos_votacion: catálogo (zona + código dentro del municipio), con el
 *   potencial del DIVIPOL.
 * - historico_puestos: votos por puesto y partido, sumando las mesas. Sin
 *   candidatos (ocuparía mucho); el blanco, los nulos y los no marcados van
 *   como "partidos" VOTO EN BLANCO, VOTOS NULOS y VOTOS NO MARCADOS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puestos_votacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('zona', 4);
            $table->string('codigo', 4);
            $table->string('nombre', 150);
            $table->string('comuna', 120)->nullable();
            $table->unsignedInteger('potencial')->nullable();
            $table->unsignedInteger('potencial_mujeres')->nullable();
            $table->unsignedSmallInteger('mesas')->nullable();
            $table->unsignedSmallInteger('potencial_anio')->nullable();
            $table->timestamps();

            $table->unique(['municipio_id', 'zona', 'codigo'], 'puestos_lugar');
        });

        Schema::create('historico_puestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->string('cargo', 20);
            $table->unsignedTinyInteger('vuelta')->default(1);
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->foreignId('puesto_id')->constrained('puestos_votacion')->cascadeOnDelete();
            $table->string('partido', 190);
            $table->unsignedInteger('votos');

            $table->index(['municipio_id', 'cargo', 'anio', 'vuelta'], 'historico_puestos_busqueda');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_puestos');
        Schema::dropIfExists('puestos_votacion');
    }
};
