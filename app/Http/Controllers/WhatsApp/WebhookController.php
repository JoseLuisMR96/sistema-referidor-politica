<?php

namespace App\Http\Controllers\WhatsApp;

use App\Actions\WhatsApp\ProcessTwilioWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de Twilio de la arquitectura de campañas refactorizada.
 *
 * Comparte el procesamiento con los endpoints /api/twilio/whatsapp/*, así que
 * reconoce tanto ButtonPayload como el texto del botón en el body.
 * La firma se valida en el middleware `twilio.signature`.
 */
class WebhookController extends Controller
{
    public function __construct(
        private ProcessTwilioWebhookAction $processWebhook,
    ) {}

    public function handle(Request $request): \Illuminate\Http\Response
    {
        try {
            $this->processWebhook->execute(
                $request->all(),
                $request->ip(),
                $request->userAgent(),
            );

            return response('ok', 200);
        } catch (\Throwable $e) {
            Log::error('Error procesando webhook Twilio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Error processing webhook', 500);
        }
    }
}
