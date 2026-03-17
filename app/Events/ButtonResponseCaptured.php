<?php

namespace App\Events;

use App\Models\WhatsAppCampaignResponse;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado cuando se captura una respuesta de botón
 * Permite que múltiples listeners reaccionen sin acoplamiento
 */
class ButtonResponseCaptured
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WhatsAppCampaignResponse $response
    ) {}
}
