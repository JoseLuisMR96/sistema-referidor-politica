<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wpp_messages', function (Blueprint $table) {

            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('wpp_campaigns')
                ->cascadeOnDelete();

            $table->foreignId('contact_id')
                ->constrained('wpp_contacts')
                ->cascadeOnDelete();

            $table->string('phone');

            $table->text('message');

            $table->string('status')->default('pending');
            // pending
            // sending
            // sent
            // failed

            $table->string('provider_message_id')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->text('error')->nullable();

            $table->json('provider_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wpp_messages');
    }
};