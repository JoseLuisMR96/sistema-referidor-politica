<?php

namespace App\Http\Controllers\WhatsApp;

use App\Actions\WhatsApp\CaptureButtonResponseAction;
use App\Actions\WhatsApp\UpdateMessageStatusAction;
use App\DTOs\ButtonResponseDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

/**
 * Controller para manejar webhooks de Twilio
 * Recibe actualizaciones de estado de mensajes y respuestas de botones
 */
class WebhookController extends Controller
{
    public function __construct(
        private UpdateMessageStatusAction $updateStatus,
        private CaptureButtonResponseAction $captureButton,
    ) {}

    /**
     * Maneja webhook de Twilio para estado de mensajes y respuestas
     */
    public function handle(
        Request $request,
    ): \Illuminate\Http\Response {
        // 1. Validar firma de Twilio
        $signature = $request->header('X-Twilio-Signature');
        $validator = new RequestValidator(config('services.twilio.token'));
        $url = $request->fullUrl();
        $params = $request->all();

        if (!$validator->validate($signature, $url, $params)) {
            Log::warning('Invalid Twilio webhook signature', ['url' => $url]);
            return response('Invalid signature', 403);
        }

        try {
            // 2. Actualizar estado del mensaje
            $message = $this->updateStatus->execute($params);

            // 3. Si hay respuesta de botón, capturarla
            if (!empty($params['ButtonPayload']) && $message) {
                $dto = ButtonResponseDTO::fromTwilioPayload(
                    $params,
                    $message->campaign_id,
                    $message->id
                );

                $response = $this->captureButton->execute($dto);

                // Disparar evento para listeners
                if ($response) {
                    event(new \App\Events\ButtonResponseCaptured($response));
                }
            }

            return response('ok', 200);

        } catch (\Exception $e) {
            Log::error('Error processing Twilio webhook', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            return response('Error processing webhook', 500);
        }
    }
}
