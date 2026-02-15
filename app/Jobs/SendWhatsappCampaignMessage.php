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

        $from = config('services.twilio.whatsapp_from'); // whatsapp:+...

        $body = $this->buildBody($campaign, $msgRow);

        $payload = [
            'from' => $from,
            'to'   => $msgRow->to,
            'body' => $body,
            'statusCallback' => url('/api/twilio/whatsapp/status'),
        ];

        // Media (imagen/video) + texto (caption)
        if ($campaign->type === 'media' && $campaign->media_path) {
            // Ojo: Twilio necesita URL pública accesible (no local)
            $publicUrl = Storage::disk('public')->url($campaign->media_path);
            $payload['mediaUrl'] = [$publicUrl];
        }

        // Si vas a usar templates aprobadas, aquí se arma diferente (te lo dejo listo abajo)
        try {
            $msgRow->update(['status' => 'queued']);
            $res = $client->messages->create($msgRow->to, $payload);

            $msgRow->update([
                'twilio_sid' => $res->sid,
                'status' => $res->status ?? 'sent',
                'sent_at' => now(),
                'last_status_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("WhatsApp send failed: ".$e->getMessage(), ['message_id' => $msgRow->id]);

            $msgRow->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'last_status_at' => now(),
            ]);
        }
    }

    private function buildBody(WhatsappCampaign $campaign, WhatsappMessage $msgRow): string
    {
        // Personalización simple
        $name = $msgRow->contact_name ? $msgRow->contact_name : '👋';

        $base = $campaign->body ? str_replace('{name}', $name, $campaign->body) : '';

        if ($campaign->type === 'location') {
            $extra = $campaign->location_url
                ? "\n\n📍 Ubicación: {$campaign->location_url}"
                : '';
            return trim($base.$extra);
        }

        return trim($base);
    }
}
