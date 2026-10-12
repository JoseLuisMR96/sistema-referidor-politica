<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappCampaignMessage;
use App\Livewire\Admin\Referidores\Index as ReferidoresIndex;
use App\Livewire\Dashboard\Stats;
use App\Models\Referrer;
use App\Livewire\Public\ReferirPregonero;
use App\Livewire\Registrations\Edit as RegistrationsEdit;
use App\Livewire\Users\UserEdit;
use App\Livewire\Whatsapp\CampaignComposer;
use App\Livewire\Whatsapp\WhatsappOutboxManager;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\ReferidorPregonero;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\DashboardStats;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArreglosUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->usarTenant();
    }

    private function usuarioConRol(string $rol): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    private function municipio(string $nombre = 'Villavicencio'): Municipio
    {
        $depto = Departamento::firstOrCreate(['nombre' => 'META'], ['slug' => 'meta']);

        return Municipio::create([
            'departamento_id' => $depto->id,
            'nombre' => $nombre,
            'slug' => str($nombre)->slug(),
        ]);
    }

    private function registro(array $extra = []): PublicRegistration
    {
        static $doc = 1000;

        return PublicRegistration::create([
            'full_name' => 'Persona Prueba',
            'document_type' => 'CC',
            'document_number' => (string) $doc++,
            'gender' => 'F',
            'residence_municipality' => 'X',
            'voting_municipality' => 'X',
            'phone' => '3001234567',
            ...$extra,
        ]);
    }

    // --- WhatsApp Masivo (Composer) ---

    public function test_modo_prueba_normaliza_el_numero_y_usa_estados_validos(): void
    {
        Queue::fake();

        Livewire::actingAs($this->usuarioConRol('Administrador'))
            ->test(CampaignComposer::class)
            ->set('type', 'text')
            ->set('name', 'Campaña de prueba')
            ->set('body', 'Hola {name}')
            ->set('recipientsText', '3001234567|Ana')
            ->set('testMode', true)
            ->set('testPhone', '300 123 4567')
            ->call('createAndSend')
            ->assertHasNoErrors();

        $msg = WhatsappMessage::sole();
        $this->assertSame('whatsapp:+573001234567', $msg->to);
        $this->assertSame('pending', $msg->status);
        $this->assertSame('sending', DB::table('campaigns')->value('status'));
        Queue::assertPushed(SendWhatsappCampaignMessage::class, 1);
    }

    public function test_numeros_manuales_se_normalizan_y_se_omiten_los_invalidos(): void
    {
        Queue::fake();

        Livewire::actingAs($this->usuarioConRol('Administrador'))
            ->test(CampaignComposer::class)
            ->set('type', 'text')
            ->set('name', 'Campaña real')
            ->set('recipientsText', "3001234567|Ana\n123|Malo\n+573109876543")
            ->set('testMode', false)
            ->call('createAndSend')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['whatsapp:+573001234567', 'whatsapp:+573109876543'],
            WhatsappMessage::pluck('to')->all(),
        );
    }

    public function test_numero_de_prueba_invalido_no_crea_campana(): void
    {
        Queue::fake();

        Livewire::actingAs($this->usuarioConRol('Administrador'))
            ->test(CampaignComposer::class)
            ->set('type', 'text')
            ->set('name', 'Campaña de prueba')
            ->set('recipientsText', '3001234567')
            ->set('testPhone', '12345678')
            ->call('createAndSend')
            ->assertHasErrors('testPhone');

        $this->assertSame(0, DB::table('campaigns')->count());
    }

    // --- Outbox ---

    public function test_outbox_importa_aunque_el_excel_traiga_columna_de_nombre(): void
    {
        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray([
            ['nombre', 'celular', 'mensaje'],
            ['Ana', '3001234567', 'Hola'],
            ['Beto', '123', 'Hola'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'outbox').'.xlsx';
        (new Xlsx($sheet))->save($path);

        Livewire::actingAs($this->usuarioConRol('Administrador'))
            ->test(WhatsappOutboxManager::class)
            ->set('xlsx', UploadedFile::fake()->createWithContent('contactos.xlsx', file_get_contents($path)))
            ->set('useRowMessage', true)
            ->call('import')
            ->assertSet('importSummary.inserted', 1)
            ->assertSet('importSummary.skipped', 1)
            ->assertSet('importSummary.errors', []);

        $this->assertSame('573001234567', DB::table('whatsapp_outbox')->value('phone'));
    }

    // --- Registros ---

    public function test_operador_puede_editar_y_el_municipio_se_guarda_por_fk(): void
    {
        $muni = $this->municipio();
        $reg = $this->registro();

        Livewire::actingAs($this->usuarioConRol('Operador'))
            ->test(RegistrationsEdit::class, ['publicRegistration' => $reg])
            ->set('voting_department_id', $muni->departamento_id)
            ->set('voting_municipality_id', $muni->id)
            ->call('update')
            ->assertHasNoErrors();

        $reg->refresh();
        $this->assertSame($muni->id, $reg->voting_municipality_id);
        $this->assertSame('Villavicencio', $reg->voting_municipality);
    }

    // --- Dashboard ---

    public function test_estadisticas_unen_legacy_y_separan_menores(): void
    {
        $muni = $this->municipio();
        $this->registro(['voting_municipality_id' => $muni->id, 'age' => 30]);
        $this->registro(['voting_municipality' => 'villavicencio', 'age' => 16]);

        $stats = app(DashboardStats::class);

        $this->assertSame(
            [['label' => 'Villavicencio', 'departamento' => 'META', 'value' => 2]],
            $stats->topMunicipios(),
        );

        $rangos = collect($stats->rangosEdad())->pluck('value', 'label')->all();
        $this->assertSame(['0-17' => 1, '26-35' => 1], $rangos);
    }

    public function test_dashboard_y_su_export_cargan(): void
    {
        $this->registro(['voting_municipality_id' => $this->municipio()->id, 'age' => 40]);
        $admin = $this->usuarioConRol('Administrador');

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Villavicencio');
        $this->actingAs($admin)->get('/dashboard/export/excel')->assertOk()->assertDownload('dashboard.xlsx');
    }

    public function test_ranking_de_referidores_excluye_importados(): void
    {
        $real = Referrer::create(['name' => 'Real', 'code' => 'REAL0001']);
        $soloImportados = Referrer::create(['name' => 'Importado', 'code' => 'IMPO0001']);

        $this->registro(['referrer_id' => $real->id]);
        $this->registro(['referrer_id' => $real->id, 'origen' => 'importado']);
        $this->registro(['referrer_id' => $soloImportados->id, 'origen' => 'importado']);

        $ranking = Livewire::actingAs($this->usuarioConRol('Administrador'))
            ->test(Stats::class)
            ->get('topReferrers');

        $this->assertSame(
            ['REAL0001' => 1, 'IMPO0001' => 0],
            collect($ranking)->pluck('total', 'code')->map(fn ($t) => (int) $t)->all(),
        );
    }

    // --- Menores ---

    public function test_ordenar_por_columna_no_permitida_no_revienta(): void
    {
        Livewire::actingAs($this->usuarioConRol('Administrador'))
            ->withQueryParams(['sortField' => 'monto_pagar; drop table users', 'sortDirection' => 'x'])
            ->test(ReferidoresIndex::class)
            ->assertOk();
    }

    public function test_formulario_publico_limita_envios_por_ip(): void
    {
        $referidor = ReferidorPregonero::create([
            'nombre' => 'Ref',
            'cedula' => '555555',
            'puesto_votacion' => 'Colegio',
        ]);

        $componente = Livewire::test(ReferirPregonero::class, ['id_unico' => $referidor->id_unico]);

        for ($i = 0; $i < 30; $i++) {
            $componente->set('nombre', '')->call('guardar');
        }

        $componente->call('guardar')->assertHasErrors('general');
    }

    public function test_sin_permiso_de_roles_no_se_cambian_los_roles(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo(['usuarios.ver', 'usuarios.editar']);

        $objetivo = $this->usuarioConRol('Analista');
        $admin = Role::findByName('Administrador');

        Livewire::actingAs($editor)
            ->test(UserEdit::class, ['user' => $objetivo])
            ->set('roleIds', [$admin->id])
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame(['Analista'], $objetivo->fresh()->getRoleNames()->all());
    }
}
