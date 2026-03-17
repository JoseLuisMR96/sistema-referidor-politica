<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsappWppconnectMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $msgRow = WhatsappMessage::query()
            ->with('campaign')
            ->findOrFail($this->messageId);

        $campaign = $msgRow->campaign;

        if (!$campaign) {
            throw new \RuntimeException("No se encontró la campaña para el mensaje {$this->messageId}");
        }

        // Evita duplicados
        $finalOrInFlightStatuses = ['sending', 'sent', 'delivered', 'read', 'undelivered'];

        if (!empty($msgRow->provider_message_id) || in_array($msgRow->status, $finalOrInFlightStatuses, true)) {
            Log::info('WPPConnect message skipped: already processed or in-flight', [
                'message_id' => $msgRow->id,
                'status' => $msgRow->status,
                'provider_message_id' => $msgRow->provider_message_id ?? null,
                'to' => $msgRow->to,
            ]);
            return;
        }

        $baseUrl = rtrim(config('services.wppconnect.base_url'), '/');
        $session = config('services.wppconnect.session');
        $token = config('services.wppconnect.token');

        if (!$baseUrl || !$session || !$token) {
            throw new \RuntimeException('Falta configuración de WPPConnect en services.php / .env');
        }

        $phone = $this->normalizePhone($msgRow->to);
        $message = $this->buildBody($campaign, $msgRow);

        if (!$message) {
            throw new \RuntimeException('Mensaje vacío para envío por WPPConnect');
        }

        try {
            $msgRow->update([
                'status' => 'sending',
                'last_status_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            // Pequeña pausa aleatoria para bajar patrón bot
            usleep(random_int(800000, 2500000)); // 0.8 a 2.5 segundos

            // Verifica estado de sesión
            $statusResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->timeout(30)->get("{$baseUrl}/api/{$session}/check-connection-session");

            if ($statusResponse->failed()) {
                throw new \RuntimeException('No se pudo validar la sesión de WPPConnect: ' . $statusResponse->body());
            }

            $statusData = $statusResponse->json();
            $sessionStatus = strtolower((string) data_get($statusData, 'status', ''));

            if (!in_array($sessionStatus, ['connected', 'inchat', 'islogged', 'open'], true)) {
                $msgRow->update([
                    'status' => 'queued',
                    'error_message' => 'Sesión WPPConnect no conectada',
                    'last_status_at' => now(),
                ]);

                $this->release(random_int(30, 90));
                return;
            }

            Log::info('WPPConnect send payload', [
                'message_id' => $msgRow->id,
                'campaign_id' => $campaign->id,
                'to' => $phone,
                'attempt' => $this->attempts(),
                'session' => $session,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(60)->post("{$baseUrl}/api/{$session}/send-message", [
                'phone' => $phone,
                'message' => $message,
                'isGroup' => false,
            ]);

            if ($response->failed()) {
                $body = $response->body();

                Log::warning('WPPConnect send failed response', [
                    'message_id' => $msgRow->id,
                    'campaign_id' => $campaign->id,
                    'status_code' => $response->status(),
                    'response' => $body,
                ]);

                // Reintento suave para fallos temporales
                if (in_array($response->status(), [408, 409, 423, 429, 500, 502, 503, 504], true)) {
                    $msgRow->update([
                        'status' => 'queued',
                        'error_code' => (string) $response->status(),
                        'error_message' => mb_substr($body, 0, 1000),
                        'last_status_at' => now(),
                    ]);

                    $this->release(random_int(60, 180));
                    return;
                }

                $msgRow->update([
                    'status' => 'failed',
                    'error_code' => (string) $response->status(),
                    'error_message' => mb_substr($body, 0, 1000),
                    'last_status_at' => now(),
                ]);

                return;
            }

            $data = $response->json();

            $providerId = data_get($data, 'response.id')
                ?? data_get($data, 'response.messageId')
                ?? data_get($data, 'response.key.id')
                ?? null;

            $msgRow->update([
                'provider_message_id' => $providerId,
                'status' => 'sent',
                'sent_at' => now(),
                'last_status_at' => now(),
                'error_code' => null,
                'error_message' => null,
                'raw_provider_response' => $data,
            ]);

            Log::info('WPPConnect message sent', [
                'message_id' => $msgRow->id,
                'campaign_id' => $campaign->id,
                'provider_message_id' => $providerId,
                'to' => $phone,
            ]);

        } catch (\Throwable $e) {
            Log::error('WPPConnect send failed (generic)', [
                'message_id' => $msgRow->id,
                'campaign_id' => $campaign->id ?? null,
                'to' => $phone ?? null,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            $msgRow->update([
                'status' => 'failed',
                'error_code' => null,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'last_status_at' => now(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $msgRow = WhatsappMessage::query()->find($this->messageId);

        if (!$msgRow) {
            return;
        }

        $msgRow->update([
            'status' => 'failed',
            'error_code' => null,
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
            'last_status_at' => now(),
        ]);

        Log::error('WPPConnect job exhausted retries', [
            'message_id' => $this->messageId,
            'error' => $e->getMessage(),
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $phone = str_replace('whatsapp:', '', $phone);
        $phone = preg_replace('/[^\d]/', '', $phone);

        return $phone;
    }

    private function buildBody($campaign, WhatsappMessage $msgRow): string
    {
        $name = $msgRow->contact_name ?: 'Cliente';
        $body = (string) ($campaign->body ?? '');

        return trim(str_replace('{name}', $name, $body));
    }
}