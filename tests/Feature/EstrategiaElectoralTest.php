<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Analisis;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\User;
use App\Services\Historico\EstrategiaElectoral;
use App\Services\Historico\ImportadorHistorico;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EstrategiaElectoralTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $meta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->usarTenant();

        $this->meta = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        Municipio::forceCreate(['departamento_id' => $this->meta->id, 'nombre' => 'VILLAVICENCIO', 'slug' => 'villavicencio', 'codigo_dane' => '50001', 'potencial_electoral' => 2000]);
        Municipio::forceCreate(['departamento_id' => $this->meta->id, 'nombre' => 'GRANADA', 'slug' => 'granada', 'codigo_dane' => '50313', 'potencial_electoral' => 100]);
    }

    private function cargar(): void
    {
        $f = fn ($anio, $cargo, $mun, $partido, $candidato, $votos) => [
            'anio' => (string) $anio, 'cargo' => $cargo, 'departamento' => 'META', 'municipio' => $mun,
            'partido' => $partido, 'candidato' => $candidato, 'votos' => (string) $votos,
        ];

        app(ImportadorHistorico::class)->guardar([
            // Gobernación 2023: la U y el Liberal por separado pierden contra Fe y Firmeza
            $f(2023, 'gobernacion', 'VILLAVICENCIO', 'FE Y FIRMEZA', 'RIVAL', 500),
            $f(2023, 'gobernacion', 'VILLAVICENCIO', 'PARTIDO DE LA U', 'U', 300),
            $f(2023, 'gobernacion', 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', 'LIB', 250),
            $f(2023, 'gobernacion', 'GRANADA', 'FE Y FIRMEZA', 'RIVAL', 30),
            $f(2023, 'gobernacion', 'GRANADA', 'PARTIDO DE LA U', 'U', 60),
            // El mismo día, alcaldía con muchos menos votos en Granada
            $f(2023, 'alcaldia', 'VILLAVICENCIO', 'PARTIDO DE LA U', 'A', 1000),
            $f(2023, 'alcaldia', 'GRANADA', 'PARTIDO DE LA U', 'B', 40),
            $f(2023, 'alcaldia', 'GRANADA', 'PARTIDO LIBERAL COLOMBIANO', 'C', 20),
            // Senado (opinión) frente a concejo (estructura): el Liberal saca más en lo local
            $f(2026, 'senado', 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', null, 100),
            $f(2026, 'senado', 'VILLAVICENCIO', 'PARTIDO DE LA U', null, 900),
            $f(2023, 'concejo', 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', 'CONCEJAL UNO', 300),
            $f(2023, 'concejo', 'VILLAVICENCIO', 'PARTIDO DE LA U', 'CONCEJAL DOS', 700),
            // Asamblea: un diputado con casi todo en Villavicencio
            $f(2023, 'asamblea', 'VILLAVICENCIO', 'PARTIDO DE LA U', 'DIPUTADO', 900),
            $f(2023, 'asamblea', 'GRANADA', 'PARTIDO DE LA U', 'DIPUTADO', 100),
        ]);
    }

    public function test_reparto_por_cifra_repartidora(): void
    {
        // Total 2000, 3 curules: umbral 333. C queda fuera. Cocientes: 1000, 600, 500 → A 2, B 1.
        $this->assertSame(['A' => 2, 'B' => 1], EstrategiaElectoral::reparto(['A' => 1000, 'B' => 600, 'C' => 300], 2000, 3));
        // Juntos B y C suman 900: 1000, 900, 500 → A 2, B+C 1 (igual)
        $this->assertSame(['A' => 2, 'BC' => 1], EstrategiaElectoral::reparto(['A' => 1000, 'BC' => 900], 2000, 3));
    }

    public function test_la_alianza_gana_donde_separados_perdian(): void
    {
        $this->cargar();
        $a = app(EstrategiaElectoral::class)->alianza($this->meta->id, 'gobernacion', 2023, ['PARTIDO DE LA U', 'PARTIDO LIBERAL'], 80);

        // 300 + 250 × 0,8 = 500 + 60 de Granada = 560 frente a 530
        $this->assertSame(560, $a['resumen']['votos']);
        $this->assertTrue($a['resumen']['gana']);
        $villavo = collect($a['municipios'])->firstWhere('municipio', 'VILLAVICENCIO');
        $this->assertFalse($villavo['gana']); // empata 500 a 500: no gana
        $this->assertSame(1, $villavo['falta']);
        $this->assertSame(1, $a['ganados_antes']); // Granada ya era de la U
    }

    public function test_opinion_frente_a_estructura(): void
    {
        $this->cargar();
        $e = app(EstrategiaElectoral::class);

        $this->assertArrayHasKey('PARTIDO LIBERAL', $e->partidosMaquinaria($this->meta->id));
        $m = $e->maquinaria($this->meta->id, 'PARTIDO LIBERAL');
        $this->assertSame(10.0, $m['departamento']['opinion']);
        $this->assertSame(30.0, $m['departamento']['por_cargo']['concejo']);
        $this->assertSame('maquinaria', $m['departamento']['tipo']);
        $this->assertSame('opinion', EstrategiaElectoral::tipo(20.0, 5.0));
        $this->assertSame('equilibrado', EstrategiaElectoral::tipo(10.0, 12.0));

        $l = $e->lideres($this->meta->id, 'asamblea');
        $this->assertSame('DIPUTADO', $l['candidatos'][0]['candidato']);
        $this->assertSame('VILLAVICENCIO', $l['candidatos'][0]['fuerte']);
        $this->assertSame(90.0, $l['candidatos'][0]['concentracion']);
        $this->assertSame(1, $l['candidatos'][0]['para80']);
    }

    public function test_testigos_marca_lo_atipico(): void
    {
        $this->cargar();
        $t = app(EstrategiaElectoral::class)->testigos($this->meta->id, 'alcaldia');

        $this->assertSame(['gobernacion', 'asamblea', 'concejo'], array_values(array_intersect(['gobernacion', 'asamblea', 'concejo'], $t['mismo_dia'])));
        $villavo = collect($t['municipios'])->firstWhere('municipio', 'VILLAVICENCIO');
        // Un solo candidato con 100 %: no cuenta como unanimidad (no hubo competencia que medir)
        $this->assertArrayNotHasKey('unanimidad', $villavo['senales']);
        // Votó el 50 % del potencial: sin señal de participación
        $this->assertArrayNotHasKey('participacion', $villavo['senales']);
    }

    public function test_las_secciones_de_estrategia_cargan(): void
    {
        $this->cargar();
        $analista = User::factory()->create();
        $analista->assignRole('Analista');

        Livewire::actingAs($analista)
            ->test(Analisis::class, ['departamentoId' => $this->meta->id, 'cargo' => 'gobernacion', 'seccion' => 'alianzas'])
            ->assertSee('Marca al menos un partido')
            ->call('alternarAliado', 'PARTIDO DE LA U')
            ->call('alternarAliado', 'PARTIDO LIBERAL')
            ->assertSet('aliados', ['PARTIDO DE LA U', 'PARTIDO LIBERAL'])
            ->call('principal', 'PARTIDO LIBERAL')
            ->assertSet('aliados', ['PARTIDO LIBERAL', 'PARTIDO DE LA U'])
            ->assertSee('Dónde gana la alianza')
            ->call('verSeccion', 'maquinaria')
            ->assertSee('Qué significa')
            ->assertSee('Líderes')
            ->call('verSeccion', 'testigos')
            ->assertSee('no pruebas de fraude', false);
    }
}
