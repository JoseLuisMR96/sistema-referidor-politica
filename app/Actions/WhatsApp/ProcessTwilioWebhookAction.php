<?php

namespace App\Actions\WhatsApp;

use App\DTOs\ButtonResponseDTO;
use App\Enums\ButtonIdEnum;
use App\Events\ButtonResponseCaptured;
use App\Models\CampaignMessage;
use App\Models\WhatsAppCampaignResponse;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * Procesa un webhook de Twilio: actualiza el estado del mensaje y, si es una
 * respuesta de botón, la captura y dispara el evento.
 *
 * Los tres endpoints de webhook (/api/twilio/whatsapp/status,
 * /api/twilio/whatsapp/incoming y /api/whatsapp/webhook/status) delegan aquí,
 * así da igual cuál configure Twilio: todos se comportan igual.
 */
class ProcessTwilioWebhookAction
{
    public function __construct(
        private UpdateMessageStatusAction $updateStatus,
        private CaptureButtonResponseAction $captureButton,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Payload crudo del webhook
     */
    public function execute(
        array $payload,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ?WhatsAppCampaignResponse {
        // 1. Status callback: actualiza el mensaje buscándolo por MessageSid.
        $message = $this->updateStatus->execute($payload);

        // 2. Mensaje entrante: el MessageSid es nuevo, hay que buscar el
        //    original por el SID al que responde.
        if (! $message) {
            $message = $this->findRepliedMessage($payload);
        }

        // 3. Reconocer el botón, por payload explícito o por el texto del body.
        $button = $this->detectButton($payload);

        if (! $button) {
            return null;
        }

        if (! $message) {
            Log::warning('Respuesta de botón sin mensaje original identificable', [
                'button_id' => $button->value,
                'message_sid' => $payload['MessageSid'] ?? null,
                'original_sid' => $payload['OriginalRepliedMessageSid'] ?? null,
            ]);

            return null;
        }

        // 4. Capturar (la Action deduplica) y notificar a los listeners.
        $dto = new ButtonResponseDTO(
            campaignId: $message->campaign_id,
            campaignMessageId: $message->id,
            phone: PhoneNumber::fromWhatsApp($payload['From'] ?? '') ?? 'unknown',
            buttonId: $button->value,
            buttonText: $payload['Body'] ?? $button->label(),
            responseTimestamp: now(),
            twiloSid: $payload['MessageSid'] ?? 'unknown',
            rawWebhook: $payload,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            messagingServiceId: $payload['MessagingServiceSid'] ?? null,
        );

        $response = $this->captureButton->execute($dto);

        // Solo respuestas nuevas: ante un duplicado la Action devuelve la fila
        // existente y re-disparar el evento contaba el voto otra vez y
        // reenviaba (y cobraba) el agradecimiento.
        if ($response?->wasRecentlyCreated) {
            ButtonResponseCaptured::dispatch($response);
        }

        return $response;
    }

    /**
     * Busca el mensaje original al que responde un mensaje entrante.
     *
     * @param  array<string, mixed>  $payload
     */
    private function findRepliedMessage(array $payload): ?CampaignMessage
    {
        $originalSid = $payload['OriginalRepliedMessageSid'] ?? null;

        if (! $originalSid) {
            return null;
        }

        return CampaignMessage::query()
            ->where('twilio_sid', $originalSid)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function detectButton(array $payload): ?ButtonIdEnum
    {
        $explicit = $payload['ButtonPayload'] ?? null;

        if (is_string($explicit) && ButtonIdEnum::tryFrom($explicit)) {
            return ButtonIdEnum::from($explicit);
        }

        return ButtonIdEnum::fromText($payload['Body'] ?? null);
    }
}
