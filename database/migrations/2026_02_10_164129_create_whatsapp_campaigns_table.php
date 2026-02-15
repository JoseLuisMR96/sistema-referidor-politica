<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['text', 'media', 'location', 'template'])->default('text');

            $table->text('body')->nullable();

            // Media
            $table->string('media_path')->nullable(); // storage path
            $table->string('media_mime')->nullable();

            // Ubicación (usaremos link)
            $table->string('location_label')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('location_url')->nullable(); // google maps

            // Template (si aplica)
            $table->string('template_name')->nullable();
            $table->json('template_variables')->nullable();

            $table->enum('status', ['draft', 'queued', 'sending', 'sent', 'finished', 'failed'])->default('draft');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
