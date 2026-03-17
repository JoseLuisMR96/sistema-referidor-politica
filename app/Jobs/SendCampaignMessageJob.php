<?php

namespace App\Jobs;

use App\Models\CampaignMessage;
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
    public string $queue = 'messages';

    public function __construct(public int $messageId) {}

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

            $to = $message->to;
            if (!str_starts_with($to, 'whatsapp:')) {
                $to = 'whatsapp:' . $to;
            }

            // Obtener MSID desde campaña o config
            $msid = $campaign->messaging_service_sid
                ?: config('services.twilio.messaging_service_sid');

            if (!$msid || !str_starts_with($msid, 'MG')) {
                throw new \RuntimeException("Invalid messaging_service_sid: {$msid}");
            }

            $payload = [
                'messagingServiceSid' => $msid,
                'statusCallback' => url('/api/whatsapp/webhook/status'),
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
                    $url = Storage::disk('public')->url($campaign->media_path);
                    $payload['mediaUrl'] = [url('storage/' . $campaign->media_path)];
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
                'error' => $e->getMessage(),
            ]);

            $message->markAsFailed($e->getMessage());

            // Reintentar con backoff exponencial
            $this->release($this->tries * 60);
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
