<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('municipios', function (Blueprint $table) {
            $table->string('codigo_dane', 10)->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('municipios', function (Blueprint $table) {
            $table->dropColumn('codigo_dane');
        });
    }
};
