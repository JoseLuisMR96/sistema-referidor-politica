<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('referidores_pregoneros', function (Blueprint $table) {
            $table->id();

            $table->uuid('id_unico')->unique();

            $table->string('nombre', 150);
            $table->string('cedula', 30)->index();
            $table->string('puesto_votacion', 255);

            $table->decimal('monto_pagar', 12, 2)->nullable();
            $table->boolean('pago_realizado')->nullable(); 
            $table->timestamp('hora_pago')->nullable();

            $table->string('imagen_pago', 2048)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referidores_pregoneros');
    }
};