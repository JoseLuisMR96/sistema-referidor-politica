<?php

use App\Http\Controllers\Admin\ReferidoresPregonerosMasivoExportController;
use App\Http\Controllers\Admin\ReferidosPregonerosExportController;
use App\Http\Controllers\DashboardExportController;
use App\Http\Controllers\EvidenciaEventoController;
use App\Http\Controllers\Exports\PublicRegistrationsExportController;
use App\Http\Controllers\MarcaArchivoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantSwitchController;
use App\Livewire\Admin\Ideologias\Index as IdeologiasIndex;
use App\Livewire\Admin\Integraciones;
use App\Livewire\Admin\Redes\Cuenta as RedesCuenta;
use App\Livewire\Admin\Redes\Index as RedesIndex;
use App\Livewire\Admin\Referidores\Create as ReferidoresCreate;
use App\Livewire\Admin\Referidores\Edit as ReferidoresEdit;
use App\Livewire\Admin\Referidores\Index as ReferidoresIndex;
use App\Livewire\Admin\Referidores\Show as ReferidoresShow;
use App\Livewire\Admin\Referrers\Create as ReferrersCreate;
use App\Livewire\Admin\Referrers\Edit as ReferrersEdit;
use App\Livewire\Admin\Referrers\Index as ReferrersIndex;
use App\Livewire\Admin\Tenants\Formulario as TenantsFormulario;
use App\Livewire\Admin\Tenants\Index as TenantsIndex;
use App\Livewire\Dashboard\Analisis as DashboardAnalisis;
use App\Livewire\Dashboard\Historico as DashboardHistorico;
use App\Livewire\Dashboard\Redes as DashboardRedes;
use App\Livewire\Dashboard\Stats;
use App\Livewire\Eventos\Equipo as EventosEquipo;
use App\Livewire\Eventos\Formulario as EventosFormulario;
use App\Livewire\Eventos\Index as EventosIndex;
use App\Livewire\Eventos\Show as EventosShow;
use App\Livewire\ImportedPeople\Index as ImportedPeopleIndex;
use App\Livewire\ImportedPeople\Show as ImportedPeopleShow;
use App\Livewire\Public\ReferirPregonero;
use App\Livewire\Public\RegistrationForm;
use App\Livewire\Registrations\Edit as RegistrationsEdit;
use App\Livewire\Registrations\Index as RegistrationsIndex;
use App\Livewire\Registrations\Show as RegistrationsShow;
use App\Livewire\Users\UserCreate;
use App\Livewire\Users\UserEdit;
use App\Livewire\Users\UserIndex;
use App\Livewire\Whatsapp\CampaignComposer;
use App\Livewire\Whatsapp\WhatsappOutboxManager;
use App\Livewire\Wpp\CampaignUploader;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/

Route::get(
    '/',
    fn () => auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login')
)->name('home');

/*
|--------------------------------------------------------------------------
| Público (sin login)
|--------------------------------------------------------------------------
*/
Route::get('/registro', RegistrationForm::class)->name('public.registro');
Route::get('/pregoneros/{id_unico}/referir', ReferirPregonero::class)
    ->name('public.referir');

// Logo, foto y fondo del candidato (públicos: se ven en sus formularios de registro)
Route::get('/marca/{slug}/{tipo}', MarcaArchivoController::class)
    ->where('tipo', 'logo|foto|fondo')
    ->name('marca.archivo');

