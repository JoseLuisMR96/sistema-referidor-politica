<?php

namespace App\Jobs;

use App\Models\WhatsappCampaign;
use App\Models\WhatsappMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Twilio\Rest\Client;

class SendWhatsappCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $msgRow = WhatsappMessage::query()->with('campaign')->findOrFail($this->messageId);
        $campaign = $msgRow->campaign;

        $client = new Client(config('services.twilio.sid'), config('services.twilio.token'));

        $to = $this->normalizeTo($msgRow->to);

        $msid = $campaign->messaging_service_sid ?: config('services.twilio.messaging_service_sid');
        if (!$msid || !str_starts_with($msid, 'MG')) {
            throw new \RuntimeException("messaging_service_sid inválido o vacío. Debe iniciar con MG... (actual: {$msid})");
        }

        $payload = [
            'messagingServiceSid' => $msid,
            'statusCallback'      => url('/api/twilio/whatsapp/status'),
        ];

        try {
            $msgRow->update([
                'status' => 'queued',
                'last_status_at' => now(),
                'error_message' => null,
            ]);

            // ✅ TEMPLATE (HX...)
            if (!empty($campaign->content_sid)) {
                $contentSid = trim($campaign->content_sid);

                if (!str_starts_with($contentSid, 'HX')) {
                    throw new \RuntimeException("content_sid inválido. Debe iniciar con HX... (actual: {$contentSid})");
                }

                $vars = $this->buildTemplateVariables($campaign, $msgRow);

                $payload['contentSid'] = $contentSid;

                if (!empty($vars)) {
                    $payload['contentVariables'] = json_encode($vars, JSON_UNESCAPED_UNICODE);
                }

                Log::info('Twilio template send payload', [
                    'to' => $to,
                    'msid' => $msid,
                    'contentSid' => $contentSid,
                    'contentVariables' => $payload['contentVariables'] ?? null,
                ]);

                $res = $client->messages->create($to, $payload);

            // ✅ MENSAJE LIBRE / MEDIA / LOCATION
            } else {
                $body = $this->buildBody($campaign, $msgRow);

                if (!$body && $campaign->type !== 'media') {
                    throw new \RuntimeException('Mensaje vacío. Para iniciar conversación usa content_sid (HX...) con template aprobado.');
                }

                $payload['body'] = $body;

                if ($campaign->type === 'media' && $campaign->media_path) {
                    $publicUrl = url(Storage::disk('public')->url($campaign->media_path));
                    $payload['mediaUrl'] = [$publicUrl];
                }

                $res = $client->messages->create($to, $payload);
            }

            $msgRow->update([
                'twilio_sid' => $res->sid,
                'status' => $res->status ?? 'sent',
                'sent_at' => now(),
                'last_status_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("WhatsApp send failed: " . $e->getMessage(), [
                'message_id' => $msgRow->id,
                'to' => $to,
                'campaign_id' => $campaign->id ?? null,
                'msid' => $msid ?? null,
                'content_sid' => $campaign->content_sid ?? null,
            ]);

            $msgRow->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'last_status_at' => now(),
            ]);

            // opcional: reintentos de cola (si quieres que falle para retry automático)
            // throw $e;
        }
    }

    private function normalizeTo(string $to): string
    {
        $t = trim($to);
        return str_starts_with($t, 'whatsapp:') ? $t : 'whatsapp:' . $t;
    }

    private function buildBody(WhatsappCampaign $campaign, WhatsappMessage $msgRow): string
    {
        $name = $msgRow->contact_name ?: '👋';
        $base = $campaign->body ? str_replace('{name}', $name, $campaign->body) : '';

        if ($campaign->type === 'location') {
            $extra = $campaign->location_url ? "\n\n📍 Ubicación: {$campaign->location_url}" : '';
            return trim($base . $extra);
        }

        return trim($base);
    }

    private function sanitizeContentValue($v): string
    {
        $v = (string)($v ?? '');
        $v = str_replace(["\r", "\n", "\t"], ' ', $v);
        $v = preg_replace('/\s{2,}/', ' ', $v);
        $v = preg_replace('/ {5,}/', '    ', $v);
        return trim($v);
    }

    /**
     * Construye variables para Twilio Content Templates:
     * - Toma variables globales desde $campaign->content_variables (JSON)
     * - Inyecta "1" => nombre por destinatario (desde $msgRow->contact_name)
     * - Reemplaza {name} en cualquier valor del JSON global
     * - Limpia valores para que Twilio no se ponga exquisito
     */
    private function buildTemplateVariables(WhatsappCampaign $campaign, WhatsappMessage $msgRow): array
    {
        $vars = [];

        // 1) base vars globales si existen
        if (!empty($campaign->content_variables)) {
            $decoded = json_decode($campaign->content_variables, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $vars = $decoded;
            }
        }

        $name = $msgRow->contact_name ?: 'Cliente';

        // 2) variable 1 = nombre del contacto (por fila)
        $vars['1'] = $name;

        // 3) normaliza claves y valores, y permite {name} en JSON global
        foreach ($vars as $k => $v) {
            $key = (string)$k;

            $val = is_string($v) ? $v : (string)($v ?? '');
            $val = str_replace('{name}', $name, $val);
            $val = $this->sanitizeContentValue($val);

            if ($val === '') {
                $val = $this->sanitizeContentValue($name) ?: 'OK';
            }

            // reescribe si la key no era string
            if ($key !== $k) unset($vars[$k]);
            $vars[$key] = $val;
        }

        return $vars;
    }
}