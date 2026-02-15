<?php

// database/migrations/xxxx_xx_xx_create_municipios_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->string('departamento', 80)->default('META');
            $table->string('nombre', 120);
            $table->string('slug', 140)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['departamento', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
