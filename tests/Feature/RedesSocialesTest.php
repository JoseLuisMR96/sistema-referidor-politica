<?php

namespace Tests\Feature;

use App\Livewire\Admin\Redes\Index as RedesIndex;
use App\Models\Integracion;
use App\Models\RedCuenta;
use App\Models\RedMetrica;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Redes\RedesSincronizador;
use App\Services\Redes\RedesStats;
use App\Support\TenantContext;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RedesSocialesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $camilo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->camilo = Tenant::create(['nombre' => 'Camilo Cfer']);
        $this->usarTenant($this->camilo);
    }

    private function usuario(string $rol, ?Tenant $tenant = null): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant?->id]);
        $user->assignRole($rol);

        return $user;
    }

    private function configurarMeta(): void
    {
        $meta = Integracion::meta();
        $meta->credenciales = [
            'app_id' => '123',
            'app_secret' => 'secreto-app',
            'graph_url' => 'https://graph.facebook.com',
            'graph_version' => 'v26.0',
            'ig_user_id' => '1784000000',
            'access_token' => 'token-mio',
        ];
        $meta->activo = true;
        $meta->save();
    }

    // --- Credenciales ---

    public function test_credenciales_se_guardan_cifradas_y_vacio_conserva(): void
    {
        $admin = $this->usuario('Administrador');

        Livewire::actingAs($admin)
            ->test(RedesIndex::class)
            ->set('app_id', '123')
            ->set('app_secret', 'secreto-super-largo-9999')
            ->set('access_token', 'EAAB-token-largo-abcd')
            ->call('guardarCredenciales')
            ->assertHasNoErrors();

        $crudo = DB::table('integraciones')->value('credenciales');
        $this->assertStringNotContainsString('EAAB-token-largo-abcd', $crudo);
        $this->assertStringNotContainsString('secreto-super-largo-9999', $crudo);

        // Guardar de nuevo con los campos secretos vacíos los conserva
        Livewire::actingAs($admin)
            ->test(RedesIndex::class)
            ->set('graph_version', 'v24.0')
            ->call('guardarCredenciales')
            ->assertHasNoErrors();

        $meta = Integracion::meta();
        $this->assertSame('EAAB-token-largo-abcd', $meta->credencial('access_token'));
        $this->assertSame('secreto-super-largo-9999', $meta->credencial('app_secret'));
        $this->assertSame('v24.0', $meta->credencial('graph_version'));
    }

    public function test_usuario_restringido_no_toca_las_credenciales_globales(): void
    {
        Livewire::actingAs($this->usuario('Administrador', $this->camilo))
            ->test(RedesIndex::class)
            ->set('access_token', 'intento')
            ->call('guardarCredenciales')
            ->assertForbidden();
    }

    // --- Sincronización ---

    public function test_instagram_sin_acceso_usa_business_discovery(): void
    {
        $this->configurarMeta();
        Carbon::setTestNow('2026-10-12 10:00:00');

        Http::fake(['graph.facebook.com/*' => Http::response([
            'business_discovery' => [
                'id' => '1789999',
                'username' => 'camilocfer',
                'followers_count' => 12500,
                'media_count' => 340,
                'media' => ['data' => [
                    ['like_count' => 100, 'comments_count' => 10, 'timestamp' => '2026-10-10T12:00:00+0000'],
                    ['like_count' => 50, 'comments_count' => 5, 'timestamp' => '2026-10-08T12:00:00+0000'],
                    ['like_count' => 999, 'comments_count' => 99, 'timestamp' => '2026-09-01T12:00:00+0000'], // > 7 días
                ]],
            ],
            'id' => '1784000000',
        ])]);

        $cuenta = RedCuenta::create(['red' => 'instagram', 'usuario' => 'camilocfer']);
        $m = app(RedesSincronizador::class)->sincronizar($cuenta);

        $this->assertSame(12500, (int) $m->seguidores);
        $this->assertSame(340, (int) $m->publicaciones);
        $this->assertSame(165, (int) $m->interacciones_7d);
        $this->assertSame($this->camilo->id, $m->tenant_id);
        $this->assertSame('1789999', $cuenta->fresh()->external_id);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v26.0/1784000000')
            && str_contains(urldecode($r->url()), 'business_discovery.username(camilocfer)')
            && $r['appsecret_proof'] === hash_hmac('sha256', 'token-mio', 'secreto-app'));

        Carbon::setTestNow();
    }

    public function test_facebook_sin_token_del_candidato_queda_con_error(): void
    {
        $this->configurarMeta();
        Http::fake();

        $cuenta = RedCuenta::create(['red' => 'facebook', 'usuario' => 'CamiloCferOficial']);

        try {
            app(RedesSincronizador::class)->sincronizar($cuenta);
            $this->fail('Debía fallar sin token');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('token de la página', $e->getMessage());
        }

        $this->assertNotNull($cuenta->fresh()->ultimo_error);
        Http::assertNothingSent();
    }

    public function test_facebook_con_token_del_candidato(): void
    {
        $this->configurarMeta();
        Carbon::setTestNow('2026-10-12 10:00:00');

        Http::fake(['graph.facebook.com/*' => Http::response([
            'id' => '555',
            'name' => 'Camilo Cfer',
            'followers_count' => 8000,
            'posts' => ['data' => [
                ['created_time' => '2026-10-11T12:00:00+0000', 'reactions' => ['summary' => ['total_count' => 40]], 'comments' => ['summary' => ['total_count' => 6]]],
                ['created_time' => '2026-08-01T12:00:00+0000', 'reactions' => ['summary' => ['total_count' => 900]], 'comments' => ['summary' => ['total_count' => 90]]],
            ]],
        ])]);

        $cuenta = RedCuenta::create(['red' => 'facebook', 'usuario' => 'CamiloCferOficial', 'access_token' => 'token-pagina']);
        $m = app(RedesSincronizador::class)->sincronizar($cuenta);

        $this->assertSame(8000, (int) $m->seguidores);
        $this->assertSame(46, (int) $m->interacciones_7d);
        Http::assertSent(fn (Request $r) => $r['access_token'] === 'token-pagina');

        // El token del candidato también va cifrado
        $this->assertStringNotContainsString('token-pagina', DB::table('redes_cuentas')->value('access_token'));

        Carbon::setTestNow();
    }

    public function test_el_comando_sincroniza_cada_tenant_con_su_tenant_id(): void
    {
        $this->configurarMeta();
        Http::fake(['graph.facebook.com/*' => Http::response([
            'business_discovery' => ['id' => '1', 'followers_count' => 10, 'media_count' => 1, 'media' => ['data' => []]],
        ])]);

        $pedro = Tenant::create(['nombre' => 'Pedro Flores']);

        RedCuenta::create(['red' => 'instagram', 'usuario' => 'camilocfer']);
        $this->usarTenant($pedro);
        RedCuenta::create(['red' => 'instagram', 'usuario' => 'pedroflores']);
        app(TenantContext::class)->set(null);

        $this->artisan('redes:sincronizar')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$this->camilo->id, $pedro->id],
            RedMetrica::withoutGlobalScope('tenant')->pluck('tenant_id')->all(),
        );
    }

    // --- Análisis ---

    public function test_crecimiento_semanal(): void
    {
        Carbon::setTestNow('2026-10-12 10:00:00'); // lunes

        $cuenta = RedCuenta::create(['red' => 'instagram', 'usuario' => 'camilocfer']);
        foreach (['2026-09-28' => 1000, '2026-10-05' => 1100, '2026-10-12' => 1210] as $fecha => $seguidores) {
            RedMetrica::create(['red_cuenta_id' => $cuenta->id, 'fecha' => $fecha, 'seguidores' => $seguidores]);
        }

        $r = app(RedesStats::class)->resumen()[0];

        $this->assertSame(1210, (int) $r['seguidores']);
        $this->assertSame(110, $r['cambio_7d']);
        $this->assertSame(10.0, $r['cambio_7d_pct']);
        $this->assertSame([1000, 1100, 1210], array_slice(array_column($r['semanas'], 'seguidores'), -3));

        Carbon::setTestNow();
    }

    public function test_dashboard_explica_como_empezar_si_el_candidato_no_tiene_cuentas(): void
    {
        $this->actingAs($this->usuario('Administrador', $this->camilo))->get('/dashboard/redes')
            ->assertOk()
            ->assertSee('Dashboard de redes sociales')
            ->assertSee('todavía no tiene cuentas de Facebook o Instagram registradas');
    }

    public function test_cada_candidato_ve_solo_sus_cuentas_y_el_dashboard_las_muestra(): void
    {
        RedCuenta::create(['red' => 'instagram', 'usuario' => 'camilocfer']);

        $pedro = Tenant::create(['nombre' => 'Pedro Flores']);
        $this->usarTenant($pedro);
        RedCuenta::create(['red' => 'instagram', 'usuario' => 'pedroflores']);

        $operadorCamilo = $this->usuario('Operador', $this->camilo);

        $this->actingAs($operadorCamilo)->get('/dashboard/redes')
            ->assertOk()
            ->assertSee('Seguidores por semana')
            ->assertSee('camilocfer')
            ->assertDontSee('pedroflores');

        // El dashboard de registros ya no mezcla redes
        $this->actingAs($operadorCamilo)->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard de registros')
            ->assertDontSee('camilocfer');
    }

    public function test_en_modo_todos_compara_candidatos(): void
    {
        $camiloCuenta = RedCuenta::create(['red' => 'instagram', 'usuario' => 'camilocfer']);
        RedMetrica::create(['red_cuenta_id' => $camiloCuenta->id, 'fecha' => today(), 'seguidores' => 500]);

        $pedro = Tenant::create(['nombre' => 'Pedro Flores', 'descripcion' => 'Alcaldía']);
        $this->usarTenant($pedro);
        $pedroCuenta = RedCuenta::create(['red' => 'instagram', 'usuario' => 'pedroflores']);
        RedMetrica::create(['red_cuenta_id' => $pedroCuenta->id, 'fecha' => today(), 'seguidores' => 900]);

        $this->actingAs($this->usuario('Administrador'))->get('/dashboard/redes')
            ->assertOk()
            ->assertSee('Comparativo entre candidatos')
            ->assertSeeInOrder(['Pedro Flores', '900', 'Camilo Cfer', '500']);
    }

    public function test_sin_permiso_no_ve_el_dashboard_de_redes(): void
    {
        $this->actingAs($this->usuario('Referidor', $this->camilo))->get('/dashboard/redes')->assertForbidden();
    }
}
