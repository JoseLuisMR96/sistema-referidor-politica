<?php

namespace App\Actions\Campaign;

use App\DTOs\CreateCampaignDTO;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\CampaignMetrics;
use App\Services\CampaignRateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Action para crear una nueva campaña de WhatsApp
 * Orquesta la creación de campaña, mensajes, lotes y métricas
 */
class CreateCampaignAction
{
    public function __construct(
        private CampaignRateLimiter $rateLimiter,
    ) {}

    /**
     * Ejecuta la creación de campaña
     *
     * @param CreateCampaignDTO $dto
     * @return Campaign
     * @throws \Exception
     */
    public function execute(CreateCampaignDTO $dto): Campaign
    {
        return DB::transaction(function () use ($dto) {
            // 1. Crear registro de campaña
            $campaign = Campaign::create([
                'name' => $dto->name,
                'type' => $dto->type,
                'body' => $dto->body,
                'media_path' => $dto->mediaPath,
                'source' => $dto->source,
                'status' => 'draft',
                'referrer_id' => $dto->referrerId,
                'referidor_pregonero_id' => $dto->referidorPregoneroId,
                'started_at' => now(),
            ]);

            // 2. Crear mensajes individuales
            foreach ($dto->recipients as $recipient) {
                $phone = $this->normalizePhone($recipient['phone']);

                if (empty($phone)) {
                    Log::warning('Skipping invalid phone number', ['phone' => $recipient['phone']]);
                    continue;
                }

                CampaignMessage::create([
                    'campaign_id' => $campaign->id,
                    'to' => $phone,
                    'contact_name' => $recipient['name'] ?? null,
                    'status' => 'pending',
                    'referrer_id' => $dto->referrerId,
                    'referidor_pregonero_id' => $dto->referidorPregoneroId,
                    'source_type' => $dto->source,
                ]);
            }

            // 3. Crear métricas iniciales
            CampaignMetrics::create([
                'campaign_id' => $campaign->id,
                'total_messages' => $campaign->messages()->count(),
                'updated_at' => now(),
            ]);

            // 4. Pasar a estado "queued" y crear lotes
            $campaign->update(['status' => 'queued']);
            $this->rateLimiter->createBatchesForCampaign($campaign, 50);

            Log::info('Campaign created successfully', [
                'campaign_id' => $campaign->id,
                'name' => $campaign->name,
                'total_messages' => $campaign->messages()->count(),
                'total_batches' => $campaign->batches()->count(),
            ]);

            return $campaign->fresh();
        });
    }

    /**
     * Normaliza un número de teléfono a formato WhatsApp
     *
     * @param string $phone
     * @return string|null
     */
    private function normalizePhone(string $phone): ?string
    {
        // Eliminar espacios y caracteres especiales
        $phone = trim($phone);
        $digits = preg_replace('/\D/', '', $phone);

        // Si viene con "whatsapp:" ya, retornar como está
        if (str_starts_with($phone, 'whatsapp:')) {
            return $phone;
        }

        // Si tiene 10 dígitos (Colombia sin el 57), agregar prefijo
        if (strlen($digits) === 10) {
            $digits = '57' . $digits;
        }

        // Validar rango válido
        if (strlen($digits) < 11 || strlen($digits) > 15) {
            return null;
        }

        return 'whatsapp:' . $digits;
    }
}
