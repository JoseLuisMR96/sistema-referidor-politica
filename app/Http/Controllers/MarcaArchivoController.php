<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve el logo, la foto o la imagen de fondo de un candidato.
 *
 * Es público a propósito: se muestran en sus formularios de registro, que
 * abren personas sin cuenta. Va por ruta (y no por /storage) para no
 * depender de `php artisan storage:link` en cada servidor.
 */
class MarcaArchivoController extends Controller
{
    public function __invoke(string $slug, string $tipo): Response
    {
        abort_unless(in_array($tipo, ['logo', 'foto', 'fondo'], true), 404);

        $ruta = Tenant::where('slug', $slug)->value("{$tipo}_path");

        abort_unless($ruta && Storage::disk('public')->exists($ruta), 404);

        return Storage::disk('public')->response($ruta, null, [
            // La URL lleva ?v=<updated_at>: se puede cachear mucho tiempo
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
