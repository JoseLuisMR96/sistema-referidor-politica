<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('call_results', function (Blueprint $table) {
            $table->id();

            // Identificadores de Retell (si los tienes)
            $table->string('provider')->default('retell');
            $table->string('call_id')->nullable()->index();
            $table->string('agent_id')->nullable()->index();

            // Datos capturados
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('senate_candidate')->nullable();
            $table->string('camara_candidate')->nullable();
            // Metadata útil
            $table->string('status')->nullable(); // completed, hangup, etc.
            $table->integer('duration_sec')->nullable();
            $table->string('recording_url')->nullable();
            $table->text('notes')->nullable();
            $table->string('condition_candidate_senate')->nullable();
            $table->string('condition_candidate_camara')->nullable();

            // Para auditoría / debugging
            $table->json('raw_payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_results');
    }
};
