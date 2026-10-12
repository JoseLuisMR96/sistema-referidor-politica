<?php

namespace Tests\Feature;

use App\Actions\WhatsApp\ProcessTwilioWebhookAction;
use App\Actions\WhatsApp\UpdateMessageStatusAction;
use App\Events\ButtonResponseCaptured;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\WhatsAppCampaignResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Respuestas de botones y estados de mensajes: lo que alimenta las métricas
 * de campaña.
 */
class WebhookMetricasTest extends TestCase
{
    use RefreshDatabase;

    private CampaignMessage $mensaje;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->usarTenant();

        $campaignId = DB::table('campaigns')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Prueba',
            'type' => 'template',
            'status' => 'sending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mensaje = CampaignMessage::create([
            'campaign_id' => $campaignId,
            'to' => 'whatsapp:+573001234567',
            'twilio_sid' => 'SMoriginal',
            'status' => 'sent',
        ]);
    }

    private function respuesta(string $sid, string $body): array
    {
        return [
            'MessageSid' => $sid,
            'From' => 'whatsapp:+573001234567',
            'Body' => $body,
            'OriginalRepliedMessageSid' => 'SMoriginal',
        ];
    }

    public function test_el_mismo_boton_dos_veces_dispara_el_evento_una_sola_vez(): void
    {
        Event::fake([ButtonResponseCaptured::class]);
        $action = app(ProcessTwilioWebhookAction::class);

        $action->execute($this->respuesta('SM1', 'Paloma Valencia'));
        $action->execute($this->respuesta('SM2', 'Paloma Valencia'));

        Event::assertDispatchedTimes(ButtonResponseCaptured::class, 1);
        $this->assertSame(1, WhatsAppCampaignResponse::count());
    }

    public function test_una_persona_no_puede_votar_por_dos_candidatos(): void
    {
        Event::fake([ButtonResponseCaptured::class]);
        $action = app(ProcessTwilioWebhookAction::class);

        $action->execute($this->respuesta('SM1', 'Paloma Valencia'));
        $action->execute($this->respuesta('SM2', 'Iván Cepeda'));

        Event::assertDispatchedTimes(ButtonResponseCaptured::class, 1);
        $this->assertSame(['palom'], WhatsAppCampaignResponse::pluck('button_id')->all());
    }

    public function test_un_callback_viejo_no_pisa_un_estado_mas_nuevo(): void
    {
        $action = app(UpdateMessageStatusAction::class);

        $action->execute(['MessageSid' => 'SMoriginal', 'MessageStatus' => 'read']);
        $action->execute(['MessageSid' => 'SMoriginal', 'MessageStatus' => 'delivered']);
        $action->execute(['MessageSid' => 'SMoriginal', 'MessageStatus' => 'sent']);

        $this->mensaje->refresh();
        $this->assertSame('read', $this->mensaje->status);
        $this->assertNotNull($this->mensaje->delivered_at);
    }

    public function test_un_estado_desconocido_no_se_escribe(): void
    {
        app(UpdateMessageStatusAction::class)
            ->execute(['MessageSid' => 'SMoriginal', 'MessageStatus' => 'sending']);

        $this->assertSame('sent', $this->mensaje->refresh()->status);
    }

    public function test_leido_cuenta_como_entregado_y_enviado(): void
    {
        app(UpdateMessageStatusAction::class)
            ->execute(['MessageSid' => 'SMoriginal', 'MessageStatus' => 'read']);

        $campaign = Campaign::find($this->mensaje->campaign_id);

        $this->assertSame(1, $campaign->getDeliveredCount());
        $this->assertSame(1, $campaign->getSentCount());
    }
}
