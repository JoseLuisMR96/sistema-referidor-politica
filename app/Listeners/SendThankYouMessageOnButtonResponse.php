<?php

namespace App\Listeners;

use App\Enums\ButtonIdEnum;
use App\Events\ButtonResponseCaptured;
use App\Models\CampaignMessage;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * Listener que envía un mensaje de agradecimiento cuando se captura una respuesta
 */
class SendThankYouMessageOnButtonResponse
{
    public function handle(ButtonResponseCaptured $event): void
    {
        try {
            $response = $event->response;
            $campaign = $response->campaign;
            $originalMessage = $response->campaignMessage;

            $thankYouText = ButtonIdEnum::tryFrom($response->button_id)?->thankYouMessage()
                ?? '✅ ¡Gracias por tu respuesta! Ha sido registrada correctamente.';

            $destino = PhoneNumber::toWhatsApp($response->phone);

            if (! $destino) {
                Log::warning('No se envía agradecimiento: teléfono inválido', [
                    'response_id' => $response->id,
                    'phone' => $response->phone,
                ]);

                return;
            }

            // Guardar el mensaje de agradecimiento en BD
            $thankYouMsg = CampaignMessage::create([
                'campaign_id' => $campaign->id,
                'to' => $destino,
                'contact_name' => null,
                'status' => 'pending',
                'referrer_id' => $response->referrer_id,
                'referidor_pregonero_id' => $response->referidor_pregonero_id,
                'source_type' => $originalMessage->source_type ?? 'twilio',
                'raw_webhook' => [
                    'type' => 'thank_you',
                    'original_response_id' => $response->id,
                ],
            ]);

            // El MSID guardado en la respuesta capturada tiene prioridad
            $this->sendViaWhatsApp(
                $thankYouText,
                $destino,
                $thankYouMsg,
                $response->messaging_service_id,
            );

            Log::info('Mensaje de agradecimiento enviado', [
                'response_id' => $response->id,
                'button_id' => $response->button_id,
                'message_id' => $thankYouMsg->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error enviando mensaje de agradecimiento', [
                'error' => $e->getMessage(),
                'response_id' => $event->response->id ?? 'unknown',
            ]);
        }
    }

    /**
     * Envía directamente a Twilio sin ir por el job
     */
    private function sendViaWhatsApp(
        string $body,
        string $destino,
        CampaignMessage $messageRecord,
        ?string $messagingServiceSid = null,
    ): void {
        $client = new Client(config('services.twilio.sid'), config('services.twilio.token'));
        $msid = $messagingServiceSid ?: config('services.twilio.messaging_service_sid');

        try {
            $result = $client->messages->create($destino, [
                'messagingServiceSid' => $msid,
                'body' => $body,
                'statusCallback' => url(config('services.twilio.status_callback_path')),
            ]);

            $messageRecord->update([
                'twilio_sid' => $result->sid,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $messageRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
