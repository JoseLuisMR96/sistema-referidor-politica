<?php

namespace App\DTOs;

/**
 * DTO para crear una nueva campaña de WhatsApp
 * Define el contrato entre la UI (Livewire) y la lógica de negocio
 */
readonly class CreateCampaignDTO
{
    public function __construct(
        public string $name,
        public string $type,                           // text|media|location|template
        public ?string $body,
        public array $recipients,                      // [['phone' => '57xxx', 'name' => 'John'], ...]
        public ?string $mediaPath = null,
        public ?int $referrerId = null,
        public ?int $referidorPregoneroId = null,
        public string $source = 'twilio',              // twilio|wppconnect
    ) {}

    /**
     * Crea un DTO desde un Form Request validado
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'],
            body: $data['body'] ?? null,
            recipients: $data['recipients'] ?? [],
            mediaPath: $data['media_path'] ?? null,
            referrerId: $data['referrer_id'] ?? null,
            referidorPregoneroId: $data['referidor_pregonero_id'] ?? null,
            source: $data['source'] ?? 'twilio',
        );
    }
}