/*
|--------------------------------------------------------------------------
| Privado (con login)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', Stats::class)
        ->middleware('permission:dashboard.ver')
        ->name('dashboard');

    // Dashboard de redes sociales (separado del de registros)
    Route::get('/dashboard/redes', DashboardRedes::class)
        ->middleware('permission:redes.ver')
        ->name('dashboard.redes');

    // Histórico electoral (resultados públicos, globales)
    Route::get('/dashboard/historico', DashboardHistorico::class)
        ->middleware('permission:historico.ver')
        ->name('dashboard.historico');

    // Análisis electoral (lecturas sobre el histórico)
    Route::get('/dashboard/analisis', DashboardAnalisis::class)
        ->middleware('permission:historico.ver')
        ->name('dashboard.analisis');

    // Perfil (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/dashboard/export/excel', [DashboardExportController::class, 'export'])
        ->middleware(['auth', 'permission:dashboard.exportar'])
        ->name('dashboard.export.excel');

    /*
    |----------------------------------------------------------------------
    | WhatsApp masivo (Twilio)
    |----------------------------------------------------------------------
    */
    Route::get('/whatsapp/campanas/nueva', CampaignComposer::class)
        ->middleware('permission:whatsapp.enviar')
        ->name('whatsapp.campaigns.create');

    Route::get('/whatsapp/whatsapp-outbox', WhatsappOutboxManager::class)
        ->middleware('permission:whatsapp.enviar')
        ->name('whatsapp.whatsapp.outbox');

    /*
    |--------------------------------------------------------------------------
    | WhatsApp WPPConnect
    |--------------------------------------------------------------------------
    */

    Route::get('/whatsapp/wpp/campanas/nueva', CampaignUploader::class)
        ->middleware('permission:whatsapp.enviar')
        ->name('wpp.campaigns.create');
    /*
    |----------------------------------------------------------------------
    | Referidores
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:referidores.ver')->group(function () {

        Route::get('/admin/referidores', ReferrersIndex::class)
            ->name('referrers.index');

        Route::get('/admin/referidores/crear', ReferrersCreate::class)
            ->middleware('permission:referidores.crear')
            ->name('referrers.create');

        Route::get('/admin/referidores/{referrer}/editar', ReferrersEdit::class)
            ->middleware('permission:referidores.editar')
            ->name('referrers.edit');
    });

    Route::middleware('permission:pregoneros_referidores.ver')->group(function () {
        Route::get('/admin/referidores-pregoneros', ReferidoresIndex::class)
            ->name('pregoneros.referidores.index');

        Route::get('/admin/referidores-pregoneros/crear', ReferidoresCreate::class)
            ->middleware('permission:pregoneros_referidores.crear')
            ->name('pregoneros.referidores.create');

        Route::get('/admin/referidores-pregoneros/export-masivo', [ReferidoresPregonerosMasivoExportController::class, 'xlsx'])
            ->middleware('permission:pregoneros_referidores.exportar_masivo')
            ->name('pregoneros.referidores.export_masivo');

        Route::get('/admin/referidores-pregoneros/{referidor}/editar', ReferidoresEdit::class)
            ->middleware('permission:pregoneros_referidores.editar')
            ->name('pregoneros.referidores.edit');

        Route::get('/admin/referidores-pregoneros/{referidor}', ReferidoresShow::class)
            ->name('pregoneros.referidores.show');

        Route::get('/admin/referidores-pregoneros/{referidor}/export-referidos', [ReferidosPregonerosExportController::class, 'csv'])
            ->middleware('permission:pregoneros_referidos.exportar')
            ->name('referidores.export_referidos');
    });

    /*
    |----------------------------------------------------------------------
    | Registros (PublicRegistration)
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:registros.ver_todos')->group(function () {

        Route::get('/registros', RegistrationsIndex::class)
            ->name('registrations.index');

        Route::get('/registros/{publicRegistration}', RegistrationsShow::class)
            ->name('registrations.show');

        // Edit SOLO Admin
        Route::get('/registros/{publicRegistration}/editar', RegistrationsEdit::class)
            ->middleware('permission:registros.editar')
            ->name('registrations.edit');
    });

    // Export CSV (Registros)
    Route::get('/export/registros', PublicRegistrationsExportController::class)
        ->middleware('permission:registros.exportar')
        ->name('registrations.export');

    /*
    |----------------------------------------------------------------------
    | Importados (imported_people)
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:importados.ver')->group(function () {
        Route::get('/imported-people', ImportedPeopleIndex::class)
            ->name('imported-people.index');

        Route::get('/imported-people/{id}', ImportedPeopleShow::class)
            ->name('imported-people.show');
    });

    /*
    |----------------------------------------------------------------------
    | Tenants (candidatos)
    |----------------------------------------------------------------------
    */
    /*
    |----------------------------------------------------------------------
    | Eventos de campaña (trazabilidad en territorio) y equipo
    |----------------------------------------------------------------------
    | Los modelos llevan el scope del tenant: el evento o la evidencia de
    | otro candidato da 404.
    */
    Route::get('/eventos', EventosIndex::class)
        ->middleware('permission:eventos.ver')
        ->name('eventos.index');

    Route::get('/eventos/crear', EventosFormulario::class)
        ->middleware('permission:eventos.crear')
        ->name('eventos.create');

    Route::get('/eventos/evidencias/{evidencia}', EvidenciaEventoController::class)
        ->middleware('permission:eventos.ver')
        ->name('eventos.evidencia');

    Route::get('/eventos/{evento}', EventosShow::class)
        ->middleware('permission:eventos.ver')
        ->name('eventos.show');

    Route::get('/eventos/{evento}/editar', EventosFormulario::class)
        ->middleware('permission:eventos.editar')
        ->name('eventos.edit');

    Route::get('/equipo-campana', EventosEquipo::class)
        ->middleware('permission:eventos.ver|equipo.gestionar')
        ->name('equipo.index');

    // Selector del menú (solo usuarios globales; se valida en el controller)
    Route::post('/tenant/seleccionar', TenantSwitchController::class)
        ->name('tenant.seleccionar');

    Route::get('/admin/tenants', TenantsIndex::class)
        ->middleware('permission:tenants.gestionar')
        ->name('tenants.index');

    Route::get('/admin/tenants/crear', TenantsFormulario::class)
        ->middleware('permission:tenants.gestionar')
        ->name('tenants.create');

    Route::get('/admin/tenants/{tenant}/editar', TenantsFormulario::class)
        ->middleware('permission:tenants.gestionar')
        ->name('tenants.edit');

    /*
    |----------------------------------------------------------------------
    | Redes sociales, integraciones e ideología de los partidos
    |----------------------------------------------------------------------
    */
    Route::get('/admin/redes', RedesIndex::class)
        ->middleware('permission:redes.configurar')
        ->name('redes.index');

    Route::get('/admin/redes/crear', RedesCuenta::class)
        ->middleware('permission:redes.configurar')
        ->name('redes.create');

    // La cuenta se busca con el scope del tenant: la de otro candidato da 404
    Route::get('/admin/redes/{cuenta}/editar', RedesCuenta::class)
        ->middleware('permission:redes.configurar')
        ->name('redes.edit');

    // Credenciales globales (Meta, IA): el componente exige además usuario global
    Route::get('/admin/integraciones', Integraciones::class)
        ->middleware('permission:redes.configurar')
        ->name('integraciones.index');

    Route::get('/admin/ideologia', IdeologiasIndex::class)
        ->middleware('permission:historico.ver')
        ->name('ideologias.index');

    /*
    |----------------------------------------------------------------------
    | Usuarios
    |----------------------------------------------------------------------
    */
    Route::get('/usuarios', UserIndex::class)
        ->name('users.index')
        ->middleware('permission:usuarios.ver');

    Route::get('/usuarios/crear', UserCreate::class)
        ->name('users.create')
        ->middleware('permission:usuarios.crear');

    Route::get('/usuarios/{user}/editar', UserEdit::class)
        ->name('users.edit')
        ->middleware('permission:usuarios.editar');
});

require __DIR__.'/auth.php';
