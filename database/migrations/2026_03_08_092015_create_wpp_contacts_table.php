<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wpp_contacts', function (Blueprint $table) {
            $table->id();

            $table->string('name')->nullable();
            $table->string('phone')->unique();

            $table->boolean('opt_in')->default(true);

            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wpp_contacts');
    }
};