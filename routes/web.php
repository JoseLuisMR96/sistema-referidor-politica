<?php

use App\Livewire\Users\UserEdit;
use App\Livewire\Dashboard\Stats;
use App\Livewire\Users\UserIndex;
use App\Livewire\Users\UserCreate;
use Illuminate\Support\Facades\Route;
use App\Livewire\Public\RegistrationForm;

use App\Http\Controllers\ProfileController;
use App\Livewire\Whatsapp\CampaignComposer;
use App\Http\Controllers\ImportedPersonController;

use App\Http\Controllers\DashboardExportController;
use App\Http\Controllers\Retell\CallResultController;
use App\Livewire\Admin\Referrers\Edit as ReferrersEdit;
use App\Http\Controllers\TwilioWhatsappStatusController;
use App\Livewire\Admin\Referrers\Index as ReferrersIndex;
use App\Livewire\Registrations\Edit as RegistrationsEdit;
use App\Livewire\Registrations\Show as RegistrationsShow;
use App\Livewire\Admin\Referrers\Create as ReferrersCreate;
use App\Livewire\ImportedPeople\Show as ImportedPeopleShow;
use App\Livewire\Registrations\Index as RegistrationsIndex;

// ✅ IMPORTA EL LIVEWIRE NUEVO
use App\Livewire\ImportedPeople\Index as ImportedPeopleIndex;
use App\Http\Controllers\Exports\PublicRegistrationsExportController;

/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/

Route::get(
    '/',
    fn() => auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login')
)->name('home');

/*
|--------------------------------------------------------------------------
| Público (sin login)
|--------------------------------------------------------------------------
*/
Route::get('/registro', RegistrationForm::class)->name('public.registro');

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

require __DIR__ . '/auth.php';
