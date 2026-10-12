<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Análisis de redes sociales por candidato.
 *
 * - integraciones: credenciales GLOBALES por proveedor (p. ej. la app de Meta).
 *   `credenciales` va cifrado con APP_KEY (cast encrypted:array): si se rota
 *   APP_KEY hay que volver a cargarlas.
 * - redes_cuentas: las cuentas de cada candidato (tenant) a medir. El token es
 *   opcional y también cifrado: solo existe cuando el candidato da acceso.
 * - redes_metricas: una foto por cuenta y día (seguidores, publicaciones,
 *   interacciones). De ahí sale el crecimiento semanal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integraciones', function (Blueprint $table) {
            $table->id();
            $table->string('proveedor', 30)->unique(); // meta
            $table->text('credenciales')->nullable();   // JSON cifrado
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('redes_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('red', 20);                  // facebook | instagram
            $table->string('url', 500)->nullable();     // perfil público
            $table->string('usuario', 150);             // @usuario de IG o id/alias de la página de FB
            $table->string('external_id', 100)->nullable(); // id en Meta, cuando se conoce
            $table->text('access_token')->nullable();   // cifrado; solo con acceso del candidato
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_sync_at')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'red', 'usuario']);
        });

        Schema::create('redes_metricas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('red_cuenta_id')->constrained('redes_cuentas')->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedBigInteger('seguidores')->nullable();
            $table->unsignedInteger('publicaciones')->nullable();
            // Me gusta + comentarios de las publicaciones de los últimos 7 días
            $table->unsignedInteger('interacciones_7d')->nullable();
            $table->json('datos')->nullable();          // respuesta resumida, para análisis futuros
            $table->timestamps();

            $table->unique(['red_cuenta_id', 'fecha']);
            $table->index(['tenant_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redes_metricas');
        Schema::dropIfExists('redes_cuentas');
        Schema::dropIfExists('integraciones');
    }
};
