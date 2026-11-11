<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas.
 *
 * - X-Frame-Options: ninguna otra página puede meter la app en un iframe
 *   (evita que engañen a un usuario logueado para que haga clic).
 * - nosniff: el navegador no adivina el tipo de un archivo (una "imagen" no
 *   se ejecuta como HTML o JS).
 * - HSTS: el navegador siempre entra por https (solo si la petición ya es https,
 *   para no romper el local).
 * - Permissions-Policy: geolocalización y cámara solo para la propia app
 *   (eventos usan la ubicación y el testigo toma la foto del E-14).
 * - Se quita X-Powered-By, que delata la versión de PHP.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $h = $response->headers;
        $h->set('X-Frame-Options', 'SAMEORIGIN', false);
        $h->set('X-Content-Type-Options', 'nosniff', false);
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $h->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=()', false);

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        $h->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
