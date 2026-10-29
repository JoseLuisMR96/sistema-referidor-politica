<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rivales y tono de los comentarios.
 *
 * - redes_cuentas.tipo: 'propia' (del candidato) o 'rival' (otro candidato al
 *   mismo cargo, medido con datos públicos para comparar).
 * - redes_tono: conteo DIARIO de comentarios a favor / en contra / neutros y
 *   temas mencionados. Solo cifras agregadas: nunca se guarda el texto de un
 *   comentario ni quién lo escribió (la opinión política es dato sensible,
 *   Ley 1581).
 * - redes_comentarios_vistos: huella HMAC del id de cada comentario ya
 *   clasificado, para no cobrarlo dos veces. No es reversible: no permite
 *   volver al comentario ni a su autor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redes_cuentas', function (Blueprint $table) {
            $table->string('tipo', 10)->default('propia')->after('red');
            $table->string('etiqueta', 150)->nullable()->after('tipo'); // nombre del rival
        });

        Schema::create('redes_tono', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('red_cuenta_id')->constrained('redes_cuentas')->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('a_favor')->default(0);
            $table->unsignedInteger('en_contra')->default(0);
            $table->unsignedInteger('neutro')->default(0);
            $table->json('temas')->nullable(); // {"seguridad": 12, "vías": 5}
            $table->timestamps();

            $table->unique(['red_cuenta_id', 'fecha']);
            $table->index(['tenant_id', 'fecha']);
        });

        Schema::create('redes_comentarios_vistos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('red_cuenta_id')->constrained('redes_cuentas')->cascadeOnDelete();
            $table->char('huella', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['red_cuenta_id', 'huella']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redes_comentarios_vistos');
        Schema::dropIfExists('redes_tono');

        Schema::table('redes_cuentas', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'etiqueta']);
        });
    }
};
