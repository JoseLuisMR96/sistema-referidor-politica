<?php

namespace App\Actions\WhatsApp;

use App\Models\CampaignMessage;
use Illuminate\Support\Facades\Log;

/**
 * Action para actualizar el estado de un mensaje desde webhooks
 */
class UpdateMessageStatusAction
{
    /**
     * Actualiza el estado de un mensaje basado en la respuesta de Twilio
     *
     * @param array $payload Webhook payload de Twilio
     * @return CampaignMessage|null
     */
    public function execute(array $payload): ?CampaignMessage
    {
        $messageSid = $payload['MessageSid'] ?? null;
        $status = $payload['MessageStatus'] ?? null;
        $errorCode = $payload['ErrorCode'] ?? null;
        $errorMessage = $payload['ErrorMessage'] ?? null;

        if (!$messageSid || !$status) {
            Log::warning('Invalid webhook payload', $payload);
            return null;
        }

        // Buscar mensaje por twilio_sid
        $message = CampaignMessage::query()
            ->where('twilio_sid', $messageSid)
            ->first();

        if (!$message) {
            Log::warning('Message not found for webhook', ['twilio_sid' => $messageSid]);
            return null;
        }

        // Actualizar estado
        $message->update([
            'status' => $status,
            'error_code' => $errorCode ? (string) $errorCode : null,
            'error_message' => $errorMessage ?: null,
            'last_status_at' => now(),
            'raw_webhook' => $payload,
        ]);

        // Actualizar delivered_at si corresponde
        if ($status === 'delivered' && !$message->delivered_at) {
            $message->markAsDelivered();
        }

        Log::info('Message status updated', [
            'message_id' => $message->id,
            'status' => $status,
        ]);

        return $message;
    }
}
