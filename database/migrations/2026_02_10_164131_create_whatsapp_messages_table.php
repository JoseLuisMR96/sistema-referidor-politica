<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('whatsapp_campaigns')->cascadeOnDelete();

            $table->string('to'); // whatsapp:+57...
            $table->string('contact_name')->nullable();

            $table->string('twilio_sid')->nullable()->index();
            $table->enum('status', [
                'created','queued','sending','sent','delivered','read','undelivered','failed'
            ])->default('created');

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_status_at')->nullable();

            $table->json('raw_webhook')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
