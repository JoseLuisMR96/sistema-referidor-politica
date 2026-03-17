<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wpp_campaigns', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('message');

            $table->string('session')->default('nerdwhats1');

            $table->integer('total_contacts')->default(0);
            $table->integer('sent')->default(0);
            $table->integer('failed')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wpp_campaigns');
    }
};