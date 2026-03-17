<?php

namespace App\Listeners;

use App\Events\ButtonResponseCaptured;
use App\Models\CampaignMetrics;
use Illuminate\Support\Facades\Log;

/**
 * Listener que actualiza métricas cuando se captura una respuesta
 */
class UpdateCampaignMetricsOnResponse
{
    public function handle(ButtonResponseCaptured $event): void
    {
        try {
            $response = $event->response;
            $campaign = $response->campaign;

            // Obtener o crear métricas
            $metrics = $campaign->metrics ?? CampaignMetrics::create([
                'campaign_id' => $campaign->id,
                'updated_at' => now(),
            ]);

            // Incrementar contador de respuestas
            $metrics->incrementButtonResponse($response->button_id);

            Log::info('Campaign metrics updated', [
                'campaign_id' => $campaign->id,
                'button_id' => $response->button_id,
                'total_responses' => $metrics->total_responses,
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating campaign metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
