<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Retell\CallResultController;
use App\Http\Controllers\TwilioWhatsappStatusController;

Route::post('/retell/call-result', [CallResultController::class, 'store'])
    ->name('retell.call-result');

Route::post('/twilio/whatsapp/status', [TwilioWhatsappStatusController::class, 'handle'])
    ->name('twilio.whatsapp.status');
