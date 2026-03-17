<?php

use App\Http\Controllers\Retell\CallResultController;
use App\Http\Controllers\TwilioWhatsappStatusController;
use App\Http\Controllers\TwilioWhatsappIncomingController;
use App\Http\Controllers\WhatsAppWorkerController;
use App\Http\Controllers\WppCampaignController;
use Illuminate\Support\Facades\Route;

Route::post('/retell/call-result', [CallResultController::class, 'store'])
    ->name('retell.call-result');

Route::post('/wpp/campaigns/send', [WppCampaignController::class, 'send']);

Route::post('/twilio/whatsapp/status', [TwilioWhatsappStatusController::class, 'handle'])
    ->name('twilio.whatsapp.status');

Route::post('/twilio/whatsapp/incoming', [TwilioWhatsappIncomingController::class, 'handle'])
    ->name('twilio.whatsapp.incoming');

Route::get('/whatsapp/pull', [WhatsAppWorkerController::class, 'pull'])
    ->name('api.whatsapp.pull');

Route::post('/whatsapp/report', [WhatsAppWorkerController::class, 'report'])
    ->name('api.whatsapp.report');

// Nuevas rutas para arquitectura de campañas refactorizada
require base_path('routes/campaigns-api.php');
