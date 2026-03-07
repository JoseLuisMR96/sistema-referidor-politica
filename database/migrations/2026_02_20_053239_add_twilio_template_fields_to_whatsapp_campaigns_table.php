<?php

// database/migrations/xxxx_xx_xx_add_twilio_template_fields_to_whatsapp_campaigns_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {

            if (!Schema::hasColumn('whatsapp_campaigns', 'messaging_service_sid')) {
                $table->string('messaging_service_sid', 64)->nullable()->after('location_url');
            }

            if (!Schema::hasColumn('whatsapp_campaigns', 'content_sid')) {
                $table->string('content_sid', 64)->nullable()->after('messaging_service_sid');
            }

            if (!Schema::hasColumn('whatsapp_campaigns', 'content_variables')) {
                $table->longText('content_variables')->nullable()->after('content_sid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_campaigns', 'content_variables')) {
                $table->dropColumn('content_variables');
            }
            if (Schema::hasColumn('whatsapp_campaigns', 'content_sid')) {
                $table->dropColumn('content_sid');
            }
            if (Schema::hasColumn('whatsapp_campaigns', 'messaging_service_sid')) {
                $table->dropColumn('messaging_service_sid');
            }
        });
    }
};
