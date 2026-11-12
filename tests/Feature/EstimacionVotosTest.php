<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Stats;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\User;
use App\Services\EstimacionVotos;
use App\Services\Historico\ImportadorHistorico;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EstimacionVotosTest extends TestCase
{
    use RefreshDatabase;

    public function test_descuenta_los_registros_por_participacion_y_deja_fuera_lo_que_no_cuenta(): void
    {
        $this->seed(RolesYPermisosSeeder::class);
        $tenant = $this->usarTenant();

        $meta = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        $huila = Departamento::create(['nombre' => 'HUILA', 'slug' => 'huila']);
        $mun = fn ($d, $n, $dane, $pot) => Municipio::forceCreate([
            'departamento_id' => $d->id, 'nombre' => $n, 'slug' => strtolower($n), 'codigo_dane' => $dane, 'potencial_electoral' => $pot,
        ]);
        $villavo = $mun($meta, 'VILLAVICENCIO', '50001', 2000);
        $granada = $mun($meta, 'GRANADA', '50313', 200);
        $acacias = $mun($meta, 'ACACIAS', '50006', 500); // sin histórico
        $neiva = $mun($huila, 'NEIVA', '41001', 1000);

        $tenant->update(['cargo' => 'gobernacion', 'departamento_id' => $meta->id]);

        // Participación 2023: Villavicencio 1000/2000 = 50 %; Granada 60/200 = 30 %
        $fila = fn ($m, $p, $c, $v) => ['anio' => '2023', 'cargo' => 'gobernacion', 'departamento' => 'META',
            'municipio' => $m, 'partido' => $p, 'candidato' => $c, 'votos' => (string) $v];
        app(ImportadorHistorico::class)->guardar([
            $fila('VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', 'ANA', 600),
            $fila('VILLAVICENCIO', 'PARTIDO DE LA U', 'BETO', 400),
            $fila('GRANADA', 'PARTIDO LIBERAL COLOMBIANO', 'ANA', 20),
            $fila('GRANADA', 'PARTIDO DE LA U', 'BETO', 40),
        ]);

        $doc = 1000;
        $registrar = function (?Municipio $m, int $n, string $origen = 'formulario') use (&$doc) {
            for ($i = 0; $i < $n; $i++) {
                PublicRegistration::create([
                    'full_name' => 'Persona', 'document_type' => 'CC', 'document_number' => (string) $doc++,
                    'gender' => 'F', 'residence_municipality' => 'X', 'voting_municipality' => 'X',
                    'voting_municipality_id' => $m?->id, 'origen' => $origen,
                ]);
            }
        };
        $registrar($villavo, 10);
        $registrar($granada, 10);
        $registrar($acacias, 4);
        $registrar($villavo, 2, 'importado'); // no cuentan
        $registrar($neiva, 3);                // fuera de la circunscripción
        $registrar(null, 1);                  // sin municipio

        $e = app(EstimacionVotos::class)->calcular($tenant->fresh());

        $this->assertSame(24, $e['registros']);
        $this->assertSame(24, $e['contables']);
        // Acacías usa el promedio del Meta: 1060 / 2200 = 48,2 %
        // 10 × 50 % + 10 × 30 % + 4 × 48,2 % = 9,93
        $this->assertSame(9.9, $e['votantes']);
        $this->assertSame(41.4, $e['participacion']);
        $this->assertSame(3, $e['fuera']);
        $this->assertSame(1, $e['sin_municipio']);
        $this->assertSame(2, $e['importados']);
        $this->assertSame(2023, $e['anio']);
        $this->assertNotNull($e['meta']);
        $this->assertSame('departamento', collect($e['municipios'])->firstWhere('municipio', 'ACACIAS')['fuente']);

        // El dashboard la muestra
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('Administrador');
        Livewire::actingAs($admin)->test(Stats::class)
            ->assertSee('Votos probables de los registros')
            ->assertSet('estimacion.contables', 24);
    }

    public function test_sin_cargo_no_hay_estimacion(): void
    {
        $tenant = $this->usarTenant();
        $tenant->update(['cargo' => null]);

        $this->assertNull(app(EstimacionVotos::class)->calcular($tenant->fresh()));
    }
}
