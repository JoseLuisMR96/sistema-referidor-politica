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
     * Orden de avance de cada estado que acepta campaign_messages.status.
     *
     * Twilio no garantiza el orden de los callbacks: un 'sent' tardío no
     * debe pisar un 'delivered' o un 'read' que ya llegó. Los estados de
     * fallo solo se aceptan si el mensaje todavía no fue entregado.
     */
    private const ORDEN = [
        'pending' => 0,
        'queued' => 1,
        'accepted' => 1,
        'sent' => 2,
        'failed' => 3,
        'undelivered' => 3,
        'delivered' => 4,
        'read' => 5,
    ];

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
            // Los mensajes entrantes no traen MessageStatus. No se registra el
            // payload completo: trae el teléfono y el texto de la persona.
            Log::debug('Webhook sin MessageSid o MessageStatus', ['message_sid' => $messageSid]);
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

        if (! array_key_exists($status, self::ORDEN)) {
            // 'sending', 'scheduled', 'canceled'… no existen en el ENUM de
            // MySQL: escribirlos reventaba el webhook.
            Log::info('Estado de Twilio no manejado, se ignora', [
                'message_id' => $message->id,
                'status' => $status,
            ]);
            return $message;
        }

        $actual = self::ORDEN[$message->status] ?? 0;

        if (self::ORDEN[$status] < $actual) {
            Log::info('Callback de estado fuera de orden, se ignora', [
                'message_id' => $message->id,
                'actual' => $message->status,
                'recibido' => $status,
            ]);
            return $message;
        }

        // Actualizar estado
        $message->update([
            'status' => $status,
            'error_code' => $errorCode ? (string) $errorCode : null,
            'error_message' => $errorMessage ?: null,
            'last_status_at' => now(),
            'raw_webhook' => $payload,
        ]);

        // 'read' implica entregado: si el 'delivered' no llegó o llegó
        // después, igual se registra la hora de entrega.
        if (in_array($status, ['delivered', 'read'], true) && !$message->delivered_at) {
            $message->update(['delivered_at' => now()]);
        }

        Log::info('Message status updated', [
            'message_id' => $message->id,
            'status' => $status,
        ]);

        return $message;
    }
}
