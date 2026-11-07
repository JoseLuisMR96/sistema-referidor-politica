<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos de campaña: trazabilidad de dónde ha estado el candidato o su
 * equipo (emisarios), con lugar, dinero asignado y evidencias.
 *
 * Todo lleva tenant_id: es del candidato. Al eliminar un candidato se borra
 * con EliminarTenant (y sus archivos de evidencia).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Personas que trabajan por el candidato (emisarios, coordinadores...)
        Schema::create('equipo_campana', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('nombre', 150);
            $table->string('rol', 30);
            $table->string('telefono', 20)->nullable();
            $table->string('notas', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'activo']);
        });

        Schema::create('eventos_campana', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('titulo', 150);
            $table->string('tipo', 30);
            $table->string('estado', 20)->default('realizado');
            $table->dateTime('fecha');
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos');
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->string('lugar', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->boolean('asistio_candidato')->default(false);
            // Quien responde por el evento y recibe el dinero asignado
            $table->foreignId('responsable_id')->nullable()->constrained('equipo_campana')->nullOnDelete();
            $table->unsignedInteger('asistentes')->nullable();
            $table->decimal('monto_asignado', 14, 2)->default(0);
            $table->decimal('monto_gastado', 14, 2)->nullable();
            $table->text('descripcion')->nullable();
            $table->text('resultado')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'fecha']);
            $table->index(['tenant_id', 'municipio_id']);
        });

        // Otros integrantes del equipo que fueron al evento
        Schema::create('evento_integrante', function (Blueprint $table) {
            $table->foreignId('evento_id')->constrained('eventos_campana')->cascadeOnDelete();
            $table->foreignId('integrante_id')->constrained('equipo_campana')->cascadeOnDelete();

            $table->primary(['evento_id', 'integrante_id']);
        });

        // Fotos y soportes (facturas, listas, actas). Disco privado.
        Schema::create('evidencias_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('evento_id')->constrained('eventos_campana')->cascadeOnDelete();
            $table->string('tipo', 20); // foto | documento
            $table->string('ruta', 255);
            $table->string('nombre', 190);
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('tamano')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias_evento');
        Schema::dropIfExists('evento_integrante');
        Schema::dropIfExists('eventos_campana');
        Schema::dropIfExists('equipo_campana');
    }
};
