<?php

namespace Tests\Feature;

use App\Livewire\Admin\Ideologias\Index as Ideologias;
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

    public function test_un_partido_que_no_fue_en_la_ultima_eleccion_usa_su_ultima(): void
    {
        $this->cargar();
        app(ImportadorHistorico::class)->guardar([
            $this->votos(2015, 'VILLAVICENCIO', 'POLO DEMOCRATICO ALTERNATIVO', 90),
            $this->votos(2015, 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', 10),
        ]);

        $f = app(AnalisisElectoral::class)->fortines($this->meta->id, 'concejo', 'POLO DEMOCRÁTICO');

        $this->assertSame(2015, $f['ultimo']);
        $this->assertSame(2023, $f['ultimo_depto']);
        $villavo = collect($f['municipios'])->firstWhere('id', $this->villavo->id);
        $this->assertSame(90.0, $villavo['por_anio'][2015]['porcentaje']);
        $this->assertFalse($villavo['por_anio'][2023]['participo']);
        $this->assertSame('LIGA DE GOBERNANTES ANTICORRUPCIÓN', Partidos::clave('PARTIDO LIGA GOBERNANTES ANTICORRUPCIÓN - LIGA'));
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

    public function test_tendencia_reparte_bloques_y_detecta_giros(): void
    {
        $this->cargar();
        // La migración ya clasifica Liberal (centro-izquierda) y la U (centro-derecha);
        // la coalición Liberal-Verde empieza sin clasificar.
        $t = app(AnalisisElectoral::class)->tendencia($this->meta->id, 'concejo');

        $villavo = collect($t['municipios'])->firstWhere('id', $this->villavo->id);
        $this->assertSame('PARTIDO LIBERAL', $villavo['por_anio'][2019]['ganador']);
        $this->assertSame('izquierda', $villavo['por_anio'][2019]['bloque']);
        $this->assertSame('derecha', $villavo['por_anio'][2023]['bloque']);
        $this->assertTrue($villavo['giro']);
        $this->assertSame(70.0, $villavo['por_anio'][2019]['porcentaje']['izquierda']);
        // Índice 2023: (600·1 + 400·−1) / 1.000 = 0,2; en 2019: (300 − 700) / 1.000 = −0,4
        $this->assertSame(0.6, $villavo['cambio_indice']);

        // La coalición Liberal - Alianza Verde se infiere centro-izquierda (mismo bloque)
        $granada = collect($t['municipios'])->firstWhere('id', $this->granada->id);
        $this->assertSame(20.0, $granada['por_anio'][2023]['porcentaje']['izquierda']);
        $this->assertSame(['LIBERAL PARTIDO ALIANZA VERDE' => 'centro_izquierda'], $t['inferidas']);
        $this->assertSame([], $t['sin_clasificar']);
        $this->assertSame(2, $t['departamento'][2023]['ganados']['derecha']);
    }

    public function test_coalicion_mixta_no_se_infiere_y_el_blanco_de_2023_es_blanco(): void
    {
        $ideologias = ['PARTIDO LIBERAL' => 'centro_izquierda', 'PARTIDO CONSERVADOR' => 'derecha', 'CENTRO DEMOCRÁTICO' => 'derecha'];

        $this->assertNull(AnalisisElectoral::inferir('PARTIDO LIBERAL - PARTIDO CONSERVADOR', $ideologias));
        $this->assertSame('derecha', AnalisisElectoral::inferir('COALICIÓN CENTRO DEMOCRÁTICO Y PARTIDO CONSERVADOR', $ideologias));
        $this->assertNull(AnalisisElectoral::inferir('FE Y FIRMEZA', $ideologias));
        $this->assertSame('VOTO EN BLANCO', Partidos::clave('CANDIDATOS TOTALES'));
    }

    public function test_solo_un_usuario_global_clasifica_partidos(): void
    {
        $this->cargar();
        $admin = User::factory()->create(['tenant_id' => null]);
        $admin->assignRole('Administrador');

        // La sección de tendencia ya no edita: enlaza a la pantalla de ideología
        Livewire::actingAs($admin)
            ->test(Analisis::class, ['departamentoId' => $this->meta->id, 'seccion' => 'tendencia'])
            ->assertSee('Clasificar partidos');

        Livewire::actingAs($admin)
            ->test(Ideologias::class, ['departamentoId' => $this->meta->id])
            ->set('cargo', 'concejo')
            ->set('estado', 'inferidos') // Liberal + Verde: los dos de centro-izquierda
            ->assertSee('PARTIDO LIBERAL - PARTIDO ALIANZA VERDE')
            ->call('abrir', 'LIBERAL PARTIDO ALIANZA VERDE')
            ->assertSet('modal', true)
            ->set('ideologia', 'centro')
            ->set('nota', 'Coalición local')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('modal', false)
            ->call('quitar', 'MIRA');

        $this->assertDatabaseHas('partidos_ideologia', ['clave' => 'LIBERAL PARTIDO ALIANZA VERDE', 'ideologia' => 'centro', 'nota' => 'Coalición local']);
        $this->assertDatabaseMissing('partidos_ideologia', ['clave' => 'MIRA']);

        // Ideología inválida
        Livewire::actingAs($admin)
            ->test(Ideologias::class, ['departamentoId' => $this->meta->id])
            ->call('abrir', 'PARTIDO DE LA U')
            ->set('ideologia', 'extrema')
            ->call('guardar')
            ->assertHasErrors(['ideologia']);

        // Un analista consulta, pero no clasifica
        $analista = User::factory()->create(['tenant_id' => null]);
        $analista->assignRole('Analista');
        Livewire::actingAs($analista)
            ->test(Ideologias::class, ['departamentoId' => $this->meta->id])
            ->assertSee('Solo un usuario global')
            ->call('abrir', 'PARTIDO LIBERAL')
            ->assertForbidden();
    }

    private function cargarAlcaldes(): void
    {
        $fila = fn ($anio, $cargo, $mun, $partido, $candidato, $votos) => [
            'anio' => (string) $anio, 'cargo' => $cargo, 'departamento' => 'META', 'municipio' => $mun,
            'partido' => $partido, 'candidato' => $candidato, 'votos' => (string) $votos,
        ];

        app(ImportadorHistorico::class)->guardar([
            // Alcaldes elegidos en 2019
            $fila(2019, 'alcaldia', 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', 'ALCALDE UNO', 500),
            $fila(2019, 'alcaldia', 'VILLAVICENCIO', 'PARTIDO CONSERVADOR COLOMBIANO', 'OTRO', 300),
            $fila(2019, 'alcaldia', 'GRANADA', 'GRANADA UNIDA', 'ALCALDE DOS', 70),
            $fila(2019, 'alcaldia', 'GRANADA', 'PARTIDO LIBERAL COLOMBIANO', 'TERCERO', 30),
            // Gobernación 2023
            $fila(2023, 'gobernacion', 'VILLAVICENCIO', 'PARTIDO LIBERAL COLOMBIANO', 'ANA', 600),
            $fila(2023, 'gobernacion', 'VILLAVICENCIO', 'FE Y FIRMEZA', 'BETO', 400),
            $fila(2023, 'gobernacion', 'GRANADA', 'PARTIDO LIBERAL COLOMBIANO', 'ANA', 20),
            $fila(2023, 'gobernacion', 'GRANADA', 'FE Y FIRMEZA', 'BETO', 80),
        ]);
    }

    public function test_alcaldes_cruza_el_alcalde_con_la_gobernacion(): void
    {
        $this->cargarAlcaldes();
        $an = app(AnalisisElectoral::class);

        $this->assertSame([2023], $an->aniosAlcaldes($this->meta->id));
        $r = $an->alcaldes($this->meta->id, 2023);

        // Ana: 620 de 1.100 = 56,4 % en el departamento
        $ana = $r['candidatos']['PARTIDO LIBERAL|ANA'];
        $this->assertSame(56.4, $ana['porcentaje']);
        $this->assertSame(1, $ana['con_aliado']);          // Villavicencio, alcalde liberal
        $this->assertSame(3.6, $ana['sobre_con_aliado']);   // 60 % − 56,4 %
        $this->assertSame(-36.4, $ana['sobre_sin_aliado']); // Granada: 20 % − 56,4 %

        $villavo = collect($r['municipios'])->firstWhere('id', $this->villavo->id);
        $this->assertSame('ALCALDE UNO', $villavo['alcalde']['candidato']);
        $this->assertSame('PARTIDO LIBERAL|ANA', $villavo['favorito']);
        $this->assertTrue($villavo['candidatos']['PARTIDO LIBERAL|ANA']['aliado']);

        $granada = collect($r['municipios'])->firstWhere('id', $this->granada->id);
        $this->assertSame('FE Y FIRMEZA|BETO', $granada['favorito']);
        $this->assertFalse($granada['candidatos']['FE Y FIRMEZA|BETO']['aliado']);

        $actuales = $an->alcaldesActuales($this->meta->id);
        $this->assertSame(2019, $actuales['anio']);
        $this->assertSame(['ALCALDE UNO', 'ALCALDE DOS'], collect($actuales['municipios'])->sortBy('municipio')->reverse()->pluck('candidato')->values()->all());
    }

    public function test_aliados_por_partido_o_coalicion(): void
    {
        $this->assertTrue(AnalisisElectoral::aliados('PARTIDO LIBERAL COLOMBIANO', 'PARTIDO LIBERAL - PARTIDO ALIANZA VERDE'));
        $this->assertTrue(AnalisisElectoral::aliados('UNIDOS PODEMOS - CAMBIO RADICAL', 'PARTIDO CAMBIO RADICAL'));
        $this->assertFalse(AnalisisElectoral::aliados('GRANADA UNIDA', 'FE Y FIRMEZA'));
        $this->assertFalse(AnalisisElectoral::aliados('PARTIDO LIBERAL COLOMBIANO', 'PARTIDO CONSERVADOR COLOMBIANO'));
    }

    public function test_la_seccion_de_alcaldes_carga(): void
    {
        $this->cargarAlcaldes();
        $analista = User::factory()->create();
        $analista->assignRole('Analista');

        Livewire::actingAs($analista)
            ->test(Analisis::class, ['departamentoId' => $this->meta->id, 'seccion' => 'alcaldes'])
            ->assertSet('anio', 2023)
            ->assertSet('gobernador', 'PARTIDO LIBERAL|ANA')
            ->assertSee('coincidencia estadística')
            ->assertSee('ALCALDE UNO')
            ->assertSee('Alcaldes en funciones para 2023'); // elegidos en 2019
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
