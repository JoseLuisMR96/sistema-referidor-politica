<?php

namespace Tests\Feature;

use App\Livewire\Presupuesto\Index;
use App\Models\EventoCampana;
use App\Models\GastoCampana;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EliminarTenant;
use App\Services\Territorio\PresupuestoCampana;
use App\Support\TenantContext;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PresupuestoCampanaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        Storage::fake('local');
        $this->tenant = Tenant::create(['nombre' => 'Candidata Senado', 'cargo' => 'senado']);
        $this->usarTenant($this->tenant);
    }

    private function usuario(string $rol = 'Administrador', ?Tenant $tenant = null): User
    {
        $u = User::factory()->create(['tenant_id' => $tenant?->id]);
        $u->assignRole($rol);

        return $u;
    }

    public function test_cuenta_gastos_eventos_y_el_tope(): void
    {
        $admin = $this->usuario();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('abrirPresupuesto')
            ->set('tope', '1.000.000')->set('topeFuente', 'Resolución CNE de prueba')
            ->set('rubros.208', '500.000')
            ->call('guardarPresupuesto')->assertHasNoErrors()
            ->call('abrirGasto')
            ->set('gasto.concepto', 'Volantes')->set('gasto.categoria', '208')->set('gasto.valor', '600.000')
            ->set('soporte', UploadedFile::fake()->create('factura.pdf', 20, 'application/pdf'))
            ->call('guardarGasto')->assertHasNoErrors()->assertSet('modalGasto', false);

        $gasto = GastoCampana::sole();
        Storage::disk('local')->assertExists($gasto->soporte_ruta);
        $this->assertStringStartsWith("presupuesto/{$this->tenant->id}/", $gasto->soporte_ruta);

        // Evento legalizado (gasto) y otro sin legalizar (comprometido); el cancelado no cuenta
        EventoCampana::create(['titulo' => 'Foro', 'tipo' => 'foro', 'fecha' => now()->subDays(20), 'monto_asignado' => 200000, 'monto_gastado' => 150000]);
        EventoCampana::create(['titulo' => 'Caravana', 'tipo' => 'caravana', 'fecha' => now()->subDays(20), 'monto_asignado' => 100000]);
        EventoCampana::create(['titulo' => 'Cancelado', 'tipo' => 'otro', 'estado' => 'cancelado', 'fecha' => now(), 'monto_asignado' => 999999]);

        $r = app(PresupuestoCampana::class)->resumen();
        $this->assertSame(1000000.0, $r['tope']);
        $this->assertSame(750000.0, $r['gastado']);      // 600.000 + 150.000
        $this->assertSame(100000.0, $r['comprometido']);
        $this->assertSame(85.0, $r['pct_tope']);
        $this->assertSame('amarillo', $r['nivel']);
        $this->assertTrue($r['categorias']['208']['excedido']); // 600.000 de 500.000
        $this->assertSame(150000.0, $r['categorias']['204']['gastado']);
        $textos = implode(' ', array_column($r['alertas'], 1));
        $this->assertStringContainsString('85', $textos);
        $this->assertStringContainsString('sin legalizar', $textos);

        // Un gasto ligado a un evento no se cuenta dos veces
        $foro = EventoCampana::where('titulo', 'Foro')->sole();
        GastoCampana::create(['categoria' => '204', 'fecha' => now(), 'concepto' => 'Sonido del foro', 'valor' => 150000, 'evento_id' => $foro->id]);
        $this->assertSame(750000.0, app(PresupuestoCampana::class)->resumen()['gastado']);
    }

    public function test_el_csv_y_los_soportes_son_del_candidato(): void
    {
        $g = GastoCampana::create(['categoria' => '208', 'fecha' => now(), 'concepto' => '=HYPERLINK("x")', 'valor' => 1000, 'soporte_ruta' => 'presupuesto/x.pdf', 'soporte_nombre' => 'x.pdf']);
        Storage::disk('local')->put('presupuesto/x.pdf', 'pdf');
        $sesion = [TenantContext::SESSION_KEY => $this->tenant->id];

        $csv = $this->actingAs($this->usuario())->withSession($sesion)->get(route('presupuesto.exportar'))->assertOk()->streamedContent();
        $this->assertStringContainsString('208', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv); // sin fórmulas en Excel

        $this->actingAs($this->usuario())->withSession($sesion)->get(route('presupuesto.soporte', $g))->assertOk();
        $otro = Tenant::create(['nombre' => 'Otro']);
        $this->actingAs($this->usuario('Administrador', $otro))->get(route('presupuesto.soporte', $g->id))->assertNotFound();
        // El analista no ve el dinero
        $this->actingAs($this->usuario('Analista', $this->tenant))->get(route('presupuesto.index'))->assertForbidden();
    }

    public function test_eliminar_el_candidato_borra_sus_cuentas(): void
    {
        GastoCampana::create(['categoria' => '208', 'fecha' => now(), 'concepto' => 'Volantes', 'valor' => 1000, 'soporte_ruta' => "presupuesto/{$this->tenant->id}/f.pdf"]);
        Storage::disk('local')->put("presupuesto/{$this->tenant->id}/f.pdf", 'pdf');
        app(TenantContext::class)->set(null);

        app(EliminarTenant::class)->eliminar($this->tenant);

        $this->assertSame(0, GastoCampana::count());
        Storage::disk('local')->assertMissing("presupuesto/{$this->tenant->id}/f.pdf");
    }
}
