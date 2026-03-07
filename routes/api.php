<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Retell\CallResultController;
use App\Http\Controllers\TwilioWhatsappStatusController;
use App\Http\Controllers\WhatsAppWorkerController;

Route::post('/retell/call-result', [CallResultController::class, 'store'])
    ->name('retell.call-result');

Route::post('/twilio/whatsapp/status', [TwilioWhatsappStatusController::class, 'handle'])
    ->name('twilio.whatsapp.status');
    
Route::get('/whatsapp/pull', [WhatsAppWorkerController::class, 'pull'])
    ->name('api.whatsapp.pull');

Route::post('/whatsapp/report', [WhatsAppWorkerController::class, 'report'])
    ->name('api.whatsapp.report');
