<?php

namespace Tests\Feature;

use App\Livewire\Admin\Redes\Index as RedesIndex;
use App\Models\Integracion;
use App\Models\RedCuenta;
use App\Models\RedMetrica;
use App\Models\RedTono;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Redes\RedesStats;
use App\Services\Redes\Tono\AnalizadorComentarios;
use App\Services\Redes\Tono\ClasificadorTono;
use App\Support\TenantContext;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Clasificador falso: no llama a la API real (ni gasta dinero).
 * "apoyo"/"voto" → a favor, "no le creo"/"fuera" → en contra, el resto neutro.
 */
class ClasificadorFalso implements ClasificadorTono
{
    public int $llamadas = 0;

    public array $recibidos = [];

    public function configurado(): bool
    {
        return true;
    }

    public function clasificar(string $candidato, string $postulacion, array $comentarios): array
    {
        $this->llamadas++;
        $this->recibidos = array_merge($this->recibidos, $comentarios);

        return [
            'tonos' => array_map(fn ($c) => match (true) {
                str_contains(mb_strtolower($c), 'apoyo') || str_contains(mb_strtolower($c), 'voto') => self::A_FAVOR,
                str_contains(mb_strtolower($c), 'no le creo') || str_contains(mb_strtolower($c), 'fuera') => self::EN_CONTRA,
                default => self::NEUTRO,
            }, $comentarios),
            'temas' => ['seguridad', 'vías'],
        ];
    }
}

class RivalesTonoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $wilmar;

    private ClasificadorFalso $clasificador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->wilmar = Tenant::create(['nombre' => 'Wilmar Barbosa']);
        $this->usarTenant($this->wilmar);

        $this->clasificador = new ClasificadorFalso;
        $this->app->instance(ClasificadorTono::class, $this->clasificador);

        $meta = Integracion::meta();
        $meta->credenciales = ['ig_user_id' => '1784000000', 'access_token' => 'token-mio', 'graph_version' => 'v26.0'];
        $meta->activo = true;
        $meta->save();
    }

    private function admin(?Tenant $tenant = null): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant?->id]);
        $user->assignRole('Administrador');

        return $user;
    }

    private function foto(RedCuenta $cuenta, string $fecha, int $seguidores, ?int $interacciones = null): void
    {
        RedMetrica::create([
            'red_cuenta_id' => $cuenta->id,
            'fecha' => $fecha,
            'seguidores' => $seguidores,
            'interacciones_7d' => $interacciones,
        ]);
    }

    // --- C. Rivales ---

    public function test_rival_solo_en_instagram_y_con_nombre(): void
    {
        $componente = Livewire::actingAs($this->admin($this->wilmar))->test(RedesIndex::class);

        $componente->set('tipo', 'rival')->set('red', 'facebook')->set('usuario', 'OtroCandidato')
            ->set('etiqueta', 'Otro')->call('guardarCuenta')
            ->assertHasErrors(['red' => 'in']);

        $componente->set('red', 'instagram')->set('etiqueta', '')->call('guardarCuenta')
            ->assertHasErrors(['etiqueta' => 'required']);

        // Aunque llegue un token, un rival nunca lo guarda
        $componente->set('etiqueta', 'Juan Pérez')->set('usuario', 'juanperez')
            ->set('cuenta_token', 'token-que-no-debe-quedar')->call('guardarCuenta')
            ->assertHasNoErrors();

        $rival = RedCuenta::where('usuario', 'juanperez')->sole();
        $this->assertTrue($rival->esRival());
        $this->assertNull($rival->access_token);
    }

    public function test_el_resumen_excluye_rivales_y_la_comparacion_ordena_por_crecimiento(): void
    {
        Carbon::setTestNow('2026-10-13 10:00:00');

        $propia = RedCuenta::create(['red' => 'instagram', 'usuario' => 'wilmar.barbosa']);
        $lento = RedCuenta::create(['red' => 'instagram', 'usuario' => 'grande', 'tipo' => 'rival', 'etiqueta' => 'Rival Grande']);
        $rapido = RedCuenta::create(['red' => 'instagram', 'usuario' => 'chico', 'tipo' => 'rival', 'etiqueta' => 'Rival Chico']);

        // Wilmar +2 %, Rival Grande +1 %, Rival Chico +10 %
        $this->foto($propia, '2026-10-06', 20000);
        $this->foto($propia, '2026-10-13', 20400, 442);
        $this->foto($lento, '2026-10-06', 50000);
        $this->foto($lento, '2026-10-13', 50500, 1000);
        $this->foto($rapido, '2026-10-06', 1000);
        $this->foto($rapido, '2026-10-13', 1100, 50);

        $stats = app(RedesStats::class);

        $this->assertSame(['wilmar.barbosa'], array_column($stats->resumen(), 'usuario'));
        $this->assertSame(20400, $stats->totales($stats->resumen())['seguidores']);

        $rivales = $stats->rivales();
        $this->assertSame(['chico', 'wilmar.barbosa', 'grande'], array_column($rivales, 'usuario'));
        $this->assertSame([10.0, 2.0, 1.0], array_column($rivales, 'cambio_7d_pct'));
        // 442 interacciones / 20.400 seguidores
        $this->assertSame(2.17, $rivales[1]['engagement']);

        Carbon::setTestNow();
    }

    public function test_sin_rivales_no_hay_comparacion(): void
    {
        RedCuenta::create(['red' => 'instagram', 'usuario' => 'wilmar.barbosa']);

        $this->assertSame([], app(RedesStats::class)->rivales());
    }

    // --- D. Tono de los comentarios ---

    private function cuentaConToken(): RedCuenta
    {
        return RedCuenta::create([
            'red' => 'instagram',
            'usuario' => 'wilmar.barbosa',
            'external_id' => '17841400000000001',
            'access_token' => 'token-wilmar',
        ]);
    }

    private function fakeComentarios(): void
    {
        Http::fake([
            'graph.facebook.com/*/17841400000000001/media*' => Http::response(['data' => [
                ['id' => 'M1', 'timestamp' => now()->subDays(2)->toIso8601String()],
                ['id' => 'M2', 'timestamp' => now()->subDays(40)->toIso8601String()], // vieja: se ignora
            ]]),
            'graph.facebook.com/*/M1/comments*' => Http::response(['data' => [
                ['id' => 'C1', 'text' => 'Todo mi apoyo Wilmar'],
                ['id' => 'C2', 'text' => 'Cuente con mi voto'],
                ['id' => 'C3', 'text' => 'No le creo nada'],
                ['id' => 'C4', 'text' => '¿Dónde es el evento?'],
            ]]),
        ]);
    }

    public function test_clasifica_comentarios_y_solo_guarda_conteos(): void
    {
        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->fakeComentarios();
        $cuenta = $this->cuentaConToken();

        $r = app(AnalizadorComentarios::class)->analizar($cuenta);

        $this->assertSame(['nuevos' => 4, 'a_favor' => 2, 'en_contra' => 1, 'neutro' => 1], $r);

        $fila = RedTono::sole();
        $this->assertSame([2, 1, 1], [$fila->a_favor, $fila->en_contra, $fila->neutro]);
        $this->assertSame(['seguridad' => 1, 'vías' => 1], $fila->temas);
        $this->assertSame($this->wilmar->id, $fila->tenant_id);

        // Nunca se pide el autor a Meta
        Http::assertSent(fn (Request $req) => str_contains($req->url(), '/comments') && $req['fields'] === 'id,text');

        // Ni el texto de los comentarios ni sus ids quedan en la base
        $volcado = json_encode([DB::table('redes_tono')->get(), DB::table('redes_comentarios_vistos')->get()]);
        foreach (['apoyo', 'voto', 'No le creo', 'evento', '"C1"', '"C3"'] as $rastro) {
            $this->assertStringNotContainsString($rastro, $volcado);
        }

        Carbon::setTestNow();
    }

    public function test_no_clasifica_dos_veces_el_mismo_comentario(): void
    {
        $this->fakeComentarios();
        $cuenta = $this->cuentaConToken();
        $analizador = app(AnalizadorComentarios::class);

        $analizador->analizar($cuenta);
        $segunda = $analizador->analizar($cuenta);

        $this->assertSame(0, $segunda['nuevos']);
        $this->assertSame(1, $this->clasificador->llamadas);
        $this->assertSame(4, RedTono::sole()->a_favor + RedTono::sole()->en_contra + RedTono::sole()->neutro);
    }

    public function test_sin_token_del_candidato_no_lee_comentarios(): void
    {
        Http::fake();
        $cuenta = RedCuenta::create(['red' => 'instagram', 'usuario' => 'wilmar.barbosa']);

        $this->expectExceptionMessage('token del candidato');
        app(AnalizadorComentarios::class)->analizar($cuenta);
    }

    public function test_el_comando_diario_tambien_analiza_comentarios(): void
    {
        $this->fakeComentarios();
        Http::fake([
            'graph.facebook.com/*/17841400000000001?*' => Http::response([
                'id' => '17841400000000001', 'followers_count' => 20102, 'media_count' => 2751, 'media' => ['data' => []],
            ]),
        ]);
        $this->cuentaConToken();
        app(TenantContext::class)->set(null);

        $this->artisan('redes:sincronizar')
            ->expectsOutputToContain('4 nuevos')
            ->assertSuccessful();

        $this->assertSame(1, RedTono::withoutGlobalScope('tenant')->count());
    }

    public function test_la_api_key_de_anthropic_se_guarda_cifrada(): void
    {
        Livewire::actingAs($this->admin())
            ->test(RedesIndex::class)
            ->set('anthropic_key', 'sk-ant-api03-secreta-1234')
            ->call('guardarAnthropic')
            ->assertHasNoErrors();

        $crudo = DB::table('integraciones')->where('proveedor', 'anthropic')->value('credenciales');
        $this->assertStringNotContainsString('sk-ant-api03-secreta-1234', $crudo);
        $this->assertSame(
            'sk-ant-api03-secreta-1234',
            Integracion::where('proveedor', 'anthropic')->sole()->credencial('api_key'),
        );
    }

    // --- Dashboard ---

    public function test_dashboard_de_redes_muestra_rivales_y_tono(): void
    {
        $propia = $this->cuentaConToken();
        $rival = RedCuenta::create(['red' => 'instagram', 'usuario' => 'juanperez', 'tipo' => 'rival', 'etiqueta' => 'Juan Pérez']);
        $this->foto($propia, today()->toDateString(), 20102, 442);
        $this->foto($rival, today()->toDateString(), 15000, 300);
        RedTono::create(['red_cuenta_id' => $propia->id, 'fecha' => today(), 'a_favor' => 6, 'en_contra' => 3, 'neutro' => 1, 'temas' => ['seguridad' => 3]]);

        $this->actingAs($this->admin($this->wilmar))->get('/dashboard/redes')
            ->assertOk()
            ->assertSee('Frente a sus rivales en Instagram')
            ->assertSee('Juan Pérez')
            ->assertSee('@juanperez')
            ->assertSee('Tono de los comentarios')
            ->assertSee('60%')       // a favor
            ->assertSee('seguridad · 3');
    }

    public function test_dashboard_explica_que_falta_el_token_para_el_tono(): void
    {
        RedCuenta::create(['red' => 'instagram', 'usuario' => 'wilmar.barbosa']);

        $this->actingAs($this->admin($this->wilmar))->get('/dashboard/redes')
            ->assertOk()
            ->assertSee('token de la cuenta oficial');
    }
}
