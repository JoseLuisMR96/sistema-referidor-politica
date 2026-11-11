<?php

namespace Tests\Feature;

use App\Livewire\ImportedPeople\Index as ImportadosIndex;
use App\Livewire\Users\UserIndex;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Roles;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
    }

    private function con(string $rol, ?Tenant $tenant = null): User
    {
        $u = User::factory()->create(['tenant_id' => $tenant?->id]);
        $u->assignRole($rol);

        return $u;
    }

    public function test_el_seeder_crea_todos_los_roles_del_catalogo(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(Roles::CATALOGO), Role::pluck('name')->all());
        // Se puede correr de nuevo (cada despliegue lo hace)
        $this->seed(RolesYPermisosSeeder::class);
        $this->assertSame(count(Roles::CATALOGO), Role::count());

        $tesorero = $this->con('Tesorero');
        $this->assertTrue($tesorero->can('presupuesto.gestionar'));
        $this->assertFalse($tesorero->can('registros.ver_todos'));

        $estratega = $this->con('Estratega');
        $this->assertTrue($estratega->can('historico.ver'));
        $this->assertFalse($estratega->can('registros.ver_todos')); // sin listados de personas
        $this->assertFalse($estratega->can('importados.ver'));
    }

    public function test_cada_rol_entra_a_la_primera_pagina_que_puede_ver(): void
    {
        $this->actingAs($this->con('Tesorero'))->get('/')->assertRedirect(route('presupuesto.index', absolute: false));
        $this->actingAs($this->con('Coordinador de testigos'))->get('/')->assertRedirect(route('diae.index', absolute: false));
        $this->actingAs($this->con('Comunicaciones'))->get('/')->assertRedirect(route('dashboard', absolute: false));

        // Ningún rol queda sin página de inicio
        foreach (array_keys(Roles::CATALOGO) as $rol) {
            $this->assertNotSame(route('profile.edit', absolute: false), Roles::inicio($this->con($rol)), "{$rol} no tiene página de inicio");
        }
    }

    public function test_el_padron_importado_exige_permiso_tambien_en_livewire(): void
    {
        $this->usarTenant();
        Livewire::actingAs($this->con('Analista'))->test(ImportadosIndex::class)->assertOk();
        Livewire::actingAs($this->con('Estratega'))->test(ImportadosIndex::class)->assertForbidden();
    }

    public function test_el_dueno_crea_el_candidato_con_su_cuenta_y_el_candidato_crea_su_equipo(): void
    {
        $duenio = $this->con('Administrador'); // global

        Livewire::actingAs($duenio)->test(\App\Livewire\Admin\Tenants\Formulario::class)
            ->set('nombre', 'Diana Paola')->set('cargo', 'senado')
            ->set('cuentaEmail', 'diana@example.com')
            ->set('cuentaPassword', 'Clave12345')->set('cuentaPassword_confirmation', 'otra-clave')
            ->call('guardar')->assertHasErrors(['cuentaPassword'])
            ->set('cuentaPassword_confirmation', 'Clave12345')
            ->call('guardar')->assertHasNoErrors();

        $tenant = Tenant::where('nombre', 'Diana Paola')->sole();
        $diana = User::where('email', 'diana@example.com')->sole();
        $this->assertSame($tenant->id, $diana->tenant_id);
        $this->assertSame('Diana Paola', $diana->name);
        $this->assertTrue($diana->hasRole('Administrador'));
        $this->assertNull($tenant->partido); // sin partido (p. ej. un candidato de prueba)

        // El partido se elige de la lista; "Otro" deja escribirlo
        Livewire::actingAs($duenio)->test(\App\Livewire\Admin\Tenants\Formulario::class, ['tenant' => $tenant])
            ->assertSee('PARTIDO LIBERAL')
            ->set('partidoOpcion', 'PARTIDO LIBERAL')->assertSet('partido', 'PARTIDO LIBERAL')
            ->call('guardar')->assertHasNoErrors();
        $this->assertSame('PARTIDO LIBERAL', $tenant->fresh()->partido);

        Livewire::actingAs($duenio)->test(\App\Livewire\Admin\Tenants\Formulario::class, ['tenant' => $tenant->fresh()])
            ->assertSet('partidoOpcion', 'PARTIDO LIBERAL')
            ->set('partidoOpcion', \App\Livewire\Admin\Tenants\Formulario::OTRO_PARTIDO)->assertSet('partido', '')
            ->set('partido', 'Movimiento Granada Unida')->call('guardar')->assertHasNoErrors();
        $this->assertSame('Movimiento Granada Unida', $tenant->fresh()->partido);
        Livewire::actingAs($duenio)->test(\App\Livewire\Admin\Tenants\Formulario::class, ['tenant' => $tenant->fresh()])
            ->assertSet('partidoOpcion', \App\Livewire\Admin\Tenants\Formulario::OTRO_PARTIDO);

        // La candidata crea a alguien de su equipo: queda en su tenant aunque pida otro
        $otro = Tenant::create(['nombre' => 'Otro']);
        Livewire::actingAs($diana)->test(\App\Livewire\Users\UserCreate::class)
            ->set('name', 'Tesorera Uno')->set('email', 'tesorera@example.com')
            ->set('password', 'Clave12345')->set('password_confirmation', 'Clave12345')
            ->set('tenant_id', (string) $otro->id)
            ->set('roles', ['Tesorero'])
            ->call('save')->assertHasNoErrors();

        $tesorera = User::where('email', 'tesorera@example.com')->sole();
        $this->assertSame($tenant->id, $tesorera->tenant_id);
        $this->assertTrue($tesorera->hasRole('Tesorero'));

        // Pero no puede crear candidatos
        Livewire::actingAs($diana)->test(\App\Livewire\Admin\Tenants\Formulario::class)->assertForbidden();
    }

    public function test_eliminar_usuarios_con_sus_reglas(): void
    {
        $henry = Tenant::create(['nombre' => 'Henry']);
        $otro = Tenant::create(['nombre' => 'Otro']);
        $global = $this->con('Administrador');
        $adminHenry = $this->con('Administrador', $henry);
        $deHenry = $this->con('Operador', $henry);
        $deOtro = $this->con('Operador', $otro);

        // Un administrador restringido no borra a otro tenant ni a un global
        Livewire::actingAs($adminHenry)->test(UserIndex::class)->call('eliminar', $deOtro->id)->assertForbidden();
        Livewire::actingAs($adminHenry)->test(UserIndex::class)->call('eliminar', $global->id)->assertForbidden();
        Livewire::actingAs($adminHenry)->test(UserIndex::class)->call('eliminar', $deHenry->id)->assertOk();
        $this->assertModelMissing($deHenry);

        // Nadie se borra a sí mismo, ni al último administrador global
        Livewire::actingAs($global)->test(UserIndex::class)->call('eliminar', $global->id);
        $this->assertModelExists($global);

        // Sin el permiso, nada
        Livewire::actingAs($this->con('Analista'))->test(UserIndex::class)->assertForbidden();
    }
}
