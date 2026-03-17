<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignMessage extends Model
{
    protected $table = 'campaign_messages';

    protected $fillable = [
        'campaign_id',
        'to',
        'contact_name',
        'twilio_sid',
        'status',
        'referrer_id',
        'referidor_pregonero_id',
        'source_type',
        'provider_message_id',
        'error_code',
        'error_message',
        'sent_at',
        'delivered_at',
        'last_status_at',
        'raw_webhook',
    ];

    protected $casts = [
        'raw_webhook' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'last_status_at' => 'datetime',
    ];

    // ===== RELACIONES =====

    /**
     * Campaña a la que pertenece este mensaje
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
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
     * Respuestas de botones para este mensaje
     */
    public function responses(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignResponse::class, 'campaign_message_id');
    }

    // ===== SCOPES =====

    /**
     * Mensajes pendientes de envío
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Mensajes enviados
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Mensajes entregados
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Mensajes fallidos
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'undelivered']);
    }

    // ===== MÉTODOS =====

    /**
     * Marca mensaje como enviado
     */
    public function markAsSent(string $twiloSid): void
    {
        $this->update([
            'status' => 'sent',
            'twilio_sid' => $twiloSid,
            'sent_at' => now(),
            'last_status_at' => now(),
        ]);
    }

    /**
     * Marca mensaje como entregado
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'last_status_at' => now(),
        ]);
    }

    /**
     * Marca mensaje como fallido
     */
    public function markAsFailed(string $errorMessage, string $errorCode = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'last_status_at' => now(),
        ]);
    }

    /**
     * Verifica si el mensaje ya fue enviado
     */
    public function isAlreadySent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read']);
    }

    /**
     * Obtiene la respuesta de botón si existe
     */
    public function getButtonResponse(): ?WhatsAppCampaignResponse
    {
        return $this->responses()->first();
    }
}
