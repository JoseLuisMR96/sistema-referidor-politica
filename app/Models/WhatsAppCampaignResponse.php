<?php

namespace App\Models;

use App\Enums\ButtonIdEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCampaignResponse extends Model
{
    protected $table = 'whatsapp_campaign_responses';

    protected $fillable = [
        'campaign_id',
        'campaign_message_id',
        'phone',
        'referrer_id',
        'referidor_pregonero_id',
        'button_id',
        'button_text',
        'response_timestamp',
        'twilio_message_sid',
        'messaging_service_id',
        'raw_webhook',
        'processed_at',
        'processing_status',
        'processing_error',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'response_timestamp' => 'datetime',
        'processed_at' => 'datetime',
        'raw_webhook' => 'array',
    ];

    // ===== RELACIONES =====

    /**
     * Campaña a la que pertenece esta respuesta
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Mensaje original que generó esta respuesta
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(CampaignMessage::class, 'campaign_message_id');
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

    // ===== SCOPES =====

    /**
     * Respuestas de un botón específico
     */
    public function scopeByButton($query, string $buttonId)
    {
        return $query->where('button_id', $buttonId);
    }

    /**
     * Respuestas de un referidor
     */
    public function scopeByReferrer($query, int $referrerId)
    {
        return $query->where('referrer_id', $referrerId);
    }

    /**
     * Respuestas de un referidor pregonero
     */
    public function scopeByPregonero($query, int $pregoneroId)
    {
        return $query->where('referidor_pregonero_id', $pregoneroId);
    }

    /**
     * Respuestas pendientes de procesar
     */
    public function scopePending($query)
    {
        return $query->where('processing_status', 'pending');
    }

    /**
     * Respuestas procesadas
     */
    public function scopeProcessed($query)
    {
        return $query->where('processing_status', 'processed');
    }

    /**
     * Respuestas con error
     */
    public function scopeWithError($query)
    {
        return $query->where('processing_status', 'error');
    }

    /**
     * Respuestas en rango de fechas
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('response_timestamp', [$startDate, $endDate]);
    }

    // ===== MÉTODOS =====

    /**
     * Marca respuesta como procesada
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'processing_status' => 'processed',
            'processed_at' => now(),
            'processing_error' => null,
        ]);
    }

    /**
     * Marca respuesta con error
     */
    public function markAsError(string $error): void
    {
        $this->update([
            'processing_status' => 'error',
            'processing_error' => $error,
            'processed_at' => now(),
        ]);
    }

    /**
     * Obtiene la etiqueta legible del botón
     */
    public function getButtonLabel(): string
    {
        return ButtonIdEnum::tryFrom($this->button_id)?->label() ?? 'Desconocido';
    }

    /**
     * Verifica si es respuesta válida (botón conocido)
     */
    public function isValidButton(): bool
    {
        return ButtonIdEnum::isValid($this->button_id);
    }
}
