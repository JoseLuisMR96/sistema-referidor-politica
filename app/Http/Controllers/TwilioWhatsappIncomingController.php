<?php

namespace App\Http\Controllers;

use App\Actions\WhatsApp\ProcessTwilioWebhookAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Mensajes entrantes de Twilio (respuestas del usuario).
 *
 * La firma se valida en el middleware `twilio.signature`: sin esa validación
 * el endpoint permitía inyectar respuestas de botones falsas.
 */
class TwilioWhatsappIncomingController extends Controller
{
    public function __construct(
        private ProcessTwilioWebhookAction $processWebhook,
    ) {}

    public function handle(Request $request)
    {
        $params = $request->all();

        if (empty($params['From']) || empty($params['MessageSid'])) {
            Log::warning('Mensaje entrante sin From o MessageSid');

            return response()->noContent();
        }

        Log::info('Mensaje entrante de Twilio', [
            'from' => $params['From'],
            'message_id' => $params['MessageSid'],
        ]);

        try {
            $this->processWebhook->execute(
                $params,
                $request->ip(),
                $request->userAgent(),
            );
        } catch (\Throwable $e) {
            Log::error('Error procesando mensaje entrante de Twilio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->noContent();
    }
}
