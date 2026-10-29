<?php

namespace App\Models;

use App\Enums\ButtonIdEnum;
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
     * Recalcula todos los contadores a partir de la campaña.
     *
     * OJO: no se llama refresh() para no pisar Eloquent\Model::refresh(),
     * que recarga el modelo desde la BD y devuelve $this.
     */
    public function recalcular(): void
    {
        if (!$this->exists) {
            $this->updated_at = now();
        }
        $campaign = $this->campaign;

        $totalMessages = $campaign->messages()->count();
        // 'read' implica entregado (y enviado)
        $sentCount = $campaign->messages()->whereIn('status', ['sent', 'delivered', 'read'])->count();
        $deliveredCount = $campaign->messages()->whereIn('status', ['delivered', 'read'])->count();
        $failedCount = $campaign->messages()->whereIn('status', ['failed', 'undelivered'])->count();

        $totalResponses = $campaign->responses()->count();

        $conteoPorBoton = [];
        foreach (ButtonIdEnum::cases() as $button) {
            $conteoPorBoton[$button->countField()] = $campaign->responses()
                ->where('button_id', $button->value)
                ->count();
        }

        $deliveryRate = $totalMessages > 0 ? round(($deliveredCount / $totalMessages) * 100, 2) : 0;
        $responseRate = $totalMessages > 0 ? round(($totalResponses / $totalMessages) * 100, 2) : 0;

        // Calcular tiempos promedio
        $deliveryTimes = $campaign->messages()
            ->whereIn('status', ['delivered', 'read'])
            ->whereNotNull('delivered_at')
            ->whereNotNull('sent_at')
            ->get(['sent_at', 'delivered_at'])
            // En PHP y no con TIMESTAMPDIFF, que solo existe en MySQL
            ->map(fn (CampaignMessage $m) => max(0, (int) $m->sent_at->diffInSeconds($m->delivered_at)));

        $avgDeliveryTime = $deliveryTimes->isEmpty() ? null : intval($deliveryTimes->avg());
        $fastestDelivery = $deliveryTimes->isEmpty() ? null : intval($deliveryTimes->min());
        $slowestDelivery = $deliveryTimes->isEmpty() ? null : intval($deliveryTimes->max());

        $updates = [
            'total_messages' => $totalMessages,
            'sent_count' => $sentCount,
            'delivered_count' => $deliveredCount,
            'failed_count' => $failedCount,
            'total_responses' => $totalResponses,
            ...$conteoPorBoton,
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
        $button = ButtonIdEnum::tryFrom($buttonId);

        if (! $button) {
            return;
        }

        $this->increment($button->countField());
        $this->increment('total_responses');

        // Recalcular response_rate
        $totalMessages = $this->campaign->messages()->count();

        if ($totalMessages > 0) {
            $this->update([
                'response_rate' => round(($this->total_responses / $totalMessages) * 100, 2),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Obtiene el botón más votado
     */
    public function getMostVoted(): ?string
    {
        $votes = [];

        foreach (ButtonIdEnum::cases() as $button) {
            $votes[$button->value] = (int) $this->{$button->countField()};
        }

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

        $button = ButtonIdEnum::tryFrom($buttonId);
        $count = $button ? (int) $this->{$button->countField()} : 0;

        return round(($count / $this->total_responses) * 100, 2);
    }
}
