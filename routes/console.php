<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Foto diaria de las redes de los candidatos (crecimiento semanal en el
// dashboard). Requiere el cron: * * * * * php artisan schedule:run
Schedule::command('redes:sincronizar')
    ->dailyAt('06:00')
    ->withoutOverlapping();

// Termina cada despliegue de Hostinger (migraciones, permisos, cachés, cola)
// cuando llega un commit nuevo. Ver DespliegueAutomaticoCommand.
Schedule::command('deploy:automatico')
    ->everyMinute()
    ->withoutOverlapping();
