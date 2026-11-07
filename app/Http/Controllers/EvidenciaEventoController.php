<?php

namespace App\Http\Controllers;

use App\Models\EvidenciaEvento;
use Illuminate\Support\Facades\Storage;

/**
 * Sirve una evidencia de evento (foto o soporte) desde el disco privado.
 *
 * La ruta exige eventos.ver y el route model binding filtra por el candidato
 * del contexto: la evidencia de otro candidato da 404.
 */
class EvidenciaEventoController extends Controller
{
    public function __invoke(EvidenciaEvento $evidencia)
    {
        abort_unless(auth()->user()->can('eventos.ver'), 403);

        $disco = Storage::disk(EvidenciaEvento::DISCO);
        abort_unless($disco->exists($evidencia->ruta), 404);

        // Fotos y PDF se ven en el navegador; lo demás se descarga
        $enLinea = $evidencia->esFoto() || $evidencia->mime === 'application/pdf';

        return $enLinea
            ? $disco->response($evidencia->ruta, $evidencia->nombre, [
                'Content-Type' => $evidencia->mime ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ])
            : $disco->download($evidencia->ruta, $evidencia->nombre);
    }
}
