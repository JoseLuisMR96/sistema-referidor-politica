<?php

namespace Tests\Feature;

use App\Enums\CargoEnum;
use App\Livewire\Eventos\Equipo;
use App\Livewire\Eventos\Formulario;
use App\Livewire\Eventos\Index;
use App\Livewire\Eventos\Show;
use App\Models\Departamento;
use App\Models\EventoCampana;
use App\Models\EvidenciaEvento;
use App\Models\IntegranteEquipo;
use App\Models\Municipio;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EliminarTenant;
use App\Support\LugaresCampana;
use App\Support\TenantContext;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EventosCampanaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $gobernador;

    private Tenant $concejal;

    private Municipio $villavo;

    private Municipio $granada;

    private Municipio $neiva;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        Storage::fake('local');

        $meta = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        $huila = Departamento::create(['nombre' => 'HUILA', 'slug' => 'huila']);
        $this->villavo = Municipio::forceCreate(['departamento_id' => $meta->id, 'nombre' => 'VILLAVICENCIO', 'slug' => 'villavicencio', 'codigo_dane' => '50001']);
        $this->granada = Municipio::forceCreate(['departamento_id' => $meta->id, 'nombre' => 'GRANADA', 'slug' => 'granada', 'codigo_dane' => '50313']);
        $this->neiva = Municipio::forceCreate(['departamento_id' => $huila->id, 'nombre' => 'NEIVA', 'slug' => 'neiva', 'codigo_dane' => '41001']);

        $this->gobernador = Tenant::create(['nombre' => 'Gobernador Meta', 'cargo' => CargoEnum::GOBERNACION, 'departamento_id' => $meta->id]);
        $this->concejal = Tenant::create(['nombre' => 'Concejal Villavo', 'cargo' => CargoEnum::CONCEJO,
            'departamento_id' => $meta->id, 'municipio_id' => $this->villavo->id]);
        $this->usarTenant($this->gobernador);
    }

    private function usuario(string $rol = 'Administrador', ?Tenant $tenant = null): User
    {
        $u = User::factory()->create(['tenant_id' => $tenant?->id]);
        $u->assignRole($rol);

        return $u;
    }

    public function test_el_lugar_depende_del_cargo(): void
    {
        $dep = LugaresCampana::para($this->gobernador);
        $this->assertTrue($dep->permite($this->granada->id));
        $this->assertFalse($dep->permite($this->neiva->id));        // otro departamento
        $this->assertSame(2, $dep->totalMunicipios());

        $mun = LugaresCampana::para($this->concejal);
        $this->assertSame($this->villavo->id, $mun->municipioFijo());
        $this->assertTrue($mun->permite($this->villavo->id));
        $this->assertFalse($mun->permite($this->granada->id));      // concejo: solo su municipio

        $senado = LugaresCampana::para(Tenant::create(['nombre' => 'Senadora', 'cargo' => CargoEnum::SENADO]));
        $this->assertTrue($senado->permite($this->neiva->id));      // nacional: todo el país
        $this->assertFalse(LugaresCampana::para(Tenant::create(['nombre' => 'Sin cargo']))->completo());
    }

    public function test_registra_un_evento_con_dinero_y_evidencias(): void
    {
        $ana = IntegranteEquipo::create(['nombre' => 'Ana Pérez', 'rol' => 'emisario']);
        $luis = IntegranteEquipo::create(['nombre' => 'Luis Gómez', 'rol' => 'logistica']);
        $admin = $this->usuario();

        // Fuera de la circunscripción
        Livewire::actingAs($admin)->test(Formulario::class)
            ->set('titulo', 'Reunión en Neiva')->set('municipio_id', $this->neiva->id)
            ->call('guardar')->assertHasErrors(['municipio_id']);

        // Dinero sin responsable
        Livewire::actingAs($admin)->test(Formulario::class)
            ->set('titulo', 'Reunión con líderes')->set('municipio_id', $this->granada->id)
            ->set('monto_asignado', '500.000')
            ->call('guardar')->assertHasErrors(['responsable_id']);

        Livewire::actingAs($admin)->test(Formulario::class)
            ->set('titulo', 'Reunión con líderes')
            ->set('municipio_id', $this->granada->id)
            ->set('direccion', 'Salón comunal')
            ->set('coordenadas', '3.5466, -73.7062')
            ->set('responsable_id', $ana->id)
            ->set('integrantes', [(string) $luis->id, (string) $ana->id])
            ->set('monto_asignado', '500.000')
            ->set('monto_gastado', '450.000')
            ->set('archivos', [UploadedFile::fake()->image('foto.jpg'), UploadedFile::fake()->create('soporte.pdf', 50, 'application/pdf')])
            ->call('guardar')
            ->assertHasNoErrors();

        $e = EventoCampana::sole();
        $this->assertSame($this->gobernador->id, $e->tenant_id);
        $this->assertSame([500000.0, 450000.0], [(float) $e->monto_asignado, (float) $e->monto_gastado]);
        $this->assertSame([3.5466, -73.7062], [$e->latitud, $e->longitud]);
        $this->assertSame($e->departamento_id, $this->granada->departamento_id);
        $this->assertSame([$luis->id], $e->integrantes()->pluck('equipo_campana.id')->all()); // el responsable no se repite
        $this->assertSame(['documento', 'foto'], $e->evidencias()->orderBy('tipo')->pluck('tipo')->all());
        foreach ($e->evidencias as $ev) {
            Storage::disk('local')->assertExists($ev->ruta);
            $this->assertStringStartsWith("eventos/{$this->gobernador->id}/{$e->id}/", $ev->ruta);
        }
    }

    public function test_las_evidencias_son_privadas_del_candidato(): void
    {
        $e = EventoCampana::create(['titulo' => 'Visita', 'tipo' => 'visita', 'fecha' => now(), 'municipio_id' => $this->villavo->id]);
        Storage::disk('local')->put('eventos/x/foto.jpg', 'contenido');
        $ev = $e->evidencias()->create(['tenant_id' => $this->gobernador->id, 'tipo' => 'foto', 'ruta' => 'eventos/x/foto.jpg', 'nombre' => 'foto.jpg', 'mime' => 'image/jpeg']);

        $this->actingAs($this->usuario('Analista', $this->gobernador))->get(route('eventos.evidencia', $ev))->assertOk();
        // Otro candidato no la ve, ni el evento
        $otro = $this->usuario('Administrador', $this->concejal);
        $this->actingAs($otro)->get(route('eventos.evidencia', $ev->id))->assertNotFound();
        $this->actingAs($otro)->get(route('eventos.show', $e->id))->assertNotFound();
        // Sin sesión, nada
        auth()->logout();
        $this->get(route('eventos.evidencia', $ev->id))->assertRedirect();
    }

    public function test_el_analista_consulta_pero_no_registra(): void
    {
        $analista = $this->usuario('Analista', $this->gobernador);

        $this->actingAs($analista)->get(route('eventos.index'))->assertOk()->assertSee('Eventos de campaña');
        $this->actingAs($analista)->get(route('eventos.create'))->assertForbidden();

        $e = EventoCampana::create(['titulo' => 'Visita', 'tipo' => 'visita', 'fecha' => now(), 'municipio_id' => $this->villavo->id]);
        Livewire::actingAs($analista)->test(Show::class, ['evento' => $e])->call('eliminar')->assertForbidden();
        $this->assertModelExists($e);
    }

    public function test_el_listado_suma_dinero_y_municipios_sin_cancelados(): void
    {
        $ana = IntegranteEquipo::create(['nombre' => 'Ana Pérez', 'rol' => 'emisario']);
        $crear = fn ($mun, $monto, $estado = 'realizado', $candidato = false) => EventoCampana::create([
            'titulo' => 'Evento', 'tipo' => 'reunion', 'estado' => $estado, 'fecha' => now(), 'municipio_id' => $mun->id,
            'monto_asignado' => $monto, 'monto_gastado' => $monto / 2, 'responsable_id' => $ana->id, 'asistio_candidato' => $candidato,
        ]);
        $crear($this->villavo, 100000, 'realizado', true);
        $crear($this->granada, 200000);
        $crear($this->granada, 999999, 'cancelado');

        Livewire::actingAs($this->usuario())->test(Index::class)
            ->assertViewHas('totales', fn ($t) => (int) $t->eventos === 2 && (int) $t->municipios === 2
                && (float) $t->asignado === 300000.0 && (float) $t->gastado === 150000.0 && (int) $t->con_candidato === 1)
            ->assertViewHas('totalMunicipios', 2)
            ->assertViewHas('porPersona', fn ($p) => (float) $p->first()->asignado === 300000.0)
            ->assertSee('Ana Pérez')
            ->set('quien', 'candidato')
            ->assertViewHas('totales', fn ($t) => (int) $t->eventos === 1);
    }

    public function test_el_equipo_se_desactiva_si_ya_tiene_eventos(): void
    {
        $admin = $this->usuario();

        Livewire::actingAs($admin)->test(Equipo::class)
            ->call('abrir')
            ->set('nombre', 'Ana Pérez')->set('rol', 'emisario')->set('telefono', '300 123 4567')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('modal', false);

        $ana = IntegranteEquipo::sole();
        $this->assertSame('573001234567', $ana->telefono); // PhoneNumber::digits() con indicativo
        EventoCampana::create(['titulo' => 'Visita', 'tipo' => 'visita', 'fecha' => now(), 'municipio_id' => $this->villavo->id, 'responsable_id' => $ana->id]);

        Livewire::actingAs($admin)->test(Equipo::class)->call('eliminar', $ana->id);
        $this->assertFalse($ana->fresh()->activo); // no se borra: tiene eventos
    }

    public function test_eliminar_el_candidato_borra_sus_eventos_y_archivos(): void
    {
        $ana = IntegranteEquipo::create(['nombre' => 'Ana Pérez', 'rol' => 'emisario']);
        $e = EventoCampana::create(['titulo' => 'Visita', 'tipo' => 'visita', 'fecha' => now(), 'municipio_id' => $this->villavo->id, 'responsable_id' => $ana->id]);
        $e->integrantes()->attach($ana->id);
        $ruta = "eventos/{$this->gobernador->id}/{$e->id}/foto.jpg";
        Storage::disk('local')->put($ruta, 'x');
        $e->evidencias()->create(['tenant_id' => $this->gobernador->id, 'tipo' => 'foto', 'ruta' => $ruta, 'nombre' => 'foto.jpg']);
        app(TenantContext::class)->set(null);

        $this->assertArrayHasKey('Eventos de campaña', app(EliminarTenant::class)->resumen($this->gobernador));
        app(EliminarTenant::class)->eliminar($this->gobernador);

        $this->assertSame(0, EventoCampana::count() + IntegranteEquipo::count() + EvidenciaEvento::count());
        Storage::disk('local')->assertMissing($ruta);
    }
}
