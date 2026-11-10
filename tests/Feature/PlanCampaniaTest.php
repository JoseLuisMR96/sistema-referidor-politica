<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Analisis;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\User;
use App\Services\Historico\ImportadorHistorico;
use App\Services\Historico\PlanCampania;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanCampaniaTest extends TestCase
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
        $this->villavo = Municipio::forceCreate(['departamento_id' => $this->meta->id, 'nombre' => 'VILLAVICENCIO', 'slug' => 'villavicencio', 'codigo_dane' => '50001', 'potencial_electoral' => 2000]);
        $this->granada = Municipio::forceCreate(['departamento_id' => $this->meta->id, 'nombre' => 'GRANADA', 'slug' => 'granada', 'codigo_dane' => '50313', 'potencial_electoral' => 200]);
    }

    private function cargar(): void
    {
        $fila = fn ($anio, $cargo, $mun, $partido, $candidato, $votos) => [
            'anio' => (string) $anio, 'cargo' => $cargo, 'departamento' => 'META', 'municipio' => $mun,
            'partido' => $partido, 'candidato' => $candidato, 'votos' => (string) $votos,
        ];

        app(ImportadorHistorico::class)->guardar([
            // Gobernación: solo 2023, así que no se proyecta crecimiento
            $fila(2023, 'gobernacion', 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', 'ANA', 600),
            $fila(2023, 'gobernacion', 'VILLAVICENCIO', 'PARTIDO DE LA U', 'BETO', 300),
            $fila(2023, 'gobernacion', 'VILLAVICENCIO', 'VOTO EN BLANCO', null, 100),
            $fila(2023, 'gobernacion', 'GRANADA', 'PARTIDO LIBERAL COLOMBIANO', 'ANA', 20),
            $fila(2023, 'gobernacion', 'GRANADA', 'PARTIDO DE LA U', 'BETO', 80),
            // Concejo de Villavicencio: el ganador pasa de liberal (2019) a la U (2023)
            $fila(2019, 'concejo', 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', null, 700),
            $fila(2019, 'concejo', 'VILLAVICENCIO', 'PARTIDO DE LA U', null, 300),
            $fila(2023, 'concejo', 'VILLAVICENCIO', 'PARTIDO DE LA U', null, 600),
            $fila(2023, 'concejo', 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', null, 400),
        ]);
    }

    private function registro(Municipio $votaEn): void
    {
        static $doc = 7000;

        PublicRegistration::create([
            'full_name' => 'Persona', 'document_type' => 'CC', 'document_number' => (string) $doc++,
            'gender' => 'F', 'residence_municipality' => 'X', 'voting_municipality' => 'X',
            'voting_municipality_id' => $votaEn->id,
        ]);
    }

    public function test_cifra_repartidora_con_umbral(): void
    {
        $votos = ['A' => 1000, 'B' => 600, 'C' => 300, 'D' => 50, 'VOTO EN BLANCO' => 50];
        $r = PlanCampania::metaCircunscripcion($votos, null, true, 3);

        // Umbral = 2000 / 3 × 50 % = 333: C y D quedan fuera. Cocientes: 1000, 600, 500.
        $this->assertSame(334, $r['umbral']);
        $this->assertSame(500, $r['cifra']);
        $this->assertSame(2, $r['con_curul']);
        $this->assertSame(500, $r['meta']);

        // Uninominal: los votos del ganador, proyectados con el crecimiento (1600 → 2000 = +25 %)
        $u = PlanCampania::metaCircunscripcion($votos, 1600, false, null);
        $this->assertSame('A', $u['ganador']);
        $this->assertSame(25.0, $u['crecimiento']);
        $this->assertSame(1250, $u['meta']);
    }

    public function test_meta_se_reparte_por_municipio_y_mide_el_avance(): void
    {
        $this->cargar();
        $this->registro($this->granada);
        $this->registro($this->granada);

        $plan = app(PlanCampania::class);
        $m = $plan->meta($this->meta->id, 'gobernacion', [$this->granada->id => 2]);

        $this->assertSame(620, $m['meta']); // ANA: 600 + 20
        $granada = collect($m['municipios'])->firstWhere('id', $this->granada->id);
        $this->assertSame(56, $granada['meta']); // 620 × 100 / 1100
        $this->assertSame(3.6, $granada['avance']);
        $this->assertSame(54, $granada['falta']);

        // Meta a mano
        $this->assertSame(1100, $plan->meta($this->meta->id, 'gobernacion', null, 1100)['meta']);
        // Senado no tiene meta por departamento
        $this->assertFalse($plan->meta($this->meta->id, 'senado')['aplica']);
    }

    public function test_conquistar_y_disputa(): void
    {
        $this->cargar();
        $plan = app(PlanCampania::class);

        $c = $plan->conquistar($this->meta->id, 'gobernacion');
        $villavo = collect($c['municipios'])->firstWhere('id', $this->villavo->id);
        $this->assertSame(1000, $villavo['no_votaron']); // 2000 − 1000
        $this->assertSame(100, $villavo['blanco']);
        $this->assertSame(1100, $villavo['conquistables']);
        $this->assertSame(50.0, $villavo['participacion']);

        $d = $plan->disputa($this->meta->id, 'concejo', ['PARTIDO DE LA U' => 'centro_derecha', 'PARTIDO LIBERAL' => 'centro_izquierda']);
        $v = $d['municipios'][0];
        $this->assertSame('VILLAVICENCIO', $v['municipio']);
        $this->assertSame(20.0, $v['margen']);
        $this->assertSame(1.0, $v['cambio']); // de izquierda a derecha
        $this->assertSame(30.0, $v['volatilidad']);
        // 60 × (1 − 20/30) + 25 + 15 × 30/50 = 54
        $this->assertSame(54, $v['puntaje']);
    }

    public function test_prioridades_ordena_y_sugiere_accion(): void
    {
        $this->cargar();
        $p = app(PlanCampania::class)->prioridades($this->meta->id, 'gobernacion', 'derecha', 'equilibrado', [], null, null,
            ['PARTIDO DE LA U' => 'centro_derecha', 'PARTIDO LIBERAL' => 'centro_izquierda']);

        $this->assertCount(2, $p['municipios']);
        $this->assertSame([1, 2], array_column($p['municipios'], 'puesto'));
        $this->assertSame('VILLAVICENCIO', $p['municipios'][0]['municipio']);
        foreach ($p['municipios'] as $m) {
            $this->assertArrayHasKey($m['accion'], PlanCampania::ACCIONES);
        }
        // Granada: la U sacó 80 % contra 38 % en el departamento → zona afín
        $this->assertGreaterThan(0, collect($p['municipios'])->firstWhere('municipio', 'GRANADA')['afinidad']);
    }

    public function test_cada_seccion_se_puede_ver_por_anio(): void
    {
        $this->cargar();
        $plan = app(PlanCampania::class);

        // Concejo 2019 como "última": no hay elección anterior con qué comparar
        $c = $plan->conquistar($this->meta->id, 'concejo', 2019);
        $this->assertSame(2019, $c['ultimo']);
        $this->assertNull($c['previo']);
        $d = $plan->disputa($this->meta->id, 'concejo', null, 2019);
        $this->assertSame(2019, $d['ultimo']);
        // Sin año, la última de verdad
        $this->assertSame(2023, $plan->conquistar($this->meta->id, 'concejo')['ultimo']);

        $analista = User::factory()->create();
        $analista->assignRole('Analista');
        $c = Livewire::actingAs($analista)
            ->test(Analisis::class, ['departamentoId' => $this->meta->id, 'cargo' => 'concejo', 'seccion' => 'fortines'])
            ->assertSet('anio', 2023)
            ->assertSee('(la última)')
            ->set('anio', 2019)
            ->assertSee('Viendo 2019 como si fuera la última elección');

        foreach (Analisis::CON_ANIO as $seccion) {
            $c->call('verSeccion', $seccion)->assertOk()->assertSet('anio', 2019);
        }

        // Un año que el cargo no tiene vuelve al último
        $c->call('verSeccion', 'fortines')->set('anio', 1990)->assertSet('anio', 2023);
    }

    public function test_las_secciones_del_plan_cargan(): void
    {
        $this->cargar();
        $this->registro($this->villavo);
        $analista = User::factory()->create();
        $analista->assignRole('Analista');

        Livewire::actingAs($analista)
            ->test(Analisis::class, ['departamentoId' => $this->meta->id, 'cargo' => 'gobernacion', 'seccion' => 'meta'])
            ->assertSee('Meta proyectada')
            ->assertSee('Avance por municipio')
            ->set('metaManual', 1000)
            ->assertSee('fijada a mano')
            ->call('verSeccion', 'conquistar')
            ->assertSee('Por conquistar')
            ->call('verSeccion', 'disputa')
            ->assertSee('Cómo se mide')
            ->call('verSeccion', 'prioridades')
            ->assertSee('Dónde ir primero');
    }
}
