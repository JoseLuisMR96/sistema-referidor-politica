<?php

namespace App\Services\Redes;

use App\Models\Integracion;
use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo de la Graph API de Meta (Facebook e Instagram).
 *
 * La URL base, la versión y las credenciales salen de la integración "meta"
 * que se configura en Administración → Redes sociales.
 */
class MetaGraphClient
{
    public const URL_POR_DEFECTO = 'https://graph.facebook.com';

    // Meta retira cada versión unos 2 años después de publicarla: si las
    // llamadas empiezan a fallar por versión, súbela en la configuración.
    public const VERSION_POR_DEFECTO = 'v26.0';

    public function __construct(private ?Integracion $integracion = null)
    {
        $this->integracion ??= Integracion::meta();
    }

    public function integracion(): Integracion
    {
        return $this->integracion;
    }

    public function configurada(): bool
    {
        return $this->integracion->exists
            && $this->integracion->activo
            && filled($this->integracion->credencial('access_token'));
    }

    /**
     * GET a la Graph API. Si no se pasa token, usa el de la integración
     * (tu cuenta). Lanza RuntimeException con el mensaje de Meta si falla.
     *
     * @return array<string, mixed>
     */
    public function get(string $ruta, array $query = [], ?string $token = null): array
    {
        $token ??= $this->integracion->credencial('access_token');

        if (blank($token)) {
            throw new \RuntimeException('Falta el access token de Meta (configúralo en Redes sociales).');
        }

        $query['access_token'] = $token;

        // appsecret_proof: Meta lo exige si la app tiene activado "Require App Secret"
        if ($secret = $this->integracion->credencial('app_secret')) {
            $query['appsecret_proof'] = hash_hmac('sha256', $token, $secret);
        }

        $base = rtrim($this->integracion->credencial('graph_url') ?: self::URL_POR_DEFECTO, '/');
        $version = trim($this->integracion->credencial('graph_version') ?: self::VERSION_POR_DEFECTO, '/');

        $respuesta = Http::acceptJson()
            ->timeout(30)
            ->get("{$base}/{$version}/".ltrim($ruta, '/'), $query);

        if ($respuesta->failed()) {
            $mensaje = $respuesta->json('error.message') ?: "HTTP {$respuesta->status()}";

            throw new \RuntimeException("Meta respondió con error: {$mensaje}");
        }

        return $respuesta->json() ?? [];
    }
}
