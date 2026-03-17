<?php

namespace App\Jobs;

use App\Jobs\SendCampaignMessageJob;
use App\Models\CampaignBatch;
use App\Models\CampaignMessage;
use App\Services\CampaignRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para procesar un lote de mensajes respetando el rate limiting
 * 
 * Este job es responsable de:
 * - Tomar un lote de mensajes
 * - Enviar cada uno respetando la velocidad configurada
 * - Manejar reintentos si falla
 * - Actualizar estado del lote
 */
class ProcessCampaignBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos
     */
    public int $tries = 3;

    /**
     * Timeout en segundos
     */
    public int $timeout = 300;

    /**
     * Cola a usar
     */
    public string $queue = 'campaigns';

    public function __construct(public int $batchId) {}

    public function handle(CampaignRateLimiter $limiter): void
    {
        $batch = CampaignBatch::findOrFail($this->batchId);

        // Si ya fue procesada, salir
        if ($batch->status !== 'pending') {
            Log::info('Batch already processed', ['batch_id' => $this->batchId]);
            return;
        }

        try {
            $batch->markAsProcessing();

            $campaign = $batch->campaign;

            // Obtener los mensajes pendientes para este lote
            $messages = CampaignMessage::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->limit($batch->messages_count)
                ->get();

            if ($messages->isEmpty()) {
                Log::warning('Batch has no pending messages', ['batch_id' => $this->batchId]);
                $batch->markAsCompleted();
                return;
            }

            $delayMs = 0;

            foreach ($messages as $message) {
                // Obtener delay necesario para respetar rate limiting
                $waitSecs = $limiter->recordSend($campaign);

                // Dispatch del job de envío con delay
                SendCampaignMessageJob::dispatch($message->id)
                    ->onQueue('messages')
                    ->delay(now()->addSeconds($waitSecs + ($delayMs / 1000)));

                // Incrementar delay acumulativo (pequeño)
                $delayMs += 25;  // +25ms entre cada mensaje
            }

            $batch->markAsCompleted();

            Log::info('Campaign batch processed', [
                'batch_id' => $this->batchId,
                'messages_dispatched' => $messages->count(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Error processing campaign batch', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
            ]);

            $batch->incrementRetry();

            if ($batch->canRetry()) {
                // Reintentar en 2 minutos
                $this->release(120);
            } else {
                $batch->markAsFailed($e->getMessage());
            }
        }
    }
}
