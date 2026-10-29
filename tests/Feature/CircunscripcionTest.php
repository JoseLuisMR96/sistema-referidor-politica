<?php

namespace Tests\Feature;

use App\Enums\CargoEnum;
use App\Livewire\Admin\Tenants\Index as TenantsIndex;
use App\Livewire\Public\RegistrationForm;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\Referrer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardStats;
use App\Support\TenantContext;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cargo y lugar del candidato, y el análisis de apoyo dentro de su
 * circunscripción.
 */
class CircunscripcionTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $meta;

    private Departamento $huila;

    private Municipio $villavicencio;

    private Municipio $acacias;

    private Municipio $granada;

    private Municipio $neiva;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);

        $this->meta = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        $this->huila = Departamento::create(['nombre' => 'HUILA', 'slug' => 'huila']);

        $muni = fn (Departamento $d, string $n) => Municipio::create([
            'departamento_id' => $d->id, 'nombre' => $n, 'slug' => str($n)->slug(),
        ]);

        $this->villavicencio = $muni($this->meta, 'Villavicencio');
        $this->acacias = $muni($this->meta, 'Acacías');
        $this->granada = $muni($this->meta, 'Granada');
        $this->neiva = $muni($this->huila, 'Neiva');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Administrador');

        return $user;
    }

    private function registro(?Municipio $votaEn): void
    {
        static $doc = 5000;

        PublicRegistration::create([
            'full_name' => 'Persona',
            'document_type' => 'CC',
            'document_number' => (string) $doc++,
            'gender' => 'F',
            'residence_municipality' => 'X',
            'voting_municipality' => 'X',
            'voting_municipality_id' => $votaEn?->id,
        ]);
    }

    // --- Alta del tenant ---

    public function test_gobernacion_exige_departamento(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TenantsIndex::class)
            ->set('nombre', 'Wilmar Barbosa')
            ->set('cargo', CargoEnum::GOBERNACION->value)
            ->call('guardar')
            ->assertHasErrors(['departamento_id' => 'required']);
    }

    public function test_concejo_exige_municipio_del_mismo_departamento(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TenantsIndex::class)
            ->set('nombre', 'Camilo Cfer')
            ->set('cargo', CargoEnum::CONCEJO->value)
            ->set('departamento_id', $this->meta->id)
            ->set('municipio_id', $this->neiva->id) // Neiva es del Huila
            ->call('guardar')
            ->assertHasErrors(['municipio_id' => 'exists']);

        Livewire::actingAs($this->admin())
            ->test(TenantsIndex::class)
            ->set('nombre', 'Camilo Cfer')
            ->set('descripcion', 'Postulado al Concejo municipal')
            ->set('cargo', CargoEnum::CONCEJO->value)
            ->set('departamento_id', $this->meta->id)
            ->set('municipio_id', $this->villavicencio->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $tenant = Tenant::where('nombre', 'Camilo Cfer')->sole();
        $this->assertSame(CargoEnum::CONCEJO, $tenant->cargo);
        $this->assertSame('Concejo municipal · Villavicencio (META)', $tenant->circunscripcionTexto());
    }

    public function test_cargo_departamental_no_guarda_municipio(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TenantsIndex::class)
            ->set('nombre', 'Diputado Prueba')
            ->set('cargo', CargoEnum::ALCALDIA->value)
            ->set('departamento_id', $this->meta->id)
            ->set('municipio_id', $this->villavicencio->id)
            ->set('cargo', CargoEnum::ASAMBLEA->value) // cambia a departamental
            ->call('guardar')
            ->assertHasNoErrors();

        $tenant = Tenant::where('nombre', 'Diputado Prueba')->sole();
        $this->assertSame($this->meta->id, $tenant->departamento_id);
        $this->assertNull($tenant->municipio_id);
    }

    // --- Análisis ---

    public function test_departamental_cuenta_por_municipio_incluidos_los_que_van_en_cero(): void
    {
        $tenant = Tenant::create([
            'nombre' => 'Gobernador',
            'cargo' => CargoEnum::GOBERNACION,
            'departamento_id' => $this->meta->id,
        ]);
        $this->usarTenant($tenant);

        $this->registro($this->villavicencio);
        $this->registro($this->villavicencio);
        $this->registro($this->acacias);
        $this->registro($this->neiva); // vota en otro departamento
        $this->registro(null);         // sin municipio

        $c = app(DashboardStats::class)->circunscripcion($tenant);

        $this->assertSame(CargoEnum::AMBITO_DEPARTAMENTAL, $c['ambito']);
        $this->assertSame([5, 3, 1, 1], [$c['total'], $c['dentro'], $c['fuera'], $c['sin_dato']]);
        $this->assertSame(['con_registros' => 2, 'total' => 3], $c['cobertura']);
        $this->assertSame(
            ['Villavicencio' => 2, 'Acacías' => 1, 'Granada' => 0],
            collect($c['filas'])->pluck('value', 'label')->all(),
        );
    }

    public function test_municipal_separa_dentro_y_fuera_del_municipio(): void
    {
        $tenant = Tenant::create([
            'nombre' => 'Alcalde',
            'cargo' => CargoEnum::ALCALDIA,
            'departamento_id' => $this->meta->id,
            'municipio_id' => $this->villavicencio->id,
        ]);
        $this->usarTenant($tenant);

        $this->registro($this->villavicencio);
        $this->registro($this->acacias);

        $c = app(DashboardStats::class)->circunscripcion($tenant);

        $this->assertSame([2, 1, 1, 0], [$c['total'], $c['dentro'], $c['fuera'], $c['sin_dato']]);
        $this->assertNull($c['cobertura']);
    }

    public function test_tenant_sin_lugar_no_tiene_analisis(): void
    {
        $tenant = Tenant::create(['nombre' => 'Incompleto', 'cargo' => CargoEnum::GOBERNACION]);

        $this->assertNull(app(DashboardStats::class)->circunscripcion($tenant));
    }

    public function test_dashboard_muestra_la_circunscripcion_del_tenant_elegido(): void
    {
        $tenant = Tenant::create([
            'nombre' => 'Gobernador',
            'cargo' => CargoEnum::GOBERNACION,
            'departamento_id' => $this->meta->id,
        ]);
        $this->usarTenant($tenant);
        $this->registro($this->granada);

        $this->actingAs($this->admin())
            ->withSession([TenantContext::SESSION_KEY => $tenant->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Apoyo en la circunscripción')
            ->assertSee('Gobernación · META')
            ->assertSee('Presencia en');
    }

    public function test_formulario_publico_arranca_en_el_municipio_del_candidato(): void
    {
        $tenant = Tenant::create([
            'nombre' => 'Alcalde',
            'cargo' => CargoEnum::ALCALDIA,
            'departamento_id' => $this->meta->id,
            'municipio_id' => $this->acacias->id,
        ]);
        $this->usarTenant($tenant);
        Referrer::create(['name' => 'Ref', 'code' => 'ALCA0001']);
        app(TenantContext::class)->set(null);

        Livewire::withQueryParams(['ref' => 'ALCA0001'])
            ->test(RegistrationForm::class)
            ->assertSet('voting_department_id', $this->meta->id)
            ->assertSet('voting_municipality_id', $this->acacias->id)
            ->assertSet('residence_municipality_id', $this->acacias->id);
    }
}
