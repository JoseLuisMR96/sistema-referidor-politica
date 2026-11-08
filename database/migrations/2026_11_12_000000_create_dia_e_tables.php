<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Día de elecciones: testigos de la campaña en puestos y mesas, que reportan
 * los votos de cada mesa (formulario E-14) desde un enlace personal. Da un
 * conteo propio en vivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elecciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('nombre', 150);
            $table->string('cargo', 20);
            $table->date('fecha');
            // Abierta = los testigos pueden reportar
            $table->boolean('abierta')->default(true);
            $table->timestamps();
        });

        // Candidatos o listas que se cuentan en el E-14
        Schema::create('eleccion_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('partido', 150)->nullable();
            $table->boolean('propia')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
        });

        Schema::create('testigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('telefono', 20)->nullable();
            $table->foreignId('puesto_id')->constrained('puestos_votacion');
            $table->string('mesas', 120); // "1-5, 8"
            $table->string('token', 64)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('reportes_mesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('eleccion_id')->constrained('elecciones')->cascadeOnDelete();
            $table->foreignId('testigo_id')->nullable()->constrained('testigos')->nullOnDelete();
            $table->foreignId('puesto_id')->constrained('puestos_votacion');
            $table->unsignedSmallInteger('mesa');
            $table->json('votos'); // opcion_id => votos
            $table->unsignedInteger('blanco')->default(0);
            $table->unsignedInteger('nulos')->default(0);
            $table->unsignedInteger('no_marcados')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->string('foto_ruta', 255)->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();

            // Una mesa se reporta una vez; corregir la reemplaza
            $table->unique(['eleccion_id', 'puesto_id', 'mesa'], 'reporte_mesa_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_mesa');
        Schema::dropIfExists('testigos');
        Schema::dropIfExists('eleccion_opciones');
        Schema::dropIfExists('elecciones');
    }
};
