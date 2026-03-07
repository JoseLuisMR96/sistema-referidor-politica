<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referidores_pregoneros', function (Blueprint $table) {
            $table->string('celular', 20)
                  ->nullable()
                  ->after('cedula')
                  ->comment('Número de celular del referidor pregonero');
        });
    }

    public function down(): void
    {
        Schema::table('referidores_pregoneros', function (Blueprint $table) {
            $table->dropColumn('celular');
        });
    }
};