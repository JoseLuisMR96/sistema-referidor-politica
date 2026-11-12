<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publicaciones de Instagram del candidato y de sus rivales, para ver qué
 * contenido funciona (tipo, día y hora, tema).
 *
 * Son publicaciones públicas de cuentas de candidatos: se guarda su texto y
 * sus conteos, nunca comentarios ni datos de quien interactúa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redes_publicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('red_cuenta_id')->constrained('redes_cuentas')->cascadeOnDelete();
            $table->string('media_id', 64);
            $table->string('tipo', 20)->nullable(); // IMAGE, VIDEO, CAROUSEL_ALBUM
            $table->timestamp('publicada_at')->nullable();
            $table->text('texto')->nullable();
            $table->string('enlace', 500)->nullable();
            $table->unsignedInteger('me_gusta')->nullable(); // null si el dueño los oculta
            $table->unsignedInteger('comentarios')->nullable();
            $table->unsignedInteger('reproducciones')->nullable(); // solo reels de cuentas ajenas
            $table->string('tema', 40)->nullable(); // lo pone la IA (ClasificadorTemas)
            $table->timestamps();

            $table->unique(['red_cuenta_id', 'media_id']);
            $table->index(['tenant_id', 'publicada_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redes_publicaciones');
    }
};
