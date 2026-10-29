<?php

namespace Tests\Feature;

use App\Enums\CargoEnum;
use App\Livewire\Admin\Tenants\Index as TenantsIndex;
use App\Models\Departamento;
use App\Models\PublicRegistration;
use App\Models\Referrer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\HenryPalmaSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CandidatoHenryYEliminarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
    }

    private function adminGlobal(): User
    {
        $u = User::factory()->create(['tenant_id' => null]);
        $u->assignRole('Administrador');

        return $u;
    }

    public function test_seeder_de_henry_palma_es_idempotente(): void
    {
        Departamento::create(['nombre' => 'META', 'slug' => 'meta']);

        $this->seed(HenryPalmaSeeder::class);
        $this->seed(HenryPalmaSeeder::class);

        $henry = Tenant::where('slug', 'henry-palma')->sole();
        $this->assertSame('Henry Palma', $henry->nombre);
        $this->assertSame(CargoEnum::GOBERNACION, $henry->cargo);
        $this->assertSame('Defensores de la Patria', $henry->partido);
        $this->assertSame('¡FIRME POR LA PATRIA!', $henry->slogan);
        $this->assertSame('#00062A', $henry->color_primario);
        $this->assertSame('departamental', $henry->ambito());
    }

    private function candidatoConDatos(): Tenant
    {
        $tenant = Tenant::create(['nombre' => 'Candidato Prueba']);

        app(TenantContext::class)->con($tenant->id, function () {
            $ref = Referrer::create(['name' => 'Ref', 'code' => 'REF00001']);
            PublicRegistration::create([
                'full_name' => 'Persona', 'document_type' => 'CC', 'document_number' => '123',
                'gender' => 'F', 'phone' => '3001234567', 'referrer_id' => $ref->id,
            ]);
        });

        return $tenant;
    }

    public function test_hay_que_escribir_el_nombre_para_eliminar(): void
    {
        $tenant = $this->candidatoConDatos();

        Livewire::actingAs($this->adminGlobal())
            ->test(TenantsIndex::class)
            ->call('pedirEliminar', $tenant->id)
            ->assertSee('Registros: ')
            ->set('confirmacionNombre', 'otro nombre')
            ->call('eliminar')
            ->assertHasErrors('confirmacionNombre');

        $this->assertNotNull($tenant->fresh());
    }

    public function test_elimina_el_candidato_con_todos_sus_datos(): void
    {
        $tenant = $this->candidatoConDatos();
        $otro = Tenant::where('slug', 'tenant-inicial')->first();
        app(TenantContext::class)->con($otro->id, fn () => Referrer::create(['name' => 'Otro', 'code' => 'OTRO0001']));

        Livewire::actingAs($this->adminGlobal())
            ->test(TenantsIndex::class)
            ->call('pedirEliminar', $tenant->id)
            ->set('confirmacionNombre', 'Candidato Prueba')
            ->call('eliminar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tenants.index'));

        $this->assertNull($tenant->fresh());
        $this->assertSame(0, DB::table('public_registrations')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, DB::table('referrers')->where('tenant_id', $tenant->id)->count());
        // Los datos de otros candidatos no se tocan
        $this->assertSame(1, DB::table('referrers')->where('tenant_id', $otro->id)->count());
    }

    public function test_no_elimina_si_tiene_usuarios_asignados(): void
    {
        $tenant = $this->candidatoConDatos();
        $restringido = User::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::actingAs($this->adminGlobal())
            ->test(TenantsIndex::class)
            ->call('pedirEliminar', $tenant->id)
            ->assertSee('No se puede')
            ->set('confirmacionNombre', 'Candidato Prueba')
            ->call('eliminar')
            ->assertHasErrors('confirmacionNombre');

        $this->assertNotNull($tenant->fresh());
        // El usuario sigue restringido: nunca debe quedar global
        $this->assertSame($tenant->id, $restringido->fresh()->tenant_id);
    }

    public function test_un_usuario_restringido_no_puede_eliminar(): void
    {
        $tenant = $this->candidatoConDatos();
        $restringido = User::factory()->create(['tenant_id' => Tenant::where('slug', 'tenant-inicial')->value('id')]);
        $restringido->assignRole('Administrador');

        Livewire::actingAs($restringido)
            ->test(TenantsIndex::class)
            ->assertForbidden();

        $this->assertNotNull($tenant->fresh());
    }
}
