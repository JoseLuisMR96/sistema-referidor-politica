<?php

namespace Tests\Feature;

use App\Enums\CargoEnum;
use App\Livewire\DiaE\Index;
use App\Livewire\DiaE\Reportar;
use App\Models\Departamento;
use App\Models\Eleccion;
use App\Models\Municipio;
use App\Models\PuestoVotacion;
use App\Models\ReporteMesa;
use App\Models\Tenant;
use App\Models\Testigo;
use App\Models\User;
use App\Services\EliminarTenant;
use App\Services\Territorio\ResultadosDiaE;
use App\Support\TenantContext;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DiaElectoralTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private PuestoVotacion $puesto;

    private PuestoVotacion $puestoHuila;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        Storage::fake('local');

        $meta = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        $huila = Departamento::create(['nombre' => 'HUILA', 'slug' => 'huila']);
        $villavo = Municipio::forceCreate(['departamento_id' => $meta->id, 'nombre' => 'VILLAVICENCIO', 'slug' => 'villavicencio', 'codigo_dane' => '50001']);
        $neiva = Municipio::forceCreate(['departamento_id' => $huila->id, 'nombre' => 'NEIVA', 'slug' => 'neiva', 'codigo_dane' => '41001']);
        $this->puesto = PuestoVotacion::create(['municipio_id' => $villavo->id, 'zona' => '01', 'codigo' => '01', 'nombre' => 'COLEGIO CENTRAL', 'mesas' => 10]);
        $this->puestoHuila = PuestoVotacion::create(['municipio_id' => $neiva->id, 'zona' => '01', 'codigo' => '01', 'nombre' => 'NEIVA PUESTO', 'mesas' => 10]);

        $this->tenant = Tenant::create(['nombre' => 'Henry', 'cargo' => CargoEnum::GOBERNACION, 'departamento_id' => $meta->id]);
        $this->usarTenant($this->tenant);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('Administrador');

        return $u;
    }

    public function test_las_mesas_se_leen_con_rangos(): void
    {
        $this->assertSame([1, 2, 3, 8], Testigo::mesasDe('1-3, 8'));
        $this->assertNull(Testigo::mesasDe('5-2'));
        $this->assertNull(Testigo::mesasDe('1-20', 10)); // el puesto tiene 10 mesas
        $this->assertNull(Testigo::mesasDe('abc'));
    }

    public function test_configura_eleccion_y_testigos(): void
    {
        $c = Livewire::actingAs($this->admin())->test(Index::class)
            ->call('abrirEleccion', true)
            ->set('eleccion.nombre', 'Gobernación 2027')
            ->set('opcionesTexto', "* Henry | Defensores\nRival Uno | Partido X\n\nRival Dos")
            ->call('guardarEleccion')->assertHasNoErrors();

        $e = Eleccion::sole();
        $this->assertSame(['Henry', 'Rival Uno', 'Rival Dos'], $e->opciones->pluck('nombre')->all());
        $this->assertTrue($e->opciones->first()->propia);

        // Fuera de la circunscripción y mesas que el puesto no tiene
        $c->set('vista', 'testigos')->call('abrirTestigo')
            ->set('testigo.nombre', 'Ana')->set('testigo.puesto_id', $this->puestoHuila->id)->set('testigo.mesas', '1-3')
            ->call('guardarTestigo')->assertHasErrors(['testigo.puesto_id'])
            ->set('testigo.puesto_id', $this->puesto->id)->set('testigo.mesas', '1-30')
            ->call('guardarTestigo')->assertHasErrors(['testigo.mesas'])
            ->set('testigo.mesas', '1-3')->set('testigo.telefono', '300 123 4567')
            ->call('guardarTestigo')->assertHasNoErrors()
            ->assertSee('Copiar enlace');

        $t = Testigo::sole();
        $this->assertSame(40, strlen($t->token));
        $this->assertSame('573001234567', $t->telefono);
    }

    private function eleccionConTestigo(): array
    {
        $e = Eleccion::create(['nombre' => 'Gobernación 2027', 'cargo' => 'gobernacion', 'fecha' => now()]);
        $henry = $e->opciones()->create(['nombre' => 'Henry', 'propia' => true, 'orden' => 0]);
        $rival = $e->opciones()->create(['nombre' => 'Rival', 'orden' => 1]);
        $t = Testigo::create(['eleccion_id' => $e->id, 'nombre' => 'Ana', 'puesto_id' => $this->puesto->id, 'mesas' => '1-3']);

        return [$e, $henry, $rival, $t];
    }

    public function test_el_testigo_reporta_sin_sesion_y_puede_corregir(): void
    {
        [$e, $henry, $rival, $t] = $this->eleccionConTestigo();
        app(TenantContext::class)->set(null); // como en la petición pública

        $this->get(route('testigo.reportar', $t->token))->assertOk()->assertSee('COLEGIO CENTRAL');
        $this->get('/testigo/'.str_repeat('x', 40))->assertNotFound();

        Livewire::test(Reportar::class, ['token' => $t->token])
            ->set('mesa', 5)->call('enviar')->assertHasErrors(['mesa']) // no es suya
            ->set('mesa', 1)
            ->set("votos.{$henry->id}", 120)->set("votos.{$rival->id}", 80)
            ->set('blanco', 5)->set('nulos', 3)
            ->set('foto', UploadedFile::fake()->image('e14.jpg'))
            ->call('enviar')->assertHasNoErrors()->assertSet('enviado', true);

        $r = ReporteMesa::withoutGlobalScope('tenant')->sole();
        $this->assertSame($this->tenant->id, $r->tenant_id);
        $this->assertSame(208, $r->total);
        Storage::disk('local')->assertExists($r->foto_ruta);

        // Corregir la misma mesa reemplaza el reporte
        Livewire::test(Reportar::class, ['token' => $t->token])
            ->set('mesa', 1)->assertSet("votos.{$henry->id}", 120)
            ->set("votos.{$henry->id}", 125)->call('enviar')->assertHasNoErrors();
        $this->assertSame(1, ReporteMesa::withoutGlobalScope('tenant')->count());
        $this->assertSame(213, ReporteMesa::withoutGlobalScope('tenant')->sole()->total);

        // Elección cerrada: no se puede reportar
        Eleccion::withoutGlobalScope('tenant')->whereKey($e->id)->update(['abierta' => false]);
        Livewire::test(Reportar::class, ['token' => $t->token])->set('mesa', 2)->call('enviar')->assertForbidden();
    }

    public function test_resultados_en_vivo(): void
    {
        [$e, $henry, $rival] = $this->eleccionConTestigo();
        ReporteMesa::create(['eleccion_id' => $e->id, 'puesto_id' => $this->puesto->id, 'mesa' => 1,
            'votos' => [$henry->id => 100, $rival->id => 50], 'blanco' => 10, 'nulos' => 5, 'total' => 165]);
        ReporteMesa::create(['eleccion_id' => $e->id, 'puesto_id' => $this->puesto->id, 'mesa' => 2,
            'votos' => [$henry->id => 40, $rival->id => 60], 'total' => 100]);

        $r = app(ResultadosDiaE::class)->calcular($e);
        $this->assertSame([2, 3, 10], [$r['reportadas'], $r['asignadas'], $r['mesas_puestos']]);
        $this->assertSame(260, $r['validos']); // 140 + 110 + 10 en blanco
        $this->assertSame('Henry', $r['opciones'][0]['nombre']);
        $this->assertSame(140, $r['opciones'][0]['votos']);
        $this->assertSame(53.8, $r['opciones'][0]['pct']);

        Livewire::actingAs($this->admin())->test(Index::class)->assertSee('Votos por candidato')->assertSee('Henry');
    }

    public function test_eliminar_el_candidato_borra_el_dia_e(): void
    {
        [$e, $henry] = $this->eleccionConTestigo();
        ReporteMesa::create(['eleccion_id' => $e->id, 'puesto_id' => $this->puesto->id, 'mesa' => 1, 'votos' => [$henry->id => 1], 'total' => 1]);
        app(TenantContext::class)->set(null);

        app(EliminarTenant::class)->eliminar($this->tenant);

        $this->assertSame(0, Eleccion::count() + Testigo::count() + ReporteMesa::count());
        $this->assertSame(2, PuestoVotacion::count()); // el catálogo es global
    }
}
