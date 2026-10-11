<?php

namespace App\Listeners;

use App\Enums\ButtonIdEnum;
use App\Events\ButtonResponseCaptured;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * Listener que envía un mensaje de agradecimiento cuando se captura una respuesta.
 *
 * El agradecimiento NO se guarda como CampaignMessage: antes sí, y cada
 * respuesta sumaba un mensaje a la campaña, inflando total_messages y las
 * tasas de entrega y respuesta. El resultado queda en la propia respuesta
 * (processing_status / processing_error).
 */
class SendThankYouMessageOnButtonResponse
{
    public function handle(ButtonResponseCaptured $event): void
    {
        $response = $event->response;

        try {
            $thankYouText = ButtonIdEnum::tryFrom($response->button_id)?->thankYouMessage()
                ?? '✅ ¡Gracias por tu respuesta! Ha sido registrada correctamente.';

            $destino = PhoneNumber::toWhatsApp($response->phone);

            if (! $destino) {
                $response->markAsError('No se envía agradecimiento: teléfono inválido');

                return;
            }

            $client = new Client(config('services.twilio.sid'), config('services.twilio.token'));

            // El MSID guardado en la respuesta capturada tiene prioridad
            $msid = $response->messaging_service_id ?: config('services.twilio.messaging_service_sid');

            $result = $client->messages->create($destino, [
                'messagingServiceSid' => $msid,
                'body' => $thankYouText,
            ]);

            $response->markAsProcessed();

            Log::info('Mensaje de agradecimiento enviado', [
                'response_id' => $response->id,
                'button_id' => $response->button_id,
                'twilio_sid' => $result->sid,
            ]);
        } catch (\Throwable $e) {
            $response->markAsError(mb_substr($e->getMessage(), 0, 1000));

            Log::error('Error enviando mensaje de agradecimiento', [
                'error' => $e->getMessage(),
                'response_id' => $response->id,
            ]);
        }
    }
}
