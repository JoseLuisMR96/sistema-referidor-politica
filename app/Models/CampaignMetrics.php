<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMetrics extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id',
        'total_messages',
        'sent_count',
        'delivered_count',
        'failed_count',
        'total_responses',
        'palom_count',
        'cepeda_count',
        'otro_count',
        'delivery_rate',
        'response_rate',
        'avg_delivery_time_seconds',
        'fastest_delivery_seconds',
        'slowest_delivery_seconds',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    // ===== RELACIONES =====

    /**
     * Campaña asociada
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    // ===== MÉTODOS =====

    /**
     * Actualiza todos los contadores basados en la campaña
     */
    public function refresh(): void
    {
        if (!$this->exists) {
            $this->updated_at = now();
        }
        $campaign = $this->campaign;
        
        $totalMessages = $campaign->messages()->count();
        $sentCount = $campaign->messages()->whereIn('status', ['sent', 'delivered'])->count();
        $deliveredCount = $campaign->messages()->where('status', 'delivered')->count();
        $failedCount = $campaign->messages()->whereIn('status', ['failed', 'undelivered'])->count();

        $totalResponses = $campaign->responses()->count();
        $polomCount = $campaign->responses()->where('button_id', 'palom')->count();
        $cepedaCount = $campaign->responses()->where('button_id', 'cepeda')->count();
        $otroCount = $campaign->responses()->where('button_id', 'otro_candidato')->count();

        $deliveryRate = $totalMessages > 0 ? round(($deliveredCount / $totalMessages) * 100, 2) : 0;
        $responseRate = $totalMessages > 0 ? round(($totalResponses / $totalMessages) * 100, 2) : 0;

        // Calcular tiempos promedio
        $deliveryTimes = $campaign->messages()
            ->where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->whereNotNull('sent_at')
            ->selectRaw('TIMESTAMPDIFF(SECOND, sent_at, delivered_at) as seconds')
            ->pluck('seconds');

        $avgDeliveryTime = $deliveryTimes->isEmpty() ? null : intval($deliveryTimes->avg());
        $fastestDelivery = $deliveryTimes->isEmpty() ? null : intval($deliveryTimes->min());
        $slowestDelivery = $deliveryTimes->isEmpty() ? null : intval($deliveryTimes->max());

        $updates = [
            'total_messages' => $totalMessages,
            'sent_count' => $sentCount,
            'delivered_count' => $deliveredCount,
            'failed_count' => $failedCount,
            'total_responses' => $totalResponses,
            'palom_count' => $polomCount,
            'cepeda_count' => $cepedaCount,
            'otro_count' => $otroCount,
            'delivery_rate' => $deliveryRate,
            'response_rate' => $responseRate,
            'avg_delivery_time_seconds' => $avgDeliveryTime,
            'fastest_delivery_seconds' => $fastestDelivery,
            'slowest_delivery_seconds' => $slowestDelivery,
            'updated_at' => now(),
        ];

        if ($this->exists) {
            $this->update($updates);
        } else {
            $this->fill($updates)->save();
        }
    }

    /**
     * Incrementa contador de respuestas para un botón
     */
    public function incrementButtonResponse(string $buttonId): void
    {
        $field = match ($buttonId) {
            'palom' => 'palom_count',
            'cepeda' => 'cepeda_count',
            'otro_candidato' => 'otro_count',
            default => null,
        };

        if ($field) {
            $this->increment($field);
            $this->increment('total_responses');
            
            // Recalcular respuesta_rate
            if ($this->campaign->messages()->count() > 0) {
                $this->update([
                    'response_rate' => round(
                        ($this->total_responses / $this->campaign->messages()->count()) * 100,
                        2
                    ),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Obtiene el botón más votado
     */
    public function getMostVoted(): ?string
    {
        $votes = [
            'palom' => $this->palom_count,
            'cepeda' => $this->cepeda_count,
            'otro_candidato' => $this->otro_count,
        ];

        arsort($votes);
        $topButton = array_key_first($votes);

        return $votes[$topButton] > 0 ? $topButton : null;
    }

    /**
     * Obtiene porcentaje para un botón
     */
    public function getButtonPercentage(string $buttonId): float
    {
        if ($this->total_responses === 0) {
            return 0;
        }

        $count = match ($buttonId) {
            'palom' => $this->palom_count,
            'cepeda' => $this->cepeda_count,
            'otro_candidato' => $this->otro_count,
            default => 0,
        };

        return round(($count / $this->total_responses) * 100, 2);
    }
}
