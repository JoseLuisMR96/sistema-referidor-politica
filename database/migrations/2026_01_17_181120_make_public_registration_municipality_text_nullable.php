<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('public_registrations', function (Blueprint $table) {
            // Requiere doctrine/dbal si vas a usar change() en algunos entornos
            $table->string('residence_municipality', 120)->nullable()->change();
            $table->string('voting_municipality', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('public_registrations', function (Blueprint $table) {
            $table->string('residence_municipality', 120)->nullable(false)->change();
            $table->string('voting_municipality', 120)->nullable(false)->change();
        });
    }
};
