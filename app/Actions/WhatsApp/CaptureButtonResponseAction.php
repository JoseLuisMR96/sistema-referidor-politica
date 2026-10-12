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

            // 2. Una sola respuesta por persona y campaña: la primera gana.
            //    Cubre el reintento de Twilio, el doble toque del mismo botón
            //    y que la persona pulse otro candidato después.
            //    Se devuelve la fila existente (wasRecentlyCreated = false) para
            //    que quien llama NO vuelva a disparar el evento.
            $existing = WhatsAppCampaignResponse::query()
                ->where('campaign_id', $dto->campaignId)
                ->where('phone', $dto->phone)
                ->first();

            if ($existing) {
                Log::info('Respuesta repetida ignorada', [
                    'response_id' => $existing->id,
                    'button_id_original' => $existing->button_id,
                    'button_id_nuevo' => $dto->buttonId,
                ]);
                return $existing;
            }

            // 3. Crear respuesta
            // Los webhooks no tienen usuario ni tenant en contexto: el tenant
            // sale del mensaje original.
            $response = WhatsAppCampaignResponse::create([
                'tenant_id' => $message->tenant_id,
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
                'campaign_id' => $dto->campaignId,
                'message_id' => $dto->campaignMessageId,
            ]);
            return null;
        }
    }
}
