<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('imported_people', function (Blueprint $table) {
            $table->id();

            $table->string('full_name', 190);
            $table->string('phone', 30)->nullable();
            $table->string('document_number', 30); // cédula
            $table->string('voting_place', 190)->nullable(); // puesto de votación

            // opcional pero muy útil para gráficos por municipio
            $table->string('voting_municipality', 120)->nullable();

            // trazabilidad del cargue (excel)
            $table->string('batch_id', 60)->nullable(); // ej: EXCEL_2026_01_15_A
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['batch_id']);
            $table->index(['voting_municipality']);
            $table->unique(['document_number']); // si NO quieres repetir cédulas
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_people');
    }
};
