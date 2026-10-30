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

    /**
     * Cambia un token de corta duración (las pocas horas que da el Graph API
     * Explorer) por uno de larga duración (~60 días). Necesita App ID y App
     * Secret. Devuelve el token nuevo y cuándo vence (null = no informado).
     *
     * @return array{token: string, vence: ?\Illuminate\Support\Carbon}
     */
    public function extenderToken(string $token): array
    {
        $appId = $this->integracion->credencial('app_id');
        $secreto = $this->integracion->credencial('app_secret');

        if (blank($appId) || blank($secreto)) {
            throw new \RuntimeException('Para extender el token hacen falta el App ID y el App Secret.');
        }

        $base = rtrim($this->integracion->credencial('graph_url') ?: self::URL_POR_DEFECTO, '/');
        $version = trim($this->integracion->credencial('graph_version') ?: self::VERSION_POR_DEFECTO, '/');

        $respuesta = Http::acceptJson()->timeout(30)->get("{$base}/{$version}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $secreto,
            'fb_exchange_token' => $token,
        ]);

        if ($respuesta->failed() || blank($respuesta->json('access_token'))) {
            $mensaje = $respuesta->json('error.message') ?: "HTTP {$respuesta->status()}";

            throw new \RuntimeException("Meta no extendió el token: {$mensaje}");
        }

        $segundos = (int) $respuesta->json('expires_in');

        return [
            'token' => $respuesta->json('access_token'),
            'vence' => $segundos > 0 ? now()->addSeconds($segundos) : $this->vencimiento($respuesta->json('access_token')),
        ];
    }

    /** Cuándo vence un token según Meta (debug_token), o null si no se sabe o no vence. */
    public function vencimiento(string $token): ?\Illuminate\Support\Carbon
    {
        $appId = $this->integracion->credencial('app_id');
        $secreto = $this->integracion->credencial('app_secret');

        if (blank($appId) || blank($secreto)) {
            return null;
        }

        $base = rtrim($this->integracion->credencial('graph_url') ?: self::URL_POR_DEFECTO, '/');
        $version = trim($this->integracion->credencial('graph_version') ?: self::VERSION_POR_DEFECTO, '/');

        $respuesta = Http::acceptJson()->timeout(30)->get("{$base}/{$version}/debug_token", [
            'input_token' => $token,
            'access_token' => "{$appId}|{$secreto}",
        ]);

        $expira = (int) $respuesta->json('data.expires_at');

        return $expira > 0 ? \Illuminate\Support\Carbon::createFromTimestamp($expira) : null;
    }

    /** ¿El token guardado está vencido o vence en los próximos $dias días? */
    public function tokenPorVencer(int $dias = 10): ?array
    {
        $vence = $this->integracion->credencial('token_vence');

        if (! $vence) {
            return null;
        }

        $fecha = \Illuminate\Support\Carbon::parse($vence);

        return $fecha->lte(now()->addDays($dias))
            ? ['vence' => $fecha, 'vencido' => $fecha->isPast(), 'dias' => (int) floor(now()->diffInDays($fecha, false))]
            : null;
    }
}
