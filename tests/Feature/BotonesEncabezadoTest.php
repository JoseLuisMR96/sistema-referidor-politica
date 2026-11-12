<?php

namespace Tests\Feature;

use App\Livewire\Admin\Ideologias\Index as IdeologiasIndex;
use App\Livewire\DiaE\Index as DiaEIndex;
use App\Livewire\Eventos\Equipo;
use App\Livewire\Presupuesto\Index as PresupuestoIndex;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los botones del encabezado (<x-slot name="header">) quedan fuera del
 * componente, así que abren su modal con Livewire.dispatch() y no con
 * wire:click (que ahí no hace nada).
 */
class BotonesEncabezadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_botones_del_encabezado_abren_su_modal_por_evento(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $tenant = $this->usarTenant();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('Administrador');

        // En la página completa el botón dispara el evento (no wire:click)
        $this->actingAs($admin)->get(route('equipo.index'))->assertOk()
            ->assertSee("Livewire.dispatch('equipo-abrir')", false);

        Livewire::actingAs($admin)->test(Equipo::class)
            ->dispatch('equipo-abrir')->assertSet('modal', true);

        Livewire::actingAs($admin)->test(PresupuestoIndex::class)
            ->dispatch('presupuesto-gasto')->assertSet('modalGasto', true)
            ->dispatch('presupuesto-editar')->assertSet('modalPresupuesto', true);

        Livewire::actingAs($admin)->test(DiaEIndex::class)
            ->dispatch('diae-eleccion', nueva: true)->assertSet('modalEleccion', true);

        // Clasificar ideología es solo de usuarios globales
        $global = User::factory()->create();
        $global->assignRole('Administrador');
        Livewire::actingAs($global)->test(IdeologiasIndex::class)
            ->dispatch('ideologia-abrir')->assertSet('modal', true);
    }
}
