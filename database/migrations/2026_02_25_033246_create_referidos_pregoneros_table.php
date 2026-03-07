<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('referidos_pregoneros', function (Blueprint $table) {
            $table->id();

            $table->foreignId('referidor_pregonero_id')
                ->constrained('referidores_pregoneros')
                ->cascadeOnDelete();

            $table->string('nombre', 150);
            $table->string('cedula', 30)->index();
            $table->string('puesto_votacion', 255);

            $table->timestamps();

            // Si quieres evitar duplicados por referidor (opcional pero recomendado):
            $table->unique(['referidor_pregonero_id', 'cedula'], 'uniq_referido_por_referidor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referidos_pregoneros');
    }
};