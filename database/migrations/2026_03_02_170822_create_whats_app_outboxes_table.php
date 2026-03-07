<?php

// database/migrations/xxxx_xx_xx_create_whatsapp_outbox_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->text('message');

            $table->enum('status', ['PENDING', 'RESERVED', 'SENT', 'FAILED', 'CANCELLED'])->default('PENDING');
            $table->unsignedInteger('attempts')->default(0);

            $table->string('reserved_by', 64)->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['status', 'reserved_at']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_outbox');
    }
};
