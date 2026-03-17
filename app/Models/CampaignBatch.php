<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignBatch extends Model
{
    protected $fillable = [
        'campaign_id',
        'batch_number',
        'status',
        'messages_count',
        'started_at',
        'completed_at',
        'error_message',
        'retry_count',
        'max_retries',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ===== RELACIONES =====

    /**
     * Campaña a la que pertenece este lote
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    // ===== SCOPES =====

    /**
     * Lotes pendientes de procesar
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Lotes completados
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Lotes fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // ===== MÉTODOS =====

    /**
     * Marca lote como en procesamiento
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Marca lote como completado
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Marca lote como fallido
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Incrementa contador de reintentos
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }

    /**
     * Verifica si puede reitentar
     */
    public function canRetry(): bool
    {
        return $this->retry_count < $this->max_retries;
    }
}
