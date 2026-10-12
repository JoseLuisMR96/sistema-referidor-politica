<?php

namespace Tests\Feature;

use App\Actions\WhatsApp\ProcessTwilioWebhookAction;
use App\Livewire\Admin\Referrers\Create as ReferrersCreate;
use App\Livewire\Admin\Tenants\Index as TenantsIndex;
use App\Livewire\Public\RegistrationForm;
use App\Livewire\Registrations\Index as RegistrationsIndex;
use App\Livewire\Users\UserCreate;
use App\Models\CampaignMessage;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\Referrer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppCampaignResponse;
use App\Support\TenantContext;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cada candidato (tenant) ve y crea solo sus datos.
 */
class TenantAislamientoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $camilo;

    private Tenant $pedro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);

        $this->camilo = Tenant::create(['nombre' => 'Camilo Cfer', 'descripcion' => 'Concejo municipal']);
        $this->pedro = Tenant::create(['nombre' => 'Pedro Flores', 'descripcion' => 'Alcaldía']);
    }

    private function usuario(string $rol, ?Tenant $tenant = null): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant?->id]);
        $user->assignRole($rol);

        return $user;
    }

    /** Crea un referidor con un registro dentro del tenant dado. */
    private function datosDe(Tenant $tenant, string $code, string $cedula): PublicRegistration
    {
        $this->usarTenant($tenant);

        $referrer = Referrer::create(['name' => "Ref {$code}", 'code' => $code]);

        return PublicRegistration::create([
            'referrer_id' => $referrer->id,
            'full_name' => "Persona {$cedula}",
            'document_type' => 'CC',
            'document_number' => $cedula,
            'gender' => 'F',
            'residence_municipality' => 'X',
            'voting_municipality' => 'X',
        ]);
    }

    public function test_los_datos_se_crean_con_el_tenant_del_contexto(): void
    {
        $registro = $this->datosDe($this->camilo, 'CAMI0001', '111');

        $this->assertSame($this->camilo->id, $registro->tenant_id);
        $this->assertSame($this->camilo->id, $registro->referrer->tenant_id);
    }

    public function test_sin_tenant_no_se_puede_crear_nada(): void
    {
        app(TenantContext::class)->set(null);

        $this->expectException(\LogicException::class);
        Referrer::create(['name' => 'Huérfano', 'code' => 'HUER0001']);
    }

    public function test_usuario_restringido_solo_ve_su_tenant(): void
    {
        $deCamilo = $this->datosDe($this->camilo, 'CAMI0001', '111');
        $dePedro = $this->datosDe($this->pedro, 'PEDR0001', '222');

        $operador = $this->usuario('Operador', $this->camilo);

        Livewire::actingAs($operador)
            ->test(RegistrationsIndex::class)
            ->assertSee('Persona 111')
            ->assertDontSee('Persona 222');

        // El id de otro tenant en la URL da 404 (route model binding filtrado)
        $this->actingAs($operador)->get("/registros/{$deCamilo->id}")->assertOk();
        $this->actingAs($operador)->get("/registros/{$dePedro->id}")->assertNotFound();

        // Dashboard y export: el filtro de tenant convive con los JOIN
        $this->actingAs($operador)->get('/dashboard')->assertOk()->assertDontSee('Ref PEDR0001');
        $this->actingAs($operador)->get('/export/registros')->assertOk();
    }

    public function test_usuario_restringido_no_puede_cambiar_de_tenant(): void
    {
        $operador = $this->usuario('Operador', $this->camilo);

        $this->actingAs($operador)
            ->post('/tenant/seleccionar', ['tenant_id' => $this->pedro->id])
            ->assertForbidden();

        // Aunque meta otro tenant en la sesión, el contexto sigue siendo el suyo
        $this->actingAs($operador)
            ->withSession([TenantContext::SESSION_KEY => $this->pedro->id])
            ->get('/dashboard')
            ->assertOk();

        $this->assertSame($this->camilo->id, app(TenantContext::class)->id());
    }

    public function test_usuario_global_ve_todos_o_el_tenant_que_elija(): void
    {
        $this->datosDe($this->camilo, 'CAMI0001', '111');
        $this->datosDe($this->pedro, 'PEDR0001', '222');

        $admin = $this->usuario('Administrador');

        // "Todos los tenants"
        $this->actingAs($admin)->get('/registros')
            ->assertSee('Persona 111')
            ->assertSee('Persona 222');

        // Elige a Pedro
        $this->actingAs($admin)->post('/tenant/seleccionar', ['tenant_id' => $this->pedro->id]);

        $this->actingAs($admin)->get('/registros')
            ->assertDontSee('Persona 111')
            ->assertSee('Persona 222');
    }

    public function test_en_modo_todos_la_pantalla_abre_pero_no_deja_crear(): void
    {
        $admin = $this->usuario('Administrador');
        app(TenantContext::class)->set(null);

        Livewire::actingAs($admin)
            ->test(ReferrersCreate::class)
            ->assertSee('Elige un candidato para crear referidores')
            ->set('name', 'Sin candidato')
            ->call('save')
            ->assertHasErrors('tenant');

        $this->assertSame(0, Referrer::withoutGlobalScope('tenant')->count());
    }

    public function test_whatsapp_masivo_abre_en_modo_todos_con_selector_de_candidato(): void
    {
        $admin = $this->usuario('Administrador');

        // Antes redirigía al dashboard y parecía que el menú no funcionaba
        $this->actingAs($admin)->get('/whatsapp/campanas/nueva')
            ->assertOk()
            ->assertSee('Elige un candidato para enviar campañas')
            ->assertSee('Camilo Cfer');

        // Al elegir candidato vuelve a la misma pantalla, ya sin el aviso
        $this->actingAs($admin)
            ->from('/whatsapp/campanas/nueva')
            ->post('/tenant/seleccionar', ['tenant_id' => $this->camilo->id])
            ->assertRedirect('/whatsapp/campanas/nueva');

        $this->actingAs($admin)->get('/whatsapp/campanas/nueva')
            ->assertOk()
            ->assertDontSee('Elige un candidato para enviar campañas')
            ->assertSee('Candidato:');
    }

    public function test_formulario_publico_registra_en_el_tenant_del_referidor_y_permite_misma_cedula_en_otro(): void
    {
        $depto = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        $muni = Municipio::create(['departamento_id' => $depto->id, 'nombre' => 'Villavicencio', 'slug' => 'villavicencio']);

        // La cédula 99999999 ya está registrada con Camilo
        $this->datosDe($this->camilo, 'CAMI0001', '99999999');

        $this->usarTenant($this->pedro);
        Referrer::create(['name' => 'Ref Pedro', 'code' => 'PEDR0001']);

        // Formulario público: sin usuario ni tenant en contexto
        app(TenantContext::class)->set(null);

        Livewire::withQueryParams(['ref' => 'PEDR0001'])
            ->test(RegistrationForm::class)
            ->set('full_name', 'Persona Repetida')
            ->set('document_number', '99999999')
            ->set('gender', 'M')
            ->set('residence_department_id', $depto->id)
            ->set('residence_municipality_id', $muni->id)
            ->set('voting_department_id', $depto->id)
            ->set('voting_municipality_id', $muni->id)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('enviado', true);

        $this->assertSame(
            [$this->camilo->id, $this->pedro->id],
            PublicRegistration::where('document_number', '99999999')->orderBy('tenant_id')->pluck('tenant_id')->all(),
        );
    }

    public function test_respuesta_de_whatsapp_hereda_el_tenant_del_mensaje(): void
    {
        Event::fake();

        $campaignId = DB::table('campaigns')->insertGetId([
            'tenant_id' => $this->pedro->id,
            'name' => 'Campaña Pedro',
            'type' => 'template',
            'status' => 'sending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CampaignMessage::create([
            'tenant_id' => $this->pedro->id,
            'campaign_id' => $campaignId,
            'to' => 'whatsapp:+573001234567',
            'twilio_sid' => 'SMpedro',
            'status' => 'sent',
        ]);

        // Webhook: sin usuario ni tenant en contexto
        app(TenantContext::class)->set(null);

        app(ProcessTwilioWebhookAction::class)->execute([
            'MessageSid' => 'SMrespuesta',
            'From' => 'whatsapp:+573001234567',
            'Body' => 'Paloma Valencia',
            'OriginalRepliedMessageSid' => 'SMpedro',
        ]);

        $this->assertSame($this->pedro->id, WhatsAppCampaignResponse::sole()->tenant_id);
    }

    public function test_solo_usuarios_globales_gestionan_tenants(): void
    {
        $adminRestringido = $this->usuario('Administrador', $this->camilo);

        $this->actingAs($adminRestringido)->get('/admin/tenants')->assertForbidden();

        Livewire::actingAs($this->usuario('Administrador'))
            ->test(TenantsIndex::class)
            ->set('nombre', 'Wilmar Barbosa')
            ->set('descripcion', 'Candidato al Senado')
            ->set('cargo', 'senado') // nacional: no exige lugar
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('wilmar-barbosa', Tenant::where('nombre', 'Wilmar Barbosa')->value('slug'));
    }

    public function test_usuario_restringido_solo_crea_usuarios_de_su_tenant(): void
    {
        $adminCamilo = $this->usuario('Administrador', $this->camilo);

        Livewire::actingAs($adminCamilo)
            ->test(UserCreate::class)
            ->set('name', 'Nuevo Operador')
            ->set('email', 'nuevo@example.com')
            ->set('password', 'Secreta123*')
            ->set('password_confirmation', 'Secreta123*')
            ->set('tenant_id', (string) $this->pedro->id) // intenta otro tenant
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($this->camilo->id, User::where('email', 'nuevo@example.com')->value('tenant_id'));
    }
}
