<?php

namespace App\Http\Controllers\Campaign;

use App\Actions\Campaign\CreateCampaignAction;
use App\DTOs\CreateCampaignDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para gestionar campañas de WhatsApp
 */
class CampaignController extends Controller
{
    public function __construct(
        private CreateCampaignAction $createAction,
        private CampaignRateLimiter $limiter,
    ) {}

    /**
     * Crea una nueva campaña
     */
    public function store(CreateCampaignRequest $request): JsonResponse
    {
        $dto = CreateCampaignDTO::fromArray($request->validated());
        $campaign = $this->createAction->execute($dto);

        return response()->json([
            'ok' => true,
            'message' => 'Campaña creada exitosamente',
            'campaign_id' => $campaign->id,
            'total_messages' => $campaign->messages()->count(),
            'total_batches' => $campaign->batches()->count(),
        ], 201);
    }

    /**
     * Obtiene detalles de una campaña
     */
    public function show(Campaign $campaign): JsonResponse
    {
        $campaign->load(['messages', 'batches', 'responses', 'metrics']);

        return response()->json([
            'id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status->value,
            'status_label' => $campaign->status->label(),
            'source' => $campaign->source,
            'total_messages' => $campaign->messages()->count(),
            'sent_count' => $campaign->getSentCount(),
            'delivered_count' => $campaign->getDeliveredCount(),
            'failed_count' => $campaign->getFailedCount(),
            'response_stats' => $campaign->getResponseStats(),
            'delivery_rate' => $campaign->getDeliveryRate(),
            'response_rate' => $campaign->getResponseRate(),
            'metrics' => $campaign->metrics,
        ]);
    }

    /**
     * Pausa una campaña activa
     */
    public function pause(Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        if (!$campaign->status->canPause()) {
            return response()->json([
                'ok' => false,
                'message' => "No se puede pausar una campaña con estado: {$campaign->status->label()}",
            ], 400);
        }

        $this->limiter->pauseCampaign($campaign);

        return response()->json([
            'ok' => true,
            'message' => 'Campaña pausada',
        ]);
    }

    /**
     * Reanuda una campaña pausada
     */
    public function resume(Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        if (!$campaign->status->canResume()) {
            return response()->json([
                'ok' => false,
                'message' => "No se puede reanudar una campaña con estado: {$campaign->status->label()}",
            ], 400);
        }

        $this->limiter->resumeCampaign($campaign);

        return response()->json([
            'ok' => true,
            'message' => 'Campaña reanudada',
        ]);
    }

    /**
     * Cancela una campaña
     */
    public function cancel(Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        if (!$campaign->status->isActive() && $campaign->status->value !== 'draft') {
            return response()->json([
                'ok' => false,
                'message' => "No se puede cancelar una campaña completada o fallida",
            ], 400);
        }

        $this->limiter->cancelCampaign($campaign);

        return response()->json([
            'ok' => true,
            'message' => 'Campaña cancelada',
        ]);
    }

    /**
     * Obtiene estadísticas de una campaña
     */
    public function stats(Campaign $campaign): JsonResponse
    {
        $metrics = $campaign->metrics;

        if (!$metrics) {
            $metrics = \App\Models\CampaignMetrics::create([
                'campaign_id' => $campaign->id,
                'updated_at' => now(),
            ]);
            $metrics->refresh();
        }

        return response()->json([
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'total_messages' => $metrics->total_messages,
            'sent' => $metrics->sent_count,
            'delivered' => $metrics->delivered_count,
            'failed' => $metrics->failed_count,
            'delivery_rate' => $metrics->delivery_rate,
            'responses' => [
                'total' => $metrics->total_responses,
                'palom' => [
                    'count' => $metrics->palom_count,
                    'percentage' => $metrics->getButtonPercentage('palom'),
                ],
                'cepeda' => [
                    'count' => $metrics->cepeda_count,
                    'percentage' => $metrics->getButtonPercentage('cepeda'),
                ],
                'otro_candidato' => [
                    'count' => $metrics->otro_count,
                    'percentage' => $metrics->getButtonPercentage('otro_candidato'),
                ],
            ],
            'delivery_times' => [
                'average_seconds' => $metrics->avg_delivery_time_seconds,
                'fastest_seconds' => $metrics->fastest_delivery_seconds,
                'slowest_seconds' => $metrics->slowest_delivery_seconds,
            ],
        ]);
    }

    /**
     * Obtiene rate limiting status
     */
    public function rateLimitStatus(Campaign $campaign): JsonResponse
    {
        $status = $this->limiter->getStatus($campaign);

        return response()->json($status);
    }
}
