<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();      // META, CUNDINAMARCA...
            $table->string('slug', 100)->unique();       // meta, cundinamarca...
            $table->string('codigo_dane', 10)->nullable()->index(); // opcional
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
