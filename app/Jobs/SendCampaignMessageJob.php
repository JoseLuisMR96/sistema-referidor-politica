<?php

namespace App\Jobs;

use App\Models\CampaignMessage;
use App\Support\PhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Twilio\Rest\Client;

/**
 * Job para enviar un mensaje individual via Twilio
 * 
 * Responsabilidades:
 * - Conectar a Twilio
 * - Validar datos
 * - Enviar mensaje o template
 * - Capturar SID de respuesta
 * - Manejar errores
 */
class SendCampaignMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $messageId)
    {
        // La cola se asigna aquí y no como propiedad: `$queue` ya viene del
        // trait Queueable y redeclararla tipada es un fatal error de PHP.
        $this->onQueue('messages');
    }

    public function handle(): void
    {
        $message = CampaignMessage::with('campaign')
            ->findOrFail($this->messageId);

        // Si ya fue enviado, no hacer nada
        if ($message->isAlreadySent()) {
            Log::info('Message already sent, skipping', ['message_id' => $message->id]);
            return;
        }

        try {
            $campaign = $message->campaign;

            if (!$campaign) {
                throw new \RuntimeException('Campaign not found');
            }

            // Conectar a Twilio
            $client = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $to = PhoneNumber::toWhatsApp($message->to);

            if (! $to) {
                throw new \RuntimeException("Número inválido: {$message->to}");
            }

            // Obtener MSID desde campaña o config
            $msid = $campaign->messaging_service_sid
                ?: config('services.twilio.messaging_service_sid');

            if (!$msid || !str_starts_with($msid, 'MG')) {
                throw new \RuntimeException("Invalid messaging_service_sid: {$msid}");
            }

            $payload = [
                'messagingServiceSid' => $msid,
                'statusCallback' => url(config('services.twilio.status_callback_path')),
            ];

            // Enviar según tipo de campaña
            if ($campaign->type === 'template') {
                // Usar content template
                $payload['contentSid'] = $campaign->content_sid;
                if ($campaign->content_variables) {
                    $payload['contentVariables'] = json_encode(
                        $this->buildTemplateVariables($campaign, $message),
                        JSON_UNESCAPED_UNICODE
                    );
                }
            } else {
                // Mensaje libre
                $payload['body'] = $this->buildMessageBody($campaign, $message);

                if ($campaign->type === 'media' && $campaign->media_path) {
                    $payload['mediaUrl'] = [url(Storage::disk('public')->url($campaign->media_path))];
                }
            }

            // Marcar como queued antes de enviar
            $message->update(['status' => 'queued', 'last_status_at' => now()]);

            // Enviar a Twilio
            $response = $client->messages->create($to, $payload);

            // Actualizar con SID de respuesta
            $message->markAsSent($response->sid);

            Log::info('Message sent successfully', [
                'message_id' => $message->id,
                'twilio_sid' => $response->sid,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error sending campaign message', [
                'message_id' => $this->messageId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Reintentar con backoff mientras queden intentos. Solo se marca
            // como fallido al agotarlos: antes se marcaba y además se reencolaba,
            // así que el mensaje quedaba en 'failed' pero se reenviaba igual.
            if ($this->attempts() < $this->tries) {
                $message->update(['status' => 'pending', 'last_status_at' => now()]);
                $this->release(60 * $this->attempts());

                return;
            }

            $message->markAsFailed($e->getMessage());
        }
    }

    /**
     * Construye el cuerpo del mensaje reemplazando variables
     */
    private function buildMessageBody($campaign, $message): string
    {
        $body = $campaign->body ?? '';

        // Reemplazar variables
        $body = str_replace('{name}', $message->contact_name ?: '👋', $body);

        return trim($body ?: '');
    }

    /**
     * Construye variables para templates de Twilio
     */
    private function buildTemplateVariables($campaign, $message): array
    {
        $vars = [];

        if ($campaign->content_variables) {
            $vars = json_decode($campaign->content_variables, true) ?? [];
        }

        // Agregar/reemplazar nombre
        $vars['1'] = $message->contact_name ?: 'Cliente';

        return $vars;
    }
}
