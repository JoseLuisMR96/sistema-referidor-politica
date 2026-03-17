<?php

namespace App\Http\Controllers;

use App\Actions\WhatsApp\CaptureButtonResponseAction;
use App\Actions\WhatsApp\UpdateMessageStatusAction;
use App\DTOs\ButtonResponseDTO;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

class TwilioWhatsappStatusController extends Controller
{
    public function __construct(
        private UpdateMessageStatusAction $updateStatus,
        private CaptureButtonResponseAction $captureButton,
    ) {}

    public function handle(Request $request)
    {
        $signature = $request->header('X-Twilio-Signature');
        $validator = new RequestValidator(config('services.twilio.token'));

        $url = $request->fullUrl();
        $params = $request->all();

        // 🔍 LOG TODO LO QUE LLEGA
        Log::info('=== WEBHOOK TWILIO RECIBIDO ===', [
            'url' => $url,
            'body' => $params['Body'] ?? 'N/A',
            'message_status' => $params['MessageStatus'] ?? 'N/A',
            'from' => $params['From'] ?? 'N/A',
        ]);

        if (!$validator->validate($signature, $url, $params)) {
            Log::warning('Invalid Twilio webhook signature', ['url' => $url]);
            return response('Invalid signature', 403);
        }

        try {
            // 1. Actualizar estado del mensaje usando nueva Action
            $message = $this->updateStatus->execute($params);

            Log::info('Message status updated', [
                'message_id' => $message?->id,
                'twilio_sid' => $params['MessageSid'] ?? 'unknown',
                'status' => $params['MessageStatus'] ?? 'unknown',
            ]);

            // 2. Detectar si es una respuesta de botón (incoming message con texto específico)
            // Twilio NO envía ButtonPayload para respuestas interactivas
            // En su lugar, devuelve el texto del botón como el body del mensaje
            $buttonId = $this->recognizeButtonResponse($params['Body'] ?? '');

            if ($buttonId && $message) {
                Log::info('🎯 BUTTON RESPONSE DETECTED FROM BODY!', [
                    'button_id' => $buttonId,
                    'body_text' => $params['Body'] ?? 'N/A',
                    'from' => $params['From'] ?? null,
                ]);

                // Crear DTO manualmente con los datos que tenemos
                $dto = new ButtonResponseDTO(
                    campaignId: $message->campaign_id,
                    campaignMessageId: $message->id,
                    phone: $params['From'] ?? 'unknown',
                    buttonId: $buttonId,
                    buttonText: $params['Body'] ?? null,
                    responseTimestamp: now(),
                    twiloSid: $params['MessageSid'] ?? 'unknown',
                    rawWebhook: $params,
                    ipAddress: $request->ip(),
                    userAgent: $request->header('User-Agent'),
                );

                $response = $this->captureButton->execute($dto);

                Log::info('✅ Button response captured', [
                    'response_id' => $response?->id,
                    'button_id' => $response?->button_id,
                ]);

                // Disparar evento para listeners
                if ($response) {
                    event(new \App\Events\ButtonResponseCaptured($response));
                }
            } else {
                Log::info('⚠️ Not a button response', [
                    'body' => $params['Body'] ?? 'N/A',
                    'message_status' => $params['MessageStatus'] ?? 'N/A',
                ]);
            }

            return response('ok', 200);

        } catch (\Exception $e) {
            Log::error('❌ Error processing Twilio webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('Error processing webhook', 500);
        }
    }

    /**
     * Reconoce si el body contiene un texto de botón conocido
     * y devuelve el ID del botón
     */
    private function recognizeButtonResponse(string $body): ?string
    {
        $body = strtolower(trim($body));

        // Mapear textos de botones a IDs
        $buttonMappings = [
            'paloma valencia' => 'palom',
            'paloma' => 'palom',
            'paloma valencia🕊️' => 'palom',
            
            'cepeda' => 'cepeda',
            'cepeda🎯' => 'cepeda',
            
            'otro candidato' => 'otro_candidato',
            'otro' => 'otro_candidato',
            'otro candidato🤝' => 'otro_candidato',
        ];

        foreach ($buttonMappings as $text => $buttonId) {
            if ($body === strtolower($text) || strpos($body, strtolower($text)) !== false) {
                return $buttonId;
            }
        }

        return null;
    }
}
