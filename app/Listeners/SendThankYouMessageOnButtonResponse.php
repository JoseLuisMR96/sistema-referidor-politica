<?php

namespace App\Listeners;

use App\Events\ButtonResponseCaptured;
use App\Models\CampaignMessage;
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

            // Mapear ID de botón a mensaje personalizado
            $thankYouMessages = [
                'palom' => '✅ ¡Gracias por tu voto! Tu preferencia por Paloma Valencia ha sido registrada. 🕊️',
                'cepeda' => '✅ ¡Gracias por tu voto! Tu preferencia por Cepeda ha sido registrada. 🎯',
                'otro_candidato' => '✅ ¡Gracias por tu voto! Tu preferencia ha sido registrada. 🤝',
            ];

            $thankYouText = $thankYouMessages[$response->button_id] 
                ?? '✅ ¡Gracias por tu respuesta! Ha sido registrada correctamente.';

            // Guardar el mensaje de agradecimiento en BD
            $thankYouMsg = CampaignMessage::create([
                'campaign_id' => $campaign->id,
                'to' => $response->phone,
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

            // Extraer MSID guardado en la respuesta capturada
            $messagingServiceSid = $response->messaging_service_id ?? null;

            // Enviar directamente a Twilio
            $this->sendViaWhatsApp($thankYouText, $response->phone, $thankYouMsg, $messagingServiceSid);

            Log::info('Thank you message sent', [
                'response_id' => $response->id,
                'button_id' => $response->button_id,
                'phone' => $response->phone,
                'message_id' => $thankYouMsg->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending thank you message', [
                'error' => $e->getMessage(),
                'response_id' => $event->response->id ?? 'unknown',
            ]);
        }
    }

    /**
     * Envía directamente a Twilio sin ir por el job
     */
    private function sendViaWhatsApp(string $body, string $phone, CampaignMessage $messageRecord, ?string $messagingServiceSid = null): void
    {
        $client = new Client(config('services.twilio.sid'), config('services.twilio.token'));
        $msid = $messagingServiceSid ?? config('services.twilio.messaging_service_sid');

        $normalizedPhone = preg_replace('/\D/', '', $phone);
        if (!str_starts_with($normalizedPhone, '+')) {
            $normalizedPhone = '+' . $normalizedPhone;
        }
        
        // Add whatsapp: prefix for WhatsApp messaging
        $whatsappPhone = 'whatsapp:' . $normalizedPhone;

        try {
            $result = $client->messages->create(
                $whatsappPhone,
                [
                    'messagingServiceSid' => $msid,
                    'body' => $body,
                    'statusCallback' => url('/api/twilio/whatsapp/status'),
                ]
            );

            // Actualizar registro con el SID de Twilio
            $messageRecord->update([
                'twilio_sid' => $result->sid,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

        } catch (\Exception $e) {
            $messageRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}


