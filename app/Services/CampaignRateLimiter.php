<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignBatch;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para limitar la velocidad de envío de campañas
 * Implementa un rate limiter basado en Redis/Cache para mantener
 * ~20-40 requests por segundo sin saturar Twilio ni el sistema
 */
class CampaignRateLimiter
{
    /**
     * Requests por segundo permitidos
     * Recomendado: 20 req/s = 50ms entre requests (seguro)
     * Máximo: 40 req/s = 25ms sin garantías
     */
    private int $requestsPerSecond;

    /**
     * Delay en milisegundos entre cada request
     */
    private int $delayBetweenRequestsMs;

    /**
     * TTL del cache en segundos
     */
    private int $cacheTtl = 3600;

    public function __construct(int $rps = 20)
    {
        $this->requestsPerSecond = max(1, $rps);
        $this->delayBetweenRequestsMs = intval(1000 / $this->requestsPerSecond);
    }

    /**
     * Divide una campaña en lotes respetando la velocidad de envío
     *
     * @param Campaign $campaign
     * @param int $messagesPerBatch Cuántos mensajes procesar por lote
     * @return void
     */
    public function createBatchesForCampaign(Campaign $campaign, int $messagesPerBatch = 50): void
    {
        $messages = $campaign->messages()
            ->where('status', 'pending')
            ->get();

        $totalMessages = $messages->count();

        if ($totalMessages === 0) {
            Log::warning('Campaign has no pending messages to batch', ['campaign_id' => $campaign->id]);
            return;
        }

        $batchNumber = 1;

        for ($i = 0; $i < $totalMessages; $i += $messagesPerBatch) {
            $batch = CampaignBatch::create([
                'campaign_id' => $campaign->id,
                'batch_number' => $batchNumber,
                'messages_count' => min($messagesPerBatch, $totalMessages - $i),
                'status' => 'pending',
            ]);

            Log::info('Campaign batch created', [
                'campaign_id' => $campaign->id,
                'batch_number' => $batchNumber,
                'messages_count' => $batch->messages_count,
            ]);

            $batchNumber++;
        }

        $campaign->update(['status' => 'batched']);
    }

    /**
     * Obtiene el siguiente timestamp permitido para enviar
     * Esto garantiza que no excedamos la velocidad configurada
     *
     * @param Campaign $campaign
     * @return Carbon
     */
    public function getNextAllowedTimestamp(Campaign $campaign): Carbon
    {
        $key = "campaign.{$campaign->id}.last_send_timestamp";

        // Obtener último timestamp de envío (o hace 5 segundos si no existe)
        $lastSend = Cache::get($key, now()->subSeconds(5));

        // Calcular próximo timestamp permitido
        $nextAllowed = $lastSend->copy()->addMilliseconds($this->delayBetweenRequestsMs);

        return $nextAllowed->isFuture() ? $nextAllowed : now();
    }

    /**
     * Registra un envío y retorna cuántos segundos esperar antes del siguiente
     *
     * @param Campaign $campaign
     * @return int Segundos a esperar
     */
    public function recordSend(Campaign $campaign): int
    {
        $key = "campaign.{$campaign->id}.last_send_timestamp";
        $nextAllowed = $this->getNextAllowedTimestamp($campaign);

        // Guardar en cache con TTL
        Cache::put($key, $nextAllowed, now()->addSeconds($this->cacheTtl));

        // Calcular delay en milisegundos y convertir a segundos
        $delayMs = max(0, $nextAllowed->diffInMilliseconds(now()));
        $delaySecs = intval(ceil($delayMs / 1000));

        return max(0, $delaySecs);
    }

    /**
     * Obtiene el estado actual de rate limiting para una campaña
     *
     * @param Campaign $campaign
     * @return array
     */
    public function getStatus(Campaign $campaign): array
    {
        return [
            'requests_per_second' => $this->requestsPerSecond,
            'delay_ms_between_requests' => $this->delayBetweenRequestsMs,
            'next_allowed_timestamp' => $this->getNextAllowedTimestamp($campaign),
            'pending_messages' => $campaign->messages()->where('status', 'pending')->count(),
            'pending_batches' => $campaign->batches()->where('status', 'pending')->count(),
        ];
    }

    /**
     * Pausa una campaña, deteniendo su envío
     *
     * @param Campaign $campaign
     * @return void
     */
    public function pauseCampaign(Campaign $campaign): void
    {
        $campaign->pause();

        // Limpiar cache de rate limiting
        Cache::forget("campaign.{$campaign->id}.last_send_timestamp");

        Log::info('Campaign paused', ['campaign_id' => $campaign->id]);
    }

    /**
     * Reanuda una campaña pausada
     *
     * @param Campaign $campaign
     * @return void
     */
    public function resumeCampaign(Campaign $campaign): void
    {
        $campaign->resume();

        Log::info('Campaign resumed', ['campaign_id' => $campaign->id]);
    }

    /**
     * Cancela una campaña completamente
     *
     * @param Campaign $campaign
     * @return void
     */
    public function cancelCampaign(Campaign $campaign): void
    {
        $campaign->cancel();
        Cache::forget("campaign.{$campaign->id}.last_send_timestamp");

        Log::info('Campaign cancelled', ['campaign_id' => $campaign->id]);
    }

    /**
     * Reinicia el contador de velocidad para una campaña
     * Úsalo si necesitas "resetear" el rate limiting
     *
     * @param Campaign $campaign
     * @return void
     */
    public function resetRateLimit(Campaign $campaign): void
    {
        Cache::forget("campaign.{$campaign->id}.last_send_timestamp");

        Log::info('Rate limit reset for campaign', ['campaign_id' => $campaign->id]);
    }

    /**
     * Obtiene el delay configurado en milisegundos
     *
     * @return int
     */
    public function getDelayMs(): int
    {
        return $this->delayBetweenRequestsMs;
    }

    /**
     * Cambia los requests por segundo dinámicamente
     *
     * @param int $newRps
     * @return void
     */
    public function setRequestsPerSecond(int $newRps): void
    {
        $this->requestsPerSecond = max(1, $newRps);
        $this->delayBetweenRequestsMs = intval(1000 / $this->requestsPerSecond);

        Log::info('Rate limiter updated', [
            'new_rps' => $this->requestsPerSecond,
            'new_delay_ms' => $this->delayBetweenRequestsMs,
        ]);
    }
}
