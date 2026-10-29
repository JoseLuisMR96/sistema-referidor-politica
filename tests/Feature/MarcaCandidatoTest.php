<?php

namespace Tests\Feature;

use App\Enums\CargoEnum;
use App\Livewire\Admin\Tenants\Index as TenantsIndex;
use App\Models\Referrer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Marca;
use App\Support\TenantContext;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Identidad visual por candidato: colores, logo, foto, partido y slogan.
 */
class MarcaCandidatoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        Storage::fake('public');
    }

    private function admin(?Tenant $tenant = null): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant?->id]);
        $user->assignRole('Administrador');

        return $user;
    }

    private function candidato(): Tenant
    {
        return Tenant::create([
            'nombre' => 'Candidato Demo',
            'partido' => 'Partido Demo',
            'slogan' => 'Por un Meta mejor',
            'cargo' => CargoEnum::SENADO,
            'color_primario' => '#0b3d91',
            'color_secundario' => '#f5c400',
        ]);
    }

    // --- Colores ---

    public function test_la_paleta_pone_el_color_elegido_en_el_tono_600(): void
    {
        $paleta = Marca::paleta('#0b3d91');

        $this->assertSame('11 61 145', $paleta[600]);
        $this->assertCount(11, $paleta);
        // Los claros se acercan al blanco y los oscuros al negro
        $this->assertGreaterThan(240, (int) explode(' ', $paleta[50])[0]);
        $this->assertLessThan(10, (int) explode(' ', $paleta[950])[0]);
    }

    public function test_el_texto_sobre_cada_color_es_el_que_mejor_se_lee(): void
    {
        $this->assertSame('#ffffff', Marca::textoSobre('#0b3d91'));
        $this->assertSame('#111827', Marca::textoSobre('#f5c400'));
        $this->assertSame(21.0, Marca::contraste('#000000', '#ffffff'));
    }

    public function test_sin_candidato_o_sin_colores_no_redefine_nada(): void
    {
        $this->assertSame('', Marca::css(null));
        $this->assertSame('', Marca::css(new Tenant(['nombre' => 'Sin colores'])));
    }

    // --- Pantalla de tenants ---

    public function test_guarda_la_identidad_visual_con_logo_y_foto(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TenantsIndex::class)
            ->set('nombre', 'Candidato Demo')
            ->set('cargo', 'senado')
            ->set('partido', 'Partido Demo')
            ->set('slogan', 'Por un Meta mejor')
            ->set('color_primario', '#0b3d91')
            ->set('color_secundario', '#f5c400')
            ->set('logo', UploadedFile::fake()->image('logo.png', 200, 200))
            ->set('foto', UploadedFile::fake()->image('foto.jpg', 400, 400))
            ->call('guardar')
            ->assertHasNoErrors();

        $t = Tenant::where('nombre', 'Candidato Demo')->sole();
        $this->assertSame(['Partido Demo', 'Por un Meta mejor', '#0b3d91', '#f5c400'], [$t->partido, $t->slogan, $t->color_primario, $t->color_secundario]);
        Storage::disk('public')->assertExists($t->logo_path);
        Storage::disk('public')->assertExists($t->foto_path);
        $this->assertStringStartsWith("tenants/{$t->id}/", $t->logo_path);
    }

    public function test_rechaza_colores_invalidos_y_svg(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TenantsIndex::class)
            ->set('nombre', 'X Candidato')
            ->set('cargo', 'senado')
            ->set('color_primario', 'rojo')
            ->set('logo', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
            ->call('guardar')
            ->assertHasErrors(['color_primario', 'logo']);
    }

    public function test_cambiar_el_logo_borra_el_anterior(): void
    {
        $t = $this->candidato();
        $componente = Livewire::actingAs($this->admin())->test(TenantsIndex::class);

        $componente->call('editar', $t->id)->set('logo', UploadedFile::fake()->image('a.png'))->call('guardar');
        $primero = $t->fresh()->logo_path;

        $componente->call('editar', $t->id)->set('logo', UploadedFile::fake()->image('b.png'))->call('guardar');

        Storage::disk('public')->assertMissing($primero);
        Storage::disk('public')->assertExists($t->fresh()->logo_path);
    }

    // --- La app se pinta con el candidato elegido ---

    public function test_la_app_usa_los_colores_del_candidato_elegido(): void
    {
        $t = $this->candidato();
        $admin = $this->admin();

        // "Todos": sin redefinir colores (rige el índigo por defecto)
        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('--marca-600', false);

        $this->actingAs($admin)->withSession([TenantContext::SESSION_KEY => $t->id])->get('/dashboard')
            ->assertOk()
            ->assertSee('--marca-600: 11 61 145;', false)
            ->assertSee('--marca-sec: 245 196 0;', false)
            ->assertSee('<title>Candidato Demo · ', false)
            ->assertSee('Partido Demo');
    }

    public function test_el_usuario_restringido_ve_siempre_la_marca_de_su_candidato(): void
    {
        $t = $this->candidato();

        $this->actingAs($this->admin($t))->get('/dashboard')
            ->assertOk()
            ->assertSee('--marca-600: 11 61 145;', false);
    }

    public function test_el_formulario_publico_muestra_la_cabecera_del_candidato(): void
    {
        $t = $this->candidato();
        $this->usarTenant($t);
        Referrer::create(['name' => 'Ref', 'code' => 'DEMO0001']);
        app(TenantContext::class)->set(null);

        $this->get('/registro?ref=DEMO0001')
            ->assertOk()
            ->assertSee('Candidato Demo')
            ->assertSee('Partido Demo')
            ->assertSee('Por un Meta mejor')
            ->assertSee('--marca-600: 11 61 145;', false);
    }

    public function test_la_ruta_publica_sirve_el_logo_y_no_otros_archivos(): void
    {
        $t = $this->candidato();
        $ruta = UploadedFile::fake()->image('logo.png')->store("tenants/{$t->id}", 'public');
        $t->update(['logo_path' => $ruta]);

        $this->get($t->fresh()->logoUrl())->assertOk();
        $this->get("/marca/{$t->slug}/foto")->assertNotFound();      // no tiene foto
        $this->get("/marca/{$t->slug}/../.env")->assertNotFound();
        $this->get('/marca/no-existe/logo')->assertNotFound();
    }
}
