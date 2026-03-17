<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Fix campaign_metrics: Add default value to updated_at
        if (Schema::hasTable('campaign_metrics')) {
            // Drop and recreate the column with default value
            Schema::table('campaign_metrics', function (Blueprint $table) {
                // Change the column to have a default value
                $table->timestamp('updated_at')->useCurrent()->change();
            });
        }

        // 2. Add messaging_service_id to whatsapp_campaign_responses
        if (Schema::hasTable('whatsapp_campaign_responses')) {
            Schema::table('whatsapp_campaign_responses', function (Blueprint $table) {
                // Add MSID column after twilio_message_sid if it doesn't exist
                if (!Schema::hasColumn('whatsapp_campaign_responses', 'messaging_service_id')) {
                    $table->string('messaging_service_id', 100)->nullable()->after('twilio_message_sid');
                }
            });
        }
    }

    public function down(): void
    {
        // 1. Revert campaign_metrics updated_at change
        if (Schema::hasTable('campaign_metrics')) {
            Schema::table('campaign_metrics', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->change();
            });
        }

        // 2. Drop messaging_service_id from whatsapp_campaign_responses
        if (Schema::hasTable('whatsapp_campaign_responses')) {
            Schema::table('whatsapp_campaign_responses', function (Blueprint $table) {
                if (Schema::hasColumn('whatsapp_campaign_responses', 'messaging_service_id')) {
                    $table->dropColumn('messaging_service_id');
                }
            });
        }
    }
};
