<?php

namespace App\Http\Controllers;

use App\Models\Eleccion;
use App\Models\ReporteMesa;
use Illuminate\Support\Facades\Storage;

/**
 * Foto del E-14 de un reporte (disco privado). Con el scope del tenant: la
 * de otro candidato da 404.
 */
class DiaEController extends Controller
{
    public function foto(ReporteMesa $reporte)
    {
        abort_unless(auth()->user()->can('diae.ver'), 403);
        $disco = Storage::disk(Eleccion::DISCO);
        abort_unless($reporte->foto_ruta && $disco->exists($reporte->foto_ruta), 404);

        return $disco->response($reporte->foto_ruta, "e14-mesa-{$reporte->mesa}.jpg", [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
