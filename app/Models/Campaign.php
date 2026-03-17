<?php

namespace App\Models;

use App\Enums\CampaignStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'type',                      // text|media|location|template
        'body',
        'media_path',
        'source',                    // twilio|wppconnect
        'status',                    // enum
        'referrer_id',
        'referidor_pregonero_id',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => CampaignStatusEnum::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ===== RELACIONES =====

    /**
     * Mensajes individuales de la campaña
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }

    /**
     * Lotes de procesamiento para control de velocidad
     */
    public function batches(): HasMany
    {
        return $this->hasMany(CampaignBatch::class);
    }

    /**
     * Respuestas de botones capturadas desde webhooks
     */
    public function responses(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignResponse::class);
    }

    /**
     * Referidor asociado (si aplica)
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    /**
     * Referidor pregonero asociado (si aplica)
     */
    public function referidorPregonero(): BelongsTo
    {
        return $this->belongsTo(ReferidorPregonero::class, 'referidor_pregonero_id');
    }

    /**
     * Métricas denormalizadas para dashboards
     */
    public function metrics(): HasOne
    {
        return $this->hasOne(CampaignMetrics::class);
    }

    // ===== SCOPES =====

    /**
     * Campañas activas (en proceso de envío)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            CampaignStatusEnum::QUEUED,
            CampaignStatusEnum::SENDING,
            CampaignStatusEnum::BATCHED,
        ]);
    }

    /**
     * Campañas completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', CampaignStatusEnum::COMPLETED->value);
    }

    /**
     * Campañas pausadas
     */
    public function scopePaused($query)
    {
        return $query->where('status', CampaignStatusEnum::PAUSED->value);
    }

    /**
     * Campañas fallidas
     */
    public function scopeFailed($query)
    {
        return $query->where('status', CampaignStatusEnum::FAILED->value);
    }

    /**
     * Filtrar por referidor
     */
    public function scopeByReferrer($query, int $referrerId)
    {
        return $query->where('referrer_id', $referrerId);
    }

    /**
     * Filtrar por referidor pregonero
     */
    public function scopeByPregonero($query, int $pregoneroId)
    {
        return $query->where('referidor_pregonero_id', $pregoneroId);
    }

    /**
     * Filtrar por fuente
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    // ===== MÉTODOS =====

    /**
     * Pausa la campaña
     */
    public function pause(): void
    {
        if (!$this->status->canPause()) {
            throw new \RuntimeException("No se puede pausar campaña con estado: {$this->status->label()}");
        }

        $this->update(['status' => CampaignStatusEnum::PAUSED]);
    }

    /**
     * Reanuda campaña pausada
     */
    public function resume(): void
    {
        if (!$this->status->canResume()) {
            throw new \RuntimeException("No se puede reanudar campaña con estado: {$this->status->label()}");
        }

        $this->update(['status' => CampaignStatusEnum::BATCHED]);
    }

    /**
     * Cancela la campaña
     */
    public function cancel(): void
    {
        $this->update(['status' => CampaignStatusEnum::CANCELLED]);
    }

    /**
     * Obtiene estadísticas de respuestas por botón
     */
    public function getResponseStats(): array
    {
        $stats = $this->responses()
            ->selectRaw('button_id, COUNT(*) as count')
            ->groupBy('button_id')
            ->pluck('count', 'button_id')
            ->toArray();

        return [
            'palom' => $stats['palom'] ?? 0,
            'cepeda' => $stats['cepeda'] ?? 0,
            'otro_candidato' => $stats['otro_candidato'] ?? 0,
        ];
    }

    /**
     * Obtiene el total de mensajes entregados
     */
    public function getDeliveredCount(): int
    {
        return $this->messages()
            ->where('status', 'delivered')
            ->count();
    }

    /**
     * Obtiene el total de mensajes fallidos
     */
    public function getFailedCount(): int
    {
        return $this->messages()
            ->whereIn('status', ['failed', 'undelivered'])
            ->count();
    }

    /**
     * Obtiene el total de mensajes enviados
     */
    public function getSentCount(): int
    {
        return $this->messages()
            ->whereIn('status', ['sent', 'delivered'])
            ->count();
    }

    /**
     * Calcula la tasa de entrega (porcentaje)
     */
    public function getDeliveryRate(): float
    {
        $total = $this->messages()->count();
        if ($total === 0) {
            return 0;
        }

        return round(($this->getDeliveredCount() / $total) * 100, 2);
    }

    /**
     * Calcula la tasa de respuesta (porcentaje)
     */
    public function getResponseRate(): float
    {
        $total = $this->messages()->count();
        if ($total === 0) {
            return 0;
        }

        $responses = $this->responses()->distinct('campaign_message_id')->count();

        return round(($responses / $total) * 100, 2);
    }
}
