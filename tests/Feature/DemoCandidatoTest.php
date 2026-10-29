<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\Referrer;
use App\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\DemoCandidatoSeeder;
use Database\Seeders\HenryPalmaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoCandidatoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $henry;

    protected function setUp(): void
    {
        parent::setUp();

        $meta = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        Municipio::forceCreate(['departamento_id' => $meta->id, 'nombre' => 'VILLAVICENCIO', 'slug' => 'villavicencio', 'codigo_dane' => '50001']);
        Municipio::forceCreate(['departamento_id' => $meta->id, 'nombre' => 'ACACÍAS', 'slug' => 'acacias', 'codigo_dane' => '50006']);

        $this->seed(HenryPalmaSeeder::class);
        $this->henry = Tenant::where('slug', 'henry-palma')->sole();
    }

    private function contar(string $tabla, ?Tenant $tenant = null): int
    {
        return DB::table($tabla)->where('tenant_id', ($tenant ?? $this->henry)->id)->count();
    }

    public function test_crea_la_demo_sin_telefonos_y_sin_duplicar(): void
    {
        $this->seed(DemoCandidatoSeeder::class);
        $this->seed(DemoCandidatoSeeder::class);

        $this->assertSame(12, $this->contar('referrers'));
        $this->assertSame(640, $this->contar('public_registrations'));
        $this->assertSame(15, $this->contar('referidores_pregoneros'));
        $this->assertGreaterThan(0, $this->contar('referidos_pregoneros'));

        // Nada de contacto real y todo marcado como demo
        $this->assertSame(0, DB::table('public_registrations')->where('tenant_id', $this->henry->id)->whereNotNull('phone')->count());
        $this->assertSame(0, DB::table('referidores_pregoneros')->where('tenant_id', $this->henry->id)->whereNotNull('celular')->count());
        $this->assertSame(640, DB::table('public_registrations')->where('tenant_id', $this->henry->id)->where('origen', 'demo')->count());
    }

    public function test_demo_borrar_solo_quita_lo_de_demostracion(): void
    {
        $this->seed(DemoCandidatoSeeder::class);

        // Un dato real del mismo candidato y otro de otro candidato
        app(TenantContext::class)->con($this->henry->id, fn () => Referrer::create(['name' => 'Real', 'code' => 'REAL0001']));
        $otro = Tenant::where('slug', 'tenant-inicial')->sole();
        app(TenantContext::class)->con($otro->id, fn () => Referrer::create(['name' => 'Otro', 'code' => 'OTRO0001']));

        $this->artisan('demo:borrar', ['slug' => 'henry-palma', '--force' => true])->assertSuccessful();

        $this->assertSame(1, $this->contar('referrers'));
        $this->assertSame(0, $this->contar('public_registrations'));
        $this->assertSame(0, $this->contar('referidores_pregoneros'));
        $this->assertSame(1, $this->contar('referrers', $otro));
    }
}
