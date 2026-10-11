<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los webhooks son públicos: sin validar la firma cualquiera podría inyectar
 * respuestas de botones falsas y disparar envíos de WhatsApp con costo.
 */
class TwilioWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    public static function rutasDeWebhook(): array
    {
        return [
            'status callback' => ['/api/twilio/whatsapp/status'],
            'mensaje entrante' => ['/api/twilio/whatsapp/incoming'],
            'webhook de campañas' => ['/api/whatsapp/webhook/status'],
        ];
    }

    /**
     * @dataProvider rutasDeWebhook
     */
    public function test_rechaza_peticiones_sin_firma(string $ruta): void
    {
        config(['services.twilio.token' => 'token-de-prueba']);

        $response = $this->postJson($ruta, [
            'MessageSid' => 'SM0000000000000000000000000000000',
            'From' => 'whatsapp:+573001234567',
            'Body' => 'Paloma Valencia',
        ]);

        $response->assertForbidden();
    }

    /**
     * @dataProvider rutasDeWebhook
     */
    public function test_rechaza_firmas_invalidas(string $ruta): void
    {
        config(['services.twilio.token' => 'token-de-prueba']);

        $response = $this->withHeader('X-Twilio-Signature', 'firma-falsa')
            ->postJson($ruta, [
                'MessageSid' => 'SM0000000000000000000000000000000',
                'From' => 'whatsapp:+573001234567',
                'Body' => 'Cepeda',
            ]);

        $response->assertForbidden();
    }

    public function test_no_procesa_nada_si_falta_el_token_de_twilio(): void
    {
        config(['services.twilio.token' => null]);

        $response = $this->postJson('/api/twilio/whatsapp/status', [
            'MessageSid' => 'SM0000000000000000000000000000000',
        ]);

        $response->assertStatus(500);
    }
}
