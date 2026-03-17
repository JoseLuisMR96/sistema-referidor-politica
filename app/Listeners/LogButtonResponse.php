<?php

namespace App\Listeners;

use App\Events\ButtonResponseCaptured;
use Illuminate\Support\Facades\Log;

/**
 * Listener que registra todas las respuestas de botones para auditoría
 */
class LogButtonResponse
{
    public function handle(ButtonResponseCaptured $event): void
    {
        $response = $event->response;

        Log::info('Button response captured', [
            'response_id' => $response->id,
            'campaign_id' => $response->campaign_id,
            'button_id' => $response->button_id,
            'button_label' => $response->getButtonLabel(),
            'phone' => $response->phone,
            'referrer_id' => $response->referrer_id,
            'referidor_pregonero_id' => $response->referidor_pregonero_id,
            'response_timestamp' => $response->response_timestamp,
        ]);
    }
}
