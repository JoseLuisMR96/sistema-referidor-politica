<?php

namespace App\Http\Controllers;

use App\Actions\WhatsApp\ProcessTwilioWebhookAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Status callback de Twilio: es la URL configurada en la consola
 * (ver WEBHOOK_SETUP.md).
 *
 * La firma se valida en el middleware `twilio.signature` y el procesamiento
 * vive en ProcessTwilioWebhookAction, compartido con los otros endpoints.
 */
class TwilioWhatsappStatusController extends Controller
{
    public function __construct(
        private ProcessTwilioWebhookAction $processWebhook,
    ) {}

    public function handle(Request $request)
    {
        $params = $request->all();

        Log::info('Webhook Twilio (status) recibido', [
            'message_status' => $params['MessageStatus'] ?? null,
            'message_sid' => $params['MessageSid'] ?? null,
        ]);

        try {
            $this->processWebhook->execute(
                $params,
                $request->ip(),
                $request->userAgent(),
            );

            return response('ok', 200);
        } catch (\Throwable $e) {
            Log::error('Error procesando webhook Twilio (status)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Error processing webhook', 500);
        }
    }
}
