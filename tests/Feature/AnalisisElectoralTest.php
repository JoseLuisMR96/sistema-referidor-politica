<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Analisis;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\User;
use App\Services\Historico\AnalisisElectoral;
use App\Services\Historico\ImportadorHistorico;
use App\Support\Partidos;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AnalisisElectoralTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $meta;

    private Municipio $villavo;

    private Municipio $granada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->usarTenant();

        $this->meta = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        $this->villavo = Municipio::forceCreate(['departamento_id' => $this->meta->id, 'nombre' => 'VILLAVICENCIO', 'slug' => 'villavicencio', 'codigo_dane' => '50001']);
        $this->granada = Municipio::forceCreate(['departamento_id' => $this->meta->id, 'nombre' => 'GRANADA', 'slug' => 'granada', 'codigo_dane' => '50313']);
    }

    private function votos(int $anio, string $municipio, string $partido, int $votos): array
    {
        return ['anio' => (string) $anio, 'cargo' => 'concejo', 'departamento' => 'META', 'municipio' => $municipio, 'partido' => $partido, 'votos' => (string) $votos];
    }

    private function cargar(): void
    {
        app(ImportadorHistorico::class)->guardar([
            // 2019: la U se llamaba "Partido Social de Unidad Nacional"
            $this->votos(2019, 'VILLAVICENCIO', 'PARTIDO SOCIAL DE UNIDAD NACIONAL PARTIDO DE LA U', 300),
            $this->votos(2019, 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', 700),
            $this->votos(2019, 'GRANADA', 'PARTIDO DE LA U', 50),
            $this->votos(2019, 'GRANADA', 'PARTIDO LIBERAL COLOMBIANO', 50),
            // 2023
            $this->votos(2023, 'VILLAVICENCIO', 'PARTIDO DE LA U', 600),
            $this->votos(2023, 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', 400),
            $this->votos(2023, 'GRANADA', 'PARTIDO DE LA U', 80),
            $this->votos(2023, 'GRANADA', 'PARTIDO LIBERAL - PARTIDO ALIANZA VERDE', 20),
        ]);
    }

    public function test_un_partido_se_sigue_entre_anios_y_las_coaliciones_van_aparte(): void
    {
        $this->assertSame('PARTIDO DE LA U', Partidos::clave('PARTIDO SOCIAL DE UNIDAD NACIONAL PARTIDO DE LA U'));
        $this->assertSame('PARTIDO DE LA U', Partidos::clave('PARTIDO DE LA UNIÓN POR LA GENTE - PARTIDO DE LA U'));
        $this->assertSame('PARTIDO LIBERAL', Partidos::clave('PARTIDO LIBERAL COLOMBIANO'));
        $this->assertSame('ALIANZA SOCIAL INDEPENDIENTE (ASI)', Partidos::clave('PARTIDO ALIANZA SOCIAL INDEPENDIENTE "ASI"'));
        $this->assertTrue(Partidos::esCoalicion('PARTIDO LIBERAL - PARTIDO ALIANZA VERDE'));
        $this->assertTrue(Partidos::esCoalicion('CAMBIO RADICAL Y ALIANZA SOCIAL INDEPENDIENTE'));
        $this->assertNotSame('ALIANZA SOCIAL INDEPENDIENTE (ASI)', Partidos::clave('ASÍ GANAMOS TODOS'));
    }

    public function test_fortines_calcula_porcentaje_cambio_y_donde_gano(): void
    {
        $this->cargar();
        $f = app(AnalisisElectoral::class)->fortines($this->meta->id, 'concejo', 'PARTIDO DE LA U');

        $this->assertSame([2019, 2023], $f['anios']);
        $villavo = collect($f['municipios'])->firstWhere('id', $this->villavo->id);
        $this->assertSame(30.0, $villavo['por_anio'][2019]['porcentaje']);
        $this->assertSame(60.0, $villavo['por_anio'][2023]['porcentaje']);
        $this->assertSame(30.0, $villavo['cambio']);
        $this->assertFalse($villavo['por_anio'][2019]['gano']);
        $this->assertTrue($villavo['por_anio'][2023]['gano']);

        // Todo el departamento: 680 de 1.100 votos en 2023, ganó en los 2 municipios
        $this->assertSame(61.8, $f['departamento'][2023]['porcentaje']);
        $this->assertSame(2, $f['departamento'][2023]['ganados']);
    }

    public function test_municipios_calcula_peso_y_participacion(): void
    {
        $this->cargar();
        DB::table('municipios')->where('id', $this->villavo->id)->update(['potencial_electoral' => 4000, 'potencial_mujeres' => 2100, 'mesas' => 10, 'potencial_anio' => 2026]);
        DB::table('municipios')->where('id', $this->granada->id)->update(['potencial_electoral' => 1000, 'potencial_mujeres' => 500, 'mesas' => 3, 'potencial_anio' => 2026]);

        $m = app(AnalisisElectoral::class)->municipios($this->meta->id, 'concejo');

        $this->assertSame(5000, $m['total_potencial']);
        $this->assertSame('VILLAVICENCIO', $m['municipios'][0]['municipio']);
        $this->assertSame(80.0, $m['municipios'][0]['peso']);
        $this->assertSame(25.0, $m['municipios'][0]['participacion']); // 1.000 votos / 4.000
        $this->assertSame(52.5, $m['municipios'][0]['mujeres']);
    }

    public function test_carga_el_potencial_desde_el_divipol(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'divipol');
        $linea = fn ($mun, $nombre, $mujeres, $hombres, $mesas) => '52'.$mun.'0001'.str_pad('META', 12).str_pad($nombre, 30).str_pad('PUESTO', 40).'1'
            .str_pad($mujeres, 8, '0', STR_PAD_LEFT).str_pad($hombres, 8, '0', STR_PAD_LEFT).str_pad($mesas, 6, '0', STR_PAD_LEFT).'00'.str_pad('', 30)."\r\n";
        file_put_contents($ruta, $linea('001', 'VILLAVICENCIO', 600, 400, 3).$linea('001', 'VILLAVICENCIO', 60, 40, 1));

        $this->artisan('municipios:potencial', ['divipol' => $ruta, '--anio' => 2026])->assertSuccessful();

        $v = $this->villavo->fresh();
        $this->assertSame(1100, $v->potencial_electoral);
        $this->assertSame(660, $v->potencial_mujeres);
        $this->assertSame(4, $v->mesas);
    }

    public function test_la_pantalla_carga_y_exige_permiso(): void
    {
        $this->cargar();
        $analista = User::factory()->create();
        $analista->assignRole('Analista');

        Livewire::actingAs($analista)
            ->test(Analisis::class, ['departamentoId' => $this->meta->id])
            ->assertSet('cargo', 'concejo')
            ->assertSet('partido', 'PARTIDO LIBERAL') // el de más votos sumando los años
            ->assertSee('Dónde es más fuerte')
            ->call('verSeccion', 'municipios')
            ->assertSee('Dónde está la gente');

        $referidor = User::factory()->create();
        $referidor->assignRole('Referidor');
        $this->actingAs($referidor)->get('/dashboard/analisis')->assertForbidden();
    }
}
