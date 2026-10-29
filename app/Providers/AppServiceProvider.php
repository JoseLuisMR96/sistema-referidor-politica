<?php

namespace App\Providers;

use App\Services\Redes\Tono\ClasificadorTono;
use App\Services\Redes\Tono\ClaudeClasificadorTono;
use App\Support\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // scoped: se reinicia entre peticiones y entre jobs de la cola
        $this->app->scoped(TenantContext::class);

        // Clasificación del tono de comentarios (los tests enlazan uno falso)
        $this->app->bind(ClasificadorTono::class, ClaudeClasificadorTono::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
