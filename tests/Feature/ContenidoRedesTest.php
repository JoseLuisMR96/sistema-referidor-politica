<?php

namespace Tests\Feature;

use App\Models\Integracion;
use App\Models\RedCuenta;
use App\Models\RedMetrica;
use App\Models\RedPublicacion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Redes\RedesSincronizador;
use App\Services\Redes\RedesStats;
use App\Services\Redes\Temas\ClasificadorTemas;
use App\Services\Redes\Temas\TemasPublicaciones;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Temas por palabra clave, sin llamar a la API real. */
class ClasificadorTemasFalso implements ClasificadorTemas
{
    public int $llamadas = 0;

    public function configurado(): bool
    {
        return true;
    }

    public function clasificar(array $textos): array
    {
        $this->llamadas++;

        return array_map(fn ($t) => str_contains(mb_strtolower($t), 'seguridad') ? 'seguridad'
            : (str_contains(mb_strtolower($t), 'vía') ? 'vias' : 'otro'), $textos);
    }
}

class ContenidoRedesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $wilmar;

    private ClasificadorTemasFalso $temas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->wilmar = Tenant::create(['nombre' => 'Wilmar Barbosa']);
        $this->usarTenant($this->wilmar);

        $this->temas = new ClasificadorTemasFalso;
        $this->app->instance(ClasificadorTemas::class, $this->temas);

        $meta = Integracion::meta();
        $meta->credenciales = ['ig_user_id' => '1784000000', 'access_token' => 'token-mio', 'graph_version' => 'v26.0'];
        $meta->activo = true;
        $meta->save();
    }

    private array $media = [];

    /** Respuesta de Business Discovery; llamarla de nuevo cambia lo que devuelve. */
    private function discovery(array $media): void
    {
        $primera = $this->media === [];
        $this->media = $media;

        // Http::fake acumula y gana el primero: un solo fake que lee $this->media
        if ($primera) {
            Http::fake(['graph.facebook.com/*' => fn () => Http::response([
                'business_discovery' => [
                    'id' => '1789999', 'username' => 'wilmar', 'followers_count' => 10000, 'media_count' => 300,
                    'media' => ['data' => $this->media],
                ],
                'id' => '1784000000',
            ])]);
        }
    }

    public function test_la_sincronizacion_guarda_las_publicaciones_y_conserva_el_tema(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');
        $cuenta = RedCuenta::create(['red' => 'instagram', 'usuario' => 'wilmar']);

        $this->discovery([
            ['id' => 'm1', 'caption' => 'Más seguridad para el Meta', 'media_type' => 'VIDEO', 'permalink' => 'https://instagram.com/p/m1',
                'like_count' => 300, 'comments_count' => 20, 'view_count' => 9000, 'timestamp' => '2026-11-11T23:00:00+0000'],
            ['id' => 'm2', 'caption' => 'Recorrido por la vía', 'media_type' => 'IMAGE', 'comments_count' => 5, 'timestamp' => '2026-11-10T15:00:00+0000'],
            ['like_count' => 1, 'timestamp' => '2026-11-10T15:00:00+0000'], // sin id: no se guarda
        ]);

        app(RedesSincronizador::class)->sincronizar($cuenta);

        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), 'view_count'));
        $this->assertSame(2, RedPublicacion::count());
        $m1 = RedPublicacion::where('media_id', 'm1')->sole();
        $this->assertSame($this->wilmar->id, $m1->tenant_id);
        $this->assertSame('VIDEO', $m1->tipo);
        $this->assertSame(9000, $m1->reproducciones);
        $this->assertNull(RedPublicacion::where('media_id', 'm2')->value('me_gusta')); // me gusta ocultos

        // Temas: una sola llamada para las dos
        $this->assertSame(2, app(TemasPublicaciones::class)->clasificar($cuenta));
        $this->assertSame('seguridad', $m1->fresh()->tema);
        $this->assertSame('vias', RedPublicacion::where('media_id', 'm2')->value('tema'));

        // Al día siguiente cambian los conteos: se actualizan y el tema queda.
        // Si cambia el texto, el tema se vuelve a clasificar.
        $this->discovery([
            ['id' => 'm1', 'caption' => 'Más seguridad para el Meta', 'media_type' => 'VIDEO', 'like_count' => 500, 'comments_count' => 40, 'timestamp' => '2026-11-11T23:00:00+0000'],
            ['id' => 'm2', 'caption' => 'Recorrido por Granada', 'media_type' => 'IMAGE', 'comments_count' => 5, 'timestamp' => '2026-11-10T15:00:00+0000'],
        ]);
        app(RedesSincronizador::class)->sincronizar($cuenta);

        $m1->refresh();
        $this->assertSame(500, $m1->me_gusta);
        $this->assertSame(9000, $m1->reproducciones); // no vino hoy: se conserva
        $this->assertSame('seguridad', $m1->tema);
        $this->assertNull(RedPublicacion::where('media_id', 'm2')->value('tema'));
        $this->assertSame(2, RedPublicacion::count());

        Carbon::setTestNow();
    }

    public function test_el_comando_diario_clasifica_los_temas(): void
    {
        RedCuenta::create(['red' => 'instagram', 'usuario' => 'wilmar']);
        $this->discovery([
            ['id' => 'm1', 'caption' => 'Seguridad', 'media_type' => 'IMAGE', 'like_count' => 1, 'comments_count' => 0, 'timestamp' => now()->toIso8601String()],
        ]);

        $this->artisan('redes:sincronizar')->assertSuccessful();

        $this->assertSame('seguridad', RedPublicacion::sole()->tema);
        $this->assertSame(1, $this->temas->llamadas);
    }

    private function publicacion(RedCuenta $c, string $id, string $cuando, string $tipo, int $gusta, ?string $tema = null): void
    {
        $p = new RedPublicacion([
            'red_cuenta_id' => $c->id, 'media_id' => $id, 'tipo' => $tipo, 'publicada_at' => Carbon::parse($cuando),
            'texto' => "Texto {$id}", 'me_gusta' => $gusta, 'comentarios' => 0, 'tema' => $tema,
        ]);
        $p->save();
    }

    public function test_que_contenido_funciona_compara_por_rendimiento(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00'); // jueves, UTC

        $propia = RedCuenta::create(['red' => 'instagram', 'usuario' => 'wilmar']);
        $rival = RedCuenta::create(['red' => 'instagram', 'usuario' => 'rival', 'tipo' => 'rival', 'etiqueta' => 'Rival Grande']);
        RedMetrica::create(['red_cuenta_id' => $propia->id, 'fecha' => today(), 'seguidores' => 1000]);
        RedMetrica::create(['red_cuenta_id' => $rival->id, 'fecha' => today(), 'seguidores' => 10000]);

        // Candidato: los videos rinden 5 y las fotos 1 (por cada 100 seguidores).
        // Las 01:00 UTC son las 20:00 en Colombia (noche del día anterior).
        $this->publicacion($propia, 'a', '2026-11-11 01:00:00', 'VIDEO', 50, 'seguridad'); // martes noche
        $this->publicacion($propia, 'b', '2026-11-04 01:00:00', 'VIDEO', 50, 'seguridad'); // martes noche
        $this->publicacion($propia, 'c', '2026-11-10 15:00:00', 'IMAGE', 10, 'gira');      // martes 10:00
        $this->publicacion($propia, 'd', '2026-11-03 15:00:00', 'IMAGE', 10, 'gira');
        // Rival: más interacciones en bruto (300), pero rinde 3
        $this->publicacion($rival, 'r1', '2026-11-11 15:00:00', 'IMAGE', 300, 'contraste');
        $this->publicacion($rival, 'r2', '2026-09-01 15:00:00', 'IMAGE', 999); // fuera de los 30 días

        $c = app(RedesStats::class)->contenido();

        $this->assertTrue($c['hay']);
        $this->assertTrue($c['hay_rivales']);
        $this->assertSame(5.0, $c['por_tipo']['VIDEO']['candidato']['engagement']);
        $this->assertSame(1.0, $c['por_tipo']['IMAGE']['candidato']['engagement']);
        $this->assertSame(3.0, $c['por_tipo']['IMAGE']['rivales']['engagement']);
        $this->assertSame(2, $c['por_franja']['noche']['candidato']['n']);
        $this->assertSame(4, $c['por_dia'][2]['candidato']['n']); // las 4 del candidato son martes en Colombia (ISO 2)
        $this->assertSame(['tipo' => 'VIDEO', 'dia' => 2, 'franja' => 'noche', 'tema' => 'seguridad'], $c['mejor']);

        // Ritmo: 4 publicaciones en 30 días ≈ 0,9 por semana; el rival va después
        $this->assertSame(['wilmar', 'rival'], array_column($c['cuentas'], 'usuario'));
        $this->assertSame(0.9, $c['cuentas'][0]['por_semana']);

        // Top de la semana (desde el 5 de noviembre), ordenado por rendimiento:
        // el video del candidato (5) gana al post del rival (3) aunque tenga menos me gusta
        $this->assertSame(['@wilmar', 'Rival Grande'], array_slice(array_column($c['top'], 'cuenta'), 0, 2));
        $this->assertCount(3, $c['top']);

        Carbon::setTestNow();
    }

    public function test_el_dashboard_muestra_la_seccion(): void
    {
        $admin = User::factory()->create(['tenant_id' => $this->wilmar->id]);
        $admin->assignRole('Administrador');
        $propia = RedCuenta::create(['red' => 'instagram', 'usuario' => 'wilmar']);
        RedMetrica::create(['red_cuenta_id' => $propia->id, 'fecha' => today(), 'seguidores' => 1000]);

        $this->actingAs($admin)->get('/dashboard/redes')->assertOk()
            ->assertSee('Qué contenido funciona en Instagram')
            ->assertSee('Todavía no hay publicaciones guardadas');

        $this->publicacion($propia, 'a', now()->subDay()->toDateTimeString(), 'VIDEO', 50, 'seguridad');
        $this->publicacion($propia, 'b', now()->subDays(2)->toDateTimeString(), 'VIDEO', 30, 'seguridad');

        $this->actingAs($admin)->get('/dashboard/redes')->assertOk()
            ->assertSee('Lo que mejor le funciona al candidato')
            ->assertSee('Video o reel')
            ->assertSee('Mejores publicaciones de los últimos 7 días');
    }
}
