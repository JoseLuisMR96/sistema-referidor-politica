<?php

namespace App\Enums;

enum CampaignStatusEnum: string
{
    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case BATCHED = 'batched';
    case SENDING = 'sending';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Estados que indican que la campaña está activa
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::QUEUED,
            self::SENDING,
            self::BATCHED,
        ]);
    }

    /**
     * Estados que indican que la campaña puede ser reanudada
     */
    public function canResume(): bool
    {
        return $this === self::PAUSED;
    }

    /**
     * Estados que indican que la campaña puede ser pausada
     */
    public function canPause(): bool
    {
        return $this->isActive();
    }

    /**
     * Etiqueta legible del estado
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::QUEUED => 'En Cola',
            self::BATCHED => 'Dividida en Lotes',
            self::SENDING => 'Enviando',
            self::PAUSED => 'Pausada',
            self::COMPLETED => 'Completada',
            self::FAILED => 'Falló',
            self::CANCELLED => 'Cancelada',
        };
    }
}
