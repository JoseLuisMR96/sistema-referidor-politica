<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Historico;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Historico\HistoricoElectoralStats;
use App\Services\Historico\ImportadorHistorico;
use App\Support\CatalogoDane;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class HistoricoElectoralTest extends TestCase
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

        $this->meta = Departamento::forceCreate(['id' => 16, 'nombre' => 'META', 'slug' => 'meta']);
        $this->villavo = Municipio::forceCreate(['id' => 685, 'departamento_id' => 16, 'nombre' => 'Villavicencio', 'slug' => 'villavicencio-16']);
        $this->granada = Municipio::forceCreate(['id' => 696, 'departamento_id' => 16, 'nombre' => 'Granada', 'slug' => 'granada-16']);
        CatalogoDane::aplicar();
    }

    private function archivo(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'hist').'.csv';
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    private function cargarGobernacion(): array
    {
        // Encabezados con sinónimos y separador ";" (como exporta Excel en español)
        return app(ImportadorHistorico::class)->importar($this->archivo(implode("\n", [
            'Año;Corporación;Departamento;Municipio;Partido;Candidato;Votos;Elegido',
            '2023;Gobernación;Meta;Villavicencio;PARTIDO A;ANA;85.000;si',
            '2023;Gobernación;Meta;Villavicencio;PARTIDO B;BETO;62.000;no',
            '2023;Gobernación;Meta;Granada;PARTIDO A;ANA;9.000;si',
            '2023;Gobernación;Meta;Granada;PARTIDO B;BETO;11.000;no',
            '2023;Gobernación;Meta;Granada;VOTOS NULOS;;500;',
            '2023;Gobernación;Meta;Pueblo Inventado;PARTIDO A;ANA;10;si',
        ])));
    }

    public function test_el_catalogo_recibe_el_codigo_dane(): void
    {
        $this->assertSame('50001', $this->villavo->fresh()->codigo_dane);
        $this->assertSame('50313', $this->granada->fresh()->codigo_dane);
    }

    public function test_corrige_nombres_con_la_n_danada(): void
    {
        DB::table('departamentos')->insert(['id' => 1, 'nombre' => 'ANTIOQUIA', 'slug' => 'antioquia']);
        DB::table('municipios')->insert(['id' => 1, 'departamento_id' => 1, 'nombre' => 'CÃ¡ceres', 'slug' => 'caceres-1']);

        CatalogoDane::aplicar();

        $this->assertSame(['CÁCERES', '05120'], array_values((array) DB::table('municipios')->where('id', 1)->first(['nombre', 'codigo_dane'])));
    }

    public function test_importa_omite_nulos_y_reporta_lugares_desconocidos(): void
    {
        $r = $this->cargarGobernacion();

        $this->assertSame([], $r['errores']);
        $this->assertSame(4, $r['insertadas']);
        $this->assertSame(1, $r['omitidas']);
        $this->assertSame(['Meta / Pueblo Inventado' => 1], $r['sin_lugar']);
        $this->assertSame(85000, (int) DB::table('historico_electoral')->where('candidato', 'ANA')->where('municipio_id', $this->villavo->id)->value('votos'));
    }

    public function test_volver_a_importar_reemplaza(): void
    {
        $this->cargarGobernacion();
        $r = $this->cargarGobernacion();

        $this->assertSame(4, $r['reemplazadas']);
        $this->assertSame(4, DB::table('historico_electoral')->count());
    }

    public function test_ganador_por_municipio_y_contienda_del_departamento(): void
    {
        $this->cargarGobernacion();
        $stats = app(HistoricoElectoralStats::class);

        $ganadores = $stats->ganadoresPorMunicipio(2023, 'gobernacion', $this->meta->id);
        $this->assertSame('ANA', $ganadores[$this->villavo->id]['candidato']);
        $this->assertSame('BETO', $ganadores[$this->granada->id]['candidato']);
        $this->assertSame('50313', $ganadores[$this->granada->id]['codigo_dane']);

        $contienda = $stats->contienda(2023, 'gobernacion', $this->meta->id);
        $this->assertSame(['ANA', 'BETO'], array_column($contienda, 'candidato'));
        $this->assertSame([94000, 73000], array_column($contienda, 'votos'));
        $this->assertTrue($contienda[0]['elegido']);

        $partidos = $stats->partidosGanadores($ganadores);
        $this->assertSame([1, 1], array_column($partidos, 'municipios'));
    }

    public function test_la_pantalla_muestra_resultados_y_cruza_con_los_registros(): void
    {
        $this->cargarGobernacion();
        $tenant = Tenant::where('slug', 'tenant-inicial')->first();
        $tenant->update(['cargo' => 'gobernacion', 'departamento_id' => $this->meta->id]);

        PublicRegistration::create([
            'full_name' => 'Persona', 'document_type' => 'CC', 'document_number' => '777001',
            'gender' => 'F', 'phone' => '3001234567', 'voting_municipality_id' => $this->villavo->id,
        ]);

        $analista = User::factory()->create(['tenant_id' => $tenant->id]);
        $analista->assignRole('Analista');

        Livewire::actingAs($analista)
            ->test(Historico::class)
            ->assertSet('anio', 2023)
            ->assertSet('cargo', 'gobernacion')
            ->assertSet('departamentoId', $this->meta->id)
            ->assertSee('ANA')
            ->assertSee('94.000')
            ->assertSee('Tus registros')
            ->assertDispatched('historico-mapa');

        $this->actingAs($analista)->get('/dashboard/historico')->assertOk();
    }

    public function test_solo_un_usuario_global_puede_importar(): void
    {
        $tenant = Tenant::where('slug', 'tenant-inicial')->first();
        $restringido = User::factory()->create(['tenant_id' => $tenant->id]);
        $restringido->assignRole('Administrador');

        Livewire::actingAs($restringido)
            ->test(Historico::class)
            ->set('archivo', UploadedFile::fake()->createWithContent('r.csv', "anio,cargo,departamento,partido,votos\n2023,gobernacion,META,X,1"))
            ->call('importar')
            ->assertForbidden();

        $global = User::factory()->create(['tenant_id' => null]);
        $global->assignRole('Administrador');

        Livewire::actingAs($global)
            ->test(Historico::class)
            ->set('archivo', UploadedFile::fake()->createWithContent('r.csv', "anio,cargo,departamento,partido,votos\n2023,gobernacion,META,X,1"))
            ->call('importar')
            ->assertSet('resumenImportacion.insertadas', 1);
    }

    public function test_sin_permiso_no_entra(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Referidor');

        $this->actingAs($user)->get('/dashboard/historico')->assertForbidden();
    }

    /** Base mínima con el formato del aplicativo de estadísticas de la Registraduría. */
    private function baseRegistraduria(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'rnec').'.db';
        $db = new \PDO('sqlite:'.$ruta);
        $db->exec('CREATE TABLE corporac (corpcoco number, corpdesc varchar2, corpinde varchar2)');
        $db->exec("INSERT INTO corporac VALUES (1,'GOBERNACION','DE'),(4,'CONCEJO','MU')");
        $db->exec('CREATE TABLE parametros (codigo integer, descripcion varchar, valor varchar)');
        $db->exec("INSERT INTO parametros VALUES (7,'Fecha Eleccion','25 DE OCTUBRE DE 2015')");
        $db->exec('CREATE TABLE elegidos (corporacion, departamento, municipio, comuna, partido, candidato, nombre, votos, genero)');
        $db->exec("INSERT INTO elegidos VALUES (1,52,-1,-1,1,1,'ANA',0,'F'),(4,52,1,-1,2,3,'CARLOS',0,'M')");
        $db->exec('CREATE TABLE x_resum_municipio_52 (corp, depto, desc_depto, mpio, desc_mpio, partido, desc_partido, candidato, desc_candidato, votos, porc_part_mesa, comuna)');
        $db->exec("INSERT INTO x_resum_municipio_52 VALUES
            (1,52,'META',1,'VILLAVICENCIO',1,'  PARTIDO A',1,'ANA',900,0,-1),
            (1,52,'META',1,'VILLAVICENCIO',2,'  PARTIDO B',2,'BETO',700,0,-1),
            (1,52,'META',1,'VILLAVICENCIO',997,'VOTOS NULOS',997,'VOTOS NULOS .',50,0,-1),
            (4,52,'META',1,'VILLAVICENCIO',2,'  PARTIDO B',0,'PARTIDO B ',300,0,-1),
            (4,52,'META',1,'VILLAVICENCIO',2,'  PARTIDO B',3,'CARLOS',200,0,-1),
            (4,52,'META',1,'VILLAVICENCIO',1,'  PARTIDO A',4,'DORA',400,0,-1),
            (4,52,'META',1,'VILLAVICENCIO',996,'VOTOS EN BLANCO',996,'VOTOS EN BLANCO .',30,0,-1)");

        return $ruta;
    }

    public function test_comando_importa_la_base_de_la_registraduria(): void
    {
        $this->artisan('historico:importar-registraduria', ['ruta' => $this->baseRegistraduria()])
            ->assertSuccessful();

        $stats = app(HistoricoElectoralStats::class);

        $gob = $stats->contienda(2015, 'gobernacion', $this->meta->id);
        $this->assertSame(['ANA', 'BETO'], array_column($gob, 'candidato'));
        $this->assertSame([true, false], array_column($gob, 'elegido'));

        // Concejo por listas: el partido B suma lista + preferente y se lleva la curul
        $concejo = $stats->contienda(2015, 'concejo', $this->meta->id, $this->villavo->id);
        $this->assertSame(['PARTIDO B', 'PARTIDO A', 'VOTO EN BLANCO'], array_column($concejo, 'partido'));
        $this->assertSame([500, 400, 30], array_column($concejo, 'votos'));
        $this->assertSame([1, null, null], array_column($concejo, 'curules'));
        $this->assertSame(['CARLOS'], $concejo[0]['elegidos']);

        $ganador = $stats->ganadoresPorMunicipio(2015, 'concejo', $this->meta->id)[$this->villavo->id];
        $this->assertSame(['PARTIDO B', null], [$ganador['partido'], $ganador['candidato']]);
    }

    public function test_presidencia_separa_vueltas_y_suma_todo_el_pais(): void
    {
        $importador = app(ImportadorHistorico::class);
        $fila = fn ($vuelta, $mun, $cand, $votos) => [
            'anio' => '2014', 'cargo' => 'presidencia', 'vuelta' => (string) $vuelta, 'departamento' => 'META',
            'municipio' => $mun, 'partido' => "PARTIDO {$cand}", 'candidato' => $cand, 'votos' => (string) $votos,
        ];

        $importador->guardar([$fila(1, 'Villavicencio', 'ANA', 100), $fila(1, 'Granada', 'BETO', 300)]);
        $importador->guardar([$fila(2, 'Villavicencio', 'ANA', 500), $fila(2, 'Granada', 'BETO', 200)]);

        $stats = app(HistoricoElectoralStats::class);
        $this->assertSame(['BETO', 'ANA'], array_column($stats->contienda(2014, 'presidencia', null), 'candidato'));

        $stats->vuelta = 2;
        $this->assertSame([500, 200], array_column($stats->contienda(2014, 'presidencia', null), 'votos'));

        $admin = User::factory()->create(['tenant_id' => null]);
        $admin->assignRole('Administrador');

        Livewire::actingAs($admin)
            ->test(Historico::class)
            ->set('anio', 2014)
            ->set('cargo', 'presidencia')
            ->set('departamentoId', null)
            ->set('vuelta', 2)
            ->assertSee('Segunda vuelta')
            ->assertSee('Colombia')
            ->assertSee('500');
    }

    public function test_csv_mesa_a_mesa_de_la_registraduria_se_suma_por_municipio(): void
    {
        $r = app(ImportadorHistorico::class)->importar($this->archivo(implode("\n", [
            "\u{FEFF}Código Departamento,Departamento,Código Municipio,Municipio,Comuna,Zona,Puesto,Corporacion,Circunscripcion,Partido,Código Candidato,Nombre Candidato,Votos",
            '52,META,1,VILLAVICENCIO,1,1,01,PRESIDENTE,NACIONAL,PARTIDO A,1,ANA,100',
            '52,META,1,VILLAVICENCIO,1,1,02,PRESIDENTE,NACIONAL,PARTIDO A,1,ANA,50',
            '52,META,1,VILLAVICENCIO,1,1,02,PRESIDENTE,NACIONAL,BLANCOS,996,N/A,7',
            '88,CONSULADOS,1,PANAMA,1,1,01,PRESIDENTE,NACIONAL,PARTIDO A,1,ANA,9',
        ])), 2018, null, 'Resultados.csv', 2);

        $this->assertSame(2, $r['insertadas']);
        $this->assertSame([], $r['sin_lugar']);

        $stats = app(HistoricoElectoralStats::class);
        $stats->vuelta = 2;
        $this->assertSame([['ANA', 150], [null, 7]], array_map(
            fn ($c) => [$c['candidato'], $c['votos']],
            $stats->contienda(2018, 'presidencia', null),
        ));
    }

    public function test_formato_mesa_a_mesa_2019(): void
    {
        $encabezado = 'Código Departamento,Nombre Departamento,Código Municipio,Nombre Municipio,Código Zona,Código Puesto,Nombre Puesto,Mesa,Código Comuna,Nombre Comuna,Código Corporación,Nombre Corporación,Código Circunscripción,Código Partido,Nombre Partido,Código Candidato,Nombre Candidato,Total Votos';
        $fila = fn ($mesa, $corp, $partido, $codCand, $cand, $votos) => "\"52\",\"META\",\"001\",\"VILLAVICENCIO\",\"01\",\"01\",\"COLEGIO\",\"{$mesa}\",\"01\",\"01COMUNA 1 CENTRO\",\"00\",\"{$corp}\",\"2\",\"00001\",\"{$partido}\",\"{$codCand}\",\"{$cand}\",{$votos}";

        $r = app(ImportadorHistorico::class)->importar($this->archivo(implode("\n", [
            $encabezado,
            $fila(1, 'CONCEJO', 'PARTIDO A', '00000', 'PARTIDO A', 10),
            $fila(2, 'CONCEJO', 'PARTIDO A', '00000', 'PARTIDO A', 5),
            $fila(1, 'CONCEJO', 'PARTIDO A', '00003', 'DORA', 7),
            $fila(1, 'CONCEJO', 'N/A', '00996', 'VOTOS EN BLANCO', 4),
            $fila(1, 'CONCEJO', 'N/A', '00997', 'VOTOS NULOS', 9),
            $fila(1, 'JAL', 'PARTIDO A', '00000', 'PARTIDO A', 3),
            // Codificación mezclada: esta fila trae la Ñ en Latin-1 dentro de un archivo UTF-8
            mb_convert_encoding($fila(1, 'CONCEJO', 'PARTIDO A', '00009', 'JUAN MUÑOZ', 2), 'Windows-1252', 'UTF-8'),
        ])), 2019, null, 'MMV_2019_NACIONAL.csv');

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['omitidas']);

        $concejo = DB::table('historico_electoral')->where('cargo', 'concejo')
            ->orderByDesc('votos')->get(['partido', 'candidato', 'votos'])
            ->map(fn ($f) => [$f->partido, $f->candidato, (int) $f->votos])->all();
        $this->assertSame([['PARTIDO A', null, 15], ['PARTIDO A', 'DORA', 7], ['VOTO EN BLANCO', null, 4], ['PARTIDO A', 'JUAN MUÑOZ', 2]], $concejo);

        $this->assertSame('COMUNA 1 CENTRO', DB::table('historico_electoral')->where('cargo', 'jal')->value('comuna'));
    }

    public function test_congreso_2022_en_utf16_filtra_circunscripciones_especiales(): void
    {
        $enc = 'Código Departamento,Nombre Departamento,Código Municipio,Nombre Municipio,Código Zona,Código Puesto,Nombre Puesto,Mesa,Código Comuna,Nombre Comuna,Código Corporación,Nombre Corporación,Código Circunscripción,Nombre Circunscripción,Código CITREP,Nombre CITREP,Código Partido,Nombre Partido,Código Candidato,Nombre Candidato,Total Votos';
        $fila = fn ($corp, $circ, $citrep, $partido, $codCand, $cand, $votos) => "\"52\",\"META\",\"001\",\"VILLAVICENCIO\",\"00\",\"00\",\"PUESTO\",\"0001\",\"00\",\"N/A\",\"01\",\"{$corp}\",\"01\",\"{$circ}\",\"00\",\"{$citrep}\",\"0011\",\"{$partido}\",\"{$codCand}\",\"{$cand}\",{$votos}";

        $texto = implode("\r\n", [
            $enc,
            $fila('CAMARA', 'DEPARTAMENTAL', 'NO CITREP', 'PARTIDO ÑANDÚ', '000', 'PARTIDO ÑANDÚ', 40),
            $fila('CAMARA', 'DEPARTAMENTAL', 'NO CITREP', 'PARTIDO ÑANDÚ', '101', 'JOSÉ MUÑOZ', 10),
            $fila('SENADO', 'NACIONAL', 'NO CITREP', 'PARTIDO ÑANDÚ', '000', 'PARTIDO ÑANDÚ', 7),
            $fila('CAMARA', 'INDIGENAS', 'NO CITREP', 'MAIS', '000', 'MAIS', 3),
            $fila('CAMARA', 'AFRO-DESCENDIENTES', 'NO CITREP', 'AFRO', '000', 'AFRO', 3),
            $fila('CAMARA', 'CITREP', 'CITREP - 07', 'PAZ', '000', 'PAZ', 3),
        ]);
        $ruta = tempnam(sys_get_temp_dir(), 'c22').'.csv';
        file_put_contents($ruta, "\xFF\xFE".mb_convert_encoding($texto, 'UTF-16LE', 'UTF-8'));

        $r = app(ImportadorHistorico::class)->importar($ruta, 2022, null, 'MMV_2022_52_META.csv');

        $this->assertSame([], $r['errores']);
        $this->assertSame(3, $r['insertadas']);
        $this->assertSame(3, $r['omitidas']);

        $stats = app(HistoricoElectoralStats::class);
        $camara = $stats->contienda(2022, 'camara', $this->meta->id);
        $this->assertSame([['PARTIDO ÑANDÚ', 50]], array_map(fn ($c) => [$c['partido'], $c['votos']], $camara));
        $this->assertSame('JOSÉ MUÑOZ', DB::table('historico_electoral')->where('cargo', 'camara')->whereNotNull('candidato')->value('candidato'));
        $this->assertSame(7, $stats->contienda(2022, 'senado', null)[0]['votos']);
    }

    public function test_escrutinio_con_codigos_formato_2026(): void
    {
        $dir = sys_get_temp_dir().'/basicos_'.uniqid();
        mkdir($dir);
        $ancho = fn (string $v, int $n) => str_pad(mb_convert_encoding($v, 'Windows-1252', 'UTF-8'), $n);

        file_put_contents("$dir/CORPORACION_01.TXT", '001'.$ancho('SENADO', 200)."\r\n002".$ancho('CAMARA', 200)."\r\n007".$ancho('CITREP', 200)."\r\n");
        file_put_contents("$dir/DIVIPOL_01.TXT", '52'.'001'.'00'.'01'.$ancho('META', 12).$ancho('VILLAVICENCIO', 30).$ancho('COLEGIO', 40)."0000000100000001000001\r\n");
        file_put_contents("$dir/PARTIDOS_01.TXT", '00008'.$ancho('PARTIDO ÑANDÚ', 200)."\r\n");
        // corp circ dep mun comuna partido cand pref nombre(50) apellido(50) cédula(15) género sorteo
        file_put_contents("$dir/CANDIDATOS_01.TXT", '002'.'1'.'52'.'000'.'00'.'00008'.'101'.'1'.$ancho('JOSÉ', 50).$ancho('MUÑOZ', 50).$ancho('123456789', 15)."M01\r\n");

        $l = fn ($corp, $circ, $partido, $cand, $votos) => "9999;52;001;001;01;000001;00;{$corp};{$circ};{$partido};{$cand};".str_pad((string) $votos, 8, '0', STR_PAD_LEFT).";\r\n";
        $csv = "$dir/MMV_9999.csv";
        file_put_contents($csv, implode('', [
            $l('002', '1', '0008', '000', 40),
            $l('002', '1', '0008', '101', 10),
            $l('002', '1', '0008', '101', 5),   // otra mesa: se suma
            $l('002', '1', '0000', '996', 3),   // blanco
            $l('002', '1', '0000', '997', 9),   // nulos: fuera
            $l('002', '4', '0008', '000', 50),  // indígenas: fuera
            $l('007', '9', '0008', '000', 50),  // CITREP: fuera
            $l('001', '0', '0008', '000', 0),   // cero votos: fuera
            $l('001', '0', '0008', '000', 7),
        ]));

        $this->artisan('historico:importar-escrutinio', ['mmv' => $csv, 'basicos' => $dir, '--anio' => 2026, '--sin-precalcular' => true])
            ->assertSuccessful();

        $stats = app(HistoricoElectoralStats::class);
        $camara = $stats->contienda(2026, 'camara', $this->meta->id);
        $this->assertSame([['PARTIDO ÑANDÚ', 55], ['VOTO EN BLANCO', 3]], array_map(fn ($c) => [$c['partido'], $c['votos']], $camara));
        $this->assertSame('JOSÉ MUÑOZ', DB::table('historico_electoral')->where('cargo', 'camara')->whereNotNull('candidato')->value('candidato'));
        $this->assertSame(7, $stats->contienda(2026, 'senado', null)[0]['votos']);
        // La cédula del archivo de candidatos nunca llega a la base
        $this->assertSame(0, DB::table('historico_electoral')->where('candidato', 'like', '%123456789%')->count());
    }
}
