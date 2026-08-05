<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

/**
 * Valida la firma X-Twilio-Signature de los webhooks entrantes.
 *
 * Sin esto cualquiera que conozca la URL puede inyectar respuestas de botones
 * falsas y disparar envíos de WhatsApp con costo.
 */
class VerifyTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('services.twilio.verify_webhook_signature', true)) {
            Log::warning('Validación de firma Twilio DESACTIVADA', ['url' => $request->fullUrl()]);

            return $next($request);
        }

        $token = (string) config('services.twilio.token');

        if ($token === '') {
            Log::error('No se puede validar el webhook: TWILIO_AUTH_TOKEN no está configurado');

            return response('Twilio auth token not configured', 500);
        }

        $signature = (string) $request->header('X-Twilio-Signature');
        $validator = new RequestValidator($token);

        if (! $validator->validate($signature, $request->fullUrl(), $request->all())) {
            Log::warning('Firma de webhook Twilio inválida', [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 403);
        }

        return $next($request);
    }
}
