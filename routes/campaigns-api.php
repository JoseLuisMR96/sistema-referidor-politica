<?php

use App\Http\Controllers\Campaign\CampaignController;
use App\Http\Controllers\WhatsApp\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Nuevas Rutas API para Campañas Refactorizadas
|--------------------------------------------------------------------------
| 
| Estos endpoints reemplazan gradualmente los antiguos y forman parte de
| la nueva arquitectura escalable para campaña masivas de WhatsApp.
| 
| Incluye en routes/api.php:
| require base_path('routes/campaign-api.php');
|
*/

Route::middleware('auth:api')->group(function () {
    // Endpoints de campaña
    Route::post('/campaigns', [CampaignController::class, 'store'])
        ->name('api.campaigns.store');

    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])
        ->name('api.campaigns.show');

    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])
        ->name('api.campaigns.pause');

    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume'])
        ->name('api.campaigns.resume');

    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])
        ->name('api.campaigns.cancel');

    Route::get('/campaigns/{campaign}/stats', [CampaignController::class, 'stats'])
        ->name('api.campaigns.stats');

    Route::get('/campaigns/{campaign}/rate-limit-status', [
        CampaignController::class,
        'rateLimitStatus',
    ])
        ->name('api.campaigns.rate-limit-status');
});

// Webhook de Twilio (sin autenticación, pero validado con firma)
Route::post('/whatsapp/webhook/status', [WebhookController::class, 'handle'])
    ->name('api.whatsapp.webhook.status');
