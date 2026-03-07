<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->boolean('use_twilio_campaign')->default(false)->after('location_url');
            $table->string('twilio_mode', 20)->default('custom')->after('use_twilio_campaign'); // custom|template
            $table->string('messaging_service_sid', 64)->nullable()->after('twilio_mode'); // MGxxxxxxxx
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropColumn(['use_twilio_campaign', 'twilio_mode', 'messaging_service_sid']);
        });
    }
};
