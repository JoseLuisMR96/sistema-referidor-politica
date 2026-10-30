<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Termina el despliegue solo, después de que Hostinger publica un push.
 *
 * El Git de Hostinger clona en una carpeta temporal, corre composer install
 * ahí (sin .env) y copia el resultado a public_html. No migra, no renueva las
 * cachés de config/rutas (quedan las viejas) ni reinicia la cola. Esta tarea
 * corre cada minuto con schedule:run y, cuando ve un commit nuevo en
 * .git/HEAD, hace esos pasos en public_html con el .env real.
 *
 * - Espera a ver el mismo commit dos minutos seguidos: así no actúa mientras
 *   Hostinger todavía está copiando archivos.
 * - Solo en APP_ENV=production. Lo que hace queda en storage/logs/despliegue.log.
 * - deploy.sh sigue sirviendo para correrlo a mano.
 */
class DespliegueAutomaticoCommand extends Command
{
    protected $signature = 'deploy:automatico
        {--head= : Ruta del archivo HEAD de git (por defecto .git/HEAD)}
        {--forzar : Correr los pasos aunque el commit no haya cambiado}';

    protected $description = 'Termina el despliegue (migraciones, permisos, cachés) cuando llega un commit nuevo';

    public function handle(): int
    {
        if (! app()->environment('production')) {
            $this->line('Solo corre en producción.');

            return self::SUCCESS;
        }

        $commit = $this->commitActual();
        if (! $commit) {
            return self::SUCCESS;
        }

        $carpeta = storage_path('app/despliegue');
        File::ensureDirectoryExists($carpeta);
        $ultimo = trim((string) @file_get_contents("{$carpeta}/ultimo"));
        $visto = trim((string) @file_get_contents("{$carpeta}/visto"));

        if (! $this->option('forzar')) {
            if ($commit === $ultimo) {
                return self::SUCCESS; // nada nuevo
            }
            if ($commit !== $visto) {
                file_put_contents("{$carpeta}/visto", $commit); // recién llegó: se espera un minuto

                return self::SUCCESS;
            }
        }

        $this->registrar("Commit {$commit}: terminando despliegue");

        $pasos = [
            ['package:discover', []],
            ['migrate', ['--force' => true]],
            ['db:seed', ['--class' => 'RolesYPermisosSeeder', '--force' => true]],
            // No optimize:clear: también vacía la caché de la app (el precálculo del Histórico)
            ['config:clear', []],
            ['route:clear', []],
            ['view:clear', []],
            ['event:clear', []],
            ['config:cache', []],
            ['route:cache', []],
            ['view:cache', []],
            ['queue:restart', []],
        ];

        if (! file_exists(public_path('storage'))) {
            $pasos[] = ['storage:link', []];
        }

        $fallo = false;
        foreach ($pasos as [$comando, $args]) {
            try {
                $codigo = Artisan::call($comando, $args);
                $salida = trim(preg_replace('/\e\[[0-9;]*m/', '', Artisan::output()));
                $this->registrar("  {$comando}: ".($codigo === 0 ? 'ok' : "código {$codigo}").($salida !== '' ? ' — '.str_replace("\n", ' | ', mb_substr($salida, 0, 500)) : ''));
                $fallo = $fallo || $codigo !== 0;
            } catch (\Throwable $e) {
                $this->registrar("  {$comando}: ERROR — {$e->getMessage()}");
                $fallo = true;
            }
        }

        // Se marca como hecho aunque algo falle: reintentar cada minuto no lo
        // arregla. El error queda en el log y se corre deploy.sh a mano.
        file_put_contents("{$carpeta}/ultimo", $commit);
        $this->registrar($fallo ? '  Terminó con errores: revisa arriba y corre deploy.sh a mano.' : '  Listo.');

        return $fallo ? self::FAILURE : self::SUCCESS;
    }

    /** Hash del commit publicado (HEAD puede traer el hash o "ref: refs/heads/main"). */
    private function commitActual(): ?string
    {
        $head = $this->option('head') ?: base_path('.git/HEAD');
        $contenido = trim((string) @file_get_contents($head));

        if (str_starts_with($contenido, 'ref: ')) {
            $contenido = trim((string) @file_get_contents(dirname($head).'/'.substr($contenido, 5)));
        }

        return preg_match('/^[0-9a-f]{40}$/', $contenido) ? $contenido : null;
    }

    private function registrar(string $linea): void
    {
        $this->line($linea);
        file_put_contents(storage_path('logs/despliegue.log'), '['.now()->toDateTimeString().'] '.$linea.PHP_EOL, FILE_APPEND);
    }
}
