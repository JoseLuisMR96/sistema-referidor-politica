<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * DTO para capturar respuestas de botones desde webhooks de Twilio
 */
readonly class ButtonResponseDTO
{
    public function __construct(
        public int $campaignId,
        public int $campaignMessageId,
        public string $phone,
        public string $buttonId,
        public ?string $buttonText,
        public Carbon $responseTimestamp,
        public string $twiloSid,
        public array $rawWebhook,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $messagingServiceId = null,
    ) {}

    /**
     * Crea un DTO desde el payload de Twilio
     */
    public static function fromTwilioPayload(array $payload, int $campaignId, int $messageId): self
    {
        return new self(
            campaignId: $campaignId,
            campaignMessageId: $messageId,
            phone: $payload['From'] ?? 'unknown',
            buttonId: $payload['ButtonPayload'] ?? 'unknown',
            buttonText: $payload['ButtonText'] ?? null,
            responseTimestamp: Carbon::parse($payload['timestamp'] ?? now()),
            twiloSid: $payload['MessageSid'] ?? 'unknown',
            rawWebhook: $payload,
            ipAddress: $payload['ip_address'] ?? null,
            userAgent: $payload['user_agent'] ?? null,
        );
    }
}
