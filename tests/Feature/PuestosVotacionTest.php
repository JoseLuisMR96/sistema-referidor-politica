<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Analisis;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\User;
use App\Services\Historico\AnalisisPuestos;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PuestosVotacionTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $meta;

    private Municipio $villavo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->usarTenant();

        $this->meta = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        $this->villavo = Municipio::forceCreate(['departamento_id' => $this->meta->id, 'nombre' => 'VILLAVICENCIO', 'slug' => 'villavicencio', 'codigo_dane' => '50001']);
        Departamento::create(['nombre' => 'CASANARE', 'slug' => 'casanare']);
    }

    /** CSV con el formato del "mesa a mesa" territorial de la Registraduría. */
    private function archivo(int $anio, array $filas): string
    {
        $lineas = ['Código Departamento,Nombre Departamento,Código Municipio,Nombre Municipio,Código Zona,Código Puesto,Nombre Puesto,Mesa,Código Comuna,Nombre Comuna,Código Corporación,Nombre Corporación,Código Circunscripción,Código Partido,Nombre Partido,Código Candidato,Nombre Candidato,Total Votos'];
        foreach ($filas as [$puesto, $nombre, $mesa, $partido, $cand, $votos, $dep]) {
            $lineas[] = sprintf('"52","%s","001","VILLAVICENCIO","01","%s","%s","%d","01","01COMUNA 1","001","GOBERNADOR","1","00001","%s","%s","X",%d',
                $dep ?? 'META', $puesto, $nombre, $mesa, $partido, $cand, $votos);
        }
        $ruta = tempnam(sys_get_temp_dir(), 'mmv').'.csv';
        file_put_contents($ruta, "\xEF\xBB\xBF".implode("\n", $lineas)."\n");

        return $ruta;
    }

    private function cargar(): void
    {
        $ruta = $this->archivo(2023, [
            // Puesto 01: dos mesas, se suman
            ['01', 'COLEGIO A', 1, 'PARTIDO DE LA U', '00001', 60, null],
            ['01', 'COLEGIO A', 2, 'PARTIDO DE LA U', '00001', 40, null],
            ['01', 'COLEGIO A', 1, 'PARTIDO LIBERAL COLOMBIANO', '00002', 100, null],
            ['01', 'COLEGIO A', 1, 'N/A', '00997', 5, null],
            // Puesto 02: casi todo para la U y muchos nulos
            ['02', 'ESCUELA B', 1, 'PARTIDO DE LA U', '00001', 180, null],
            ['02', 'ESCUELA B', 1, 'PARTIDO LIBERAL COLOMBIANO', '00002', 10, null],
            ['02', 'ESCUELA B', 1, 'N/A', '00996', 10, null],
            ['02', 'ESCUELA B', 1, 'N/A', '00997', 60, null],
            // Puesto 03: grande y parejo, casi sin nulos
            ['03', 'COLISEO C', 1, 'PARTIDO DE LA U', '00001', 300, null],
            ['03', 'COLISEO C', 1, 'PARTIDO LIBERAL COLOMBIANO', '00002', 300, null],
            ['03', 'COLISEO C', 1, 'N/A', '00997', 2, null],
            // Otro departamento: se filtra con --depto
            ['01', 'OTRO', 1, 'PARTIDO DE LA U', '00001', 999, 'CASANARE'],
        ]);

        $this->artisan('historico:importar-puestos', ['archivo' => $ruta, '--anio' => 2023, '--depto' => ['META']])->assertSuccessful();
        @unlink($ruta);
    }

    public function test_importa_sumando_mesas_por_puesto(): void
    {
        $this->cargar();

        $this->assertSame(3, DB::table('puestos_votacion')->count());
        $this->assertSame('COMUNA 1', DB::table('puestos_votacion')->where('codigo', '01')->value('comuna'));
        $this->assertSame(100, (int) DB::table('historico_puestos')->where('partido', 'PARTIDO DE LA U')
            ->where('puesto_id', DB::table('puestos_votacion')->where('codigo', '01')->value('id'))->value('votos'));
        $this->assertSame(67, (int) DB::table('historico_puestos')->where('partido', 'VOTOS NULOS')->sum('votos'));
        // Reimportar reemplaza, no duplica
        $this->cargar();
        $this->assertSame(10, DB::table('historico_puestos')->count());
    }

    public function test_analiza_los_puestos_del_municipio(): void
    {
        $this->cargar();
        $p = app(AnalisisPuestos::class);

        $this->assertArrayHasKey($this->villavo->id, $p->municipios($this->meta->id));
        $this->assertSame(['gobernacion' => [2023]], $p->cargos($this->villavo->id));

        $r = $p->puestos($this->villavo->id, 'gobernacion', 2023, 'PARTIDO LIBERAL');
        // Liberal: 410 de 1000 válidos (990 de partidos + 10 en blanco)
        $this->assertSame(41.0, $r['municipio']['pct_partido']);
        $a = collect($r['puestos'])->firstWhere('codigo', '01');
        $b = collect($r['puestos'])->firstWhere('codigo', '02');
        $this->assertSame(50.0, $a['pct_partido']);
        $this->assertSame(0, $a['brecha']);
        // En B tiene 5 %: para llegar al 41 % le faltan 72 votos de 200 válidos
        $this->assertSame(72, $b['brecha']);
        $this->assertArrayHasKey('nulos', $b['senales']);   // 23 % frente a 6 % del municipio
        $this->assertArrayHasKey('unanimidad', $b['senales']); // la U con 90 %
        $this->assertSame([], $a['senales']);
    }

    public function test_exporta_y_carga_en_otro_servidor(): void
    {
        $this->cargar();
        DB::table('puestos_votacion')->update(['potencial' => 500, 'potencial_anio' => 2026]);
        $archivo = tempnam(sys_get_temp_dir(), 'pu').'.jsonl.gz';

        $this->artisan('historico:exportar-puestos', ['--depto' => ['META'], '--archivo' => $archivo])->assertSuccessful();

        // "Otro servidor": las tablas vacías
        DB::table('historico_puestos')->delete();
        DB::table('puestos_votacion')->delete();

        $this->artisan('historico:cargar-puestos', ['archivos' => [$archivo]])->assertSuccessful();
        @unlink($archivo);

        $this->assertSame(3, DB::table('puestos_votacion')->count());
        $this->assertSame(10, DB::table('historico_puestos')->count());
        $this->assertSame(1500, (int) DB::table('puestos_votacion')->sum('potencial'));
        $this->assertSame('COMUNA 1', DB::table('puestos_votacion')->where('codigo', '01')->value('comuna'));
    }

    public function test_testigos_usa_nulos_y_reparte_por_mesa(): void
    {
        $this->assertSame(['mediana' => 2.0, 'escala' => 1.4826], \App\Services\Historico\EstrategiaElectoral::robusta([1, 2, 3]));
        $this->assertSame(['mediana' => null, 'escala' => null], \App\Services\Historico\EstrategiaElectoral::robusta([1, 2]));

        $this->cargar();
        // El histórico por municipio, para que haya elección que ordenar
        app(\App\Services\Historico\ImportadorHistorico::class)->guardar([
            ['anio' => '2023', 'cargo' => 'gobernacion', 'departamento' => 'META', 'municipio' => 'VILLAVICENCIO', 'partido' => 'PARTIDO DE LA U', 'candidato' => 'X', 'votos' => '580'],
        ]);
        $this->villavo->forceFill(['mesas' => 7])->save();

        $t = app(\App\Services\Historico\EstrategiaElectoral::class)->testigos($this->meta->id, 'gobernacion', 5);
        $this->assertTrue($t['hay_nulos']);
        $v = $t['municipios'][0];
        $this->assertSame(6.3, $v['nulos_pct']); // 67 de 1067 tarjetas
        $this->assertSame(5, $v['testigos']);    // 7 mesas, 5 testigos
        // ESCUELA B sale entre los puestos para vigilar
        $this->assertContains('ESCUELA B', array_column($t['puestos'], 'nombre'));
    }

    public function test_la_seccion_de_puestos_carga(): void
    {
        $analista = User::factory()->create();
        $analista->assignRole('Analista');

        Livewire::actingAs($analista)
            ->test(Analisis::class, ['departamentoId' => $this->meta->id, 'seccion' => 'puestos'])
            ->assertSee('No hay resultados por puesto');

        $this->cargar();

        Livewire::actingAs($analista)
            ->test(Analisis::class, ['departamentoId' => $this->meta->id, 'seccion' => 'puestos'])
            ->assertSet('municipioId', $this->villavo->id)
            ->assertSet('cargo', 'gobernacion')
            ->assertSee('ESCUELA B')
            ->assertSee('Dónde crecer')
            ->set('ordenPuestos', 'senales')
            ->assertSee('Nulos');
    }
}
