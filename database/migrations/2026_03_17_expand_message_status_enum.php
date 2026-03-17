<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Expande los enums de status para incluir todos los estados de Twilio
     */
    public function up(): void
    {
        // Twilio states: queued, accepted, sent, delivered, undelivered, failed, read
        $statuses = ['queued', 'accepted', 'sent', 'delivered', 'undelivered', 'failed', 'read', 'pending'];
        $statusesStr = implode("','", $statuses);

        if (Schema::hasTable('campaign_messages')) {
            // First, ensure only valid status values exist
            DB::statement("UPDATE `campaign_messages` SET `status` = 'pending' WHERE `status` NOT IN ('" . $statusesStr . "') OR `status` IS NULL");
            
            // Then change the column type to ENUM with all valid values
            DB::statement("ALTER TABLE `campaign_messages` MODIFY COLUMN `status` ENUM('" . $statusesStr . "') DEFAULT 'pending'");
        }

        if (Schema::hasTable('campaigns')) {
            $campaignStatuses = ['queued', 'active', 'paused', 'completed', 'cancelled', 'failed', 'pending', 'resumed'];
            $campaignStatusesStr = implode("','", $campaignStatuses);
            
            // First, update any invalid status values to a valid default
            DB::statement("UPDATE `campaigns` SET `status` = 'pending' WHERE `status` NOT IN ('" . $campaignStatusesStr . "') OR `status` IS NULL");
            
            // Then change the column type to ENUM
            DB::statement("ALTER TABLE `campaigns` MODIFY COLUMN `status` ENUM('" . $campaignStatusesStr . "') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        // Safely revert to VARCHAR to avoid data conflicts
        if (Schema::hasTable('campaign_messages')) {
            DB::statement("ALTER TABLE `campaign_messages` MODIFY COLUMN `status` VARCHAR(255) DEFAULT 'pending'");
        }

        if (Schema::hasTable('campaigns')) {
            DB::statement("ALTER TABLE `campaigns` MODIFY COLUMN `status` VARCHAR(255) DEFAULT 'queued'");
        }
    }
};
