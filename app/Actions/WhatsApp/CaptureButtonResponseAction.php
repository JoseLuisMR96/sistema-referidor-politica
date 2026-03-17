<?php

namespace App\Actions\WhatsApp;

use App\DTOs\ButtonResponseDTO;
use App\Models\CampaignMessage;
use App\Models\WhatsAppCampaignResponse;
use Illuminate\Support\Facades\Log;

/**
 * Action para capturar respuestas de botones desde webhooks de Twilio
 * Valida, deduplicata e inserta la respuesta en la BD
 */
class CaptureButtonResponseAction
{
    /**
     * Ejecuta la captura de respuesta de botón
     *
     * @param ButtonResponseDTO $dto
     * @return WhatsAppCampaignResponse|null
     */
    public function execute(ButtonResponseDTO $dto): ?WhatsAppCampaignResponse
    {
        try {
            // 1. Buscar mensaje original
            $message = CampaignMessage::query()
                ->where('campaign_id', $dto->campaignId)
                ->where('id', $dto->campaignMessageId)
                ->first();

            if (!$message) {
                Log::warning('Button response without original message', [
                    'campaign_id' => $dto->campaignId,
                    'message_id' => $dto->campaignMessageId,
                ]);
                return null;
            }

            // 2. Buscar respuesta duplicada (idempotencia)
            $existing = WhatsAppCampaignResponse::query()
                ->where('campaign_id', $dto->campaignId)
                ->where('campaign_message_id', $dto->campaignMessageId)
                ->where('phone', $dto->phone)
                ->where('button_id', $dto->buttonId)
                ->first();

            if ($existing) {
                Log::info('Duplicate button response detected and ignored', [
                    'response_id' => $existing->id,
                ]);
                return $existing;
            }

            // 3. Crear respuesta
            $response = WhatsAppCampaignResponse::create([
                'campaign_id' => $dto->campaignId,
                'campaign_message_id' => $dto->campaignMessageId,
                'phone' => $dto->phone,
                'referrer_id' => $message->referrer_id,
                'referidor_pregonero_id' => $message->referidor_pregonero_id,
                'button_id' => $dto->buttonId,
                'button_text' => $dto->buttonText,
                'response_timestamp' => $dto->responseTimestamp,
                'twilio_message_sid' => $dto->twiloSid,
                'messaging_service_id' => $dto->messagingServiceId,
                'raw_webhook' => $dto->rawWebhook,
                'ip_address' => $dto->ipAddress,
                'user_agent' => $dto->userAgent,
            ]);

            Log::info('Button response captured', [
                'response_id' => $response->id,
                'campaign_id' => $response->campaign_id,
                'button_id' => $response->button_id,
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Error capturing button response', [
                'error' => $e->getMessage(),
                'dto' => (array) $dto,
            ]);
            return null;
        }
    }
}
