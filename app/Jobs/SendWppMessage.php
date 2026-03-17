<?php

namespace App\Jobs;

use App\Models\WppMessage;
use App\Services\WppConnectService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $messageId) {}

    public function handle(WppConnectService $service): void
    {
        $message = WppMessage::with(['campaign', 'contact'])->findOrFail($this->messageId);

        if (in_array($message->status, ['sending', 'sent', 'delivered', 'read'], true)) {
            Log::info('WPP message skipped', [
                'message_id' => $message->id,
                'status' => $message->status,
            ]);
            return;
        }

        $campaign = $message->campaign;

        if (!$campaign) {
            throw new \RuntimeException("La campaña no existe para el mensaje {$message->id}");
        }

        try {
            $message->update([
                'status' => 'sending',
                'error' => null,
            ]);

            if ($campaign->image_path) {
                $imageUrl = asset('storage/' . $campaign->image_path);
            
                $response = $service->sendImage(
                    $campaign->session,
                    $message->phone,
                    $imageUrl,
                    $message->message
                );
            } else {
                $response = $service->sendCampaignImageWithText(
                    $campaign->session,
                    $message->phone,
                    $message->message
                );
            }
            
            $providerId = data_get($response, 'response.id')
                ?? data_get($response, 'response.messageId')
                ?? data_get($response, 'response.key.id')
                ?? null;

            $message->update([
                'status' => 'sent',
                'provider_message_id' => $providerId,
                'provider_response' => $response,
                'sent_at' => now(),
            ]);

            $campaign->increment('sent');

            $message->contact?->update([
                'last_message_at' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error('WPP send failed', [
                'message_id' => $message->id,
                'campaign_id' => $campaign->id ?? null,
                'error' => $e->getMessage(),
            ]);

            $message->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            $campaign?->increment('failed');

            throw $e;
        }
    }
}