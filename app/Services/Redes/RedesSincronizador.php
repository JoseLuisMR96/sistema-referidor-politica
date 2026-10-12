<?php

namespace App\Services\Redes;

use App\Models\RedCuenta;
use App\Models\RedMetrica;
use Illuminate\Support\Carbon;

/**
 * Trae de Meta la foto del día de una cuenta y la guarda en redes_metricas.
 *
 * Qué se puede medir según el acceso:
 * - Instagram con token del candidato (cuenta profesional): sus datos directos.
 * - Instagram SIN acceso: Business Discovery con TU cuenta profesional. Solo
 *   sirve si la cuenta del candidato también es profesional (empresa o
 *   creador) y solo da datos públicos: seguidores, publicaciones y me gusta y
 *   comentarios de sus publicaciones. Nada de ubicación de seguidores.
 * - Facebook: Meta no da métricas de páginas ajenas; hace falta el token de
 *   la página (que el candidato te dé rol en ella).
 */
class RedesSincronizador
{
    private const CAMPOS_MEDIA = 'media.limit(50){like_count,comments_count,timestamp}';

    public function __construct(private MetaGraphClient $meta) {}

    public function sincronizar(RedCuenta $cuenta): RedMetrica
    {
        try {
            $resumen = match ($cuenta->red) {
                RedCuenta::INSTAGRAM => $this->instagram($cuenta),
                RedCuenta::FACEBOOK => $this->facebook($cuenta),
                default => throw new \RuntimeException("Red no soportada: {$cuenta->red}"),
            };
        } catch (\Throwable $e) {
            $cuenta->update(['ultimo_error' => mb_substr($e->getMessage(), 0, 1000)]);

            throw $e;
        }

        $hoy = Carbon::today();

        // whereDate y no where('fecha', …): sqlite guarda la fecha con hora
        $metrica = RedMetrica::query()
            ->withoutGlobalScope('tenant')
            ->where('red_cuenta_id', $cuenta->id)
            ->whereDate('fecha', $hoy)
            ->first() ?? new RedMetrica([
                'red_cuenta_id' => $cuenta->id,
                'fecha' => $hoy,
            ]);

        // Sin usuario (comando/cola) no hay tenant en contexto: va explícito
        $metrica->tenant_id = $cuenta->tenant_id;
        $metrica->fill($resumen)->save();

        $cuenta->update([
            'ultimo_sync_at' => now(),
            'ultimo_error' => null,
            'external_id' => $cuenta->external_id ?: ($resumen['datos']['id'] ?? null),
        ]);

        return $metrica;
    }

    /** @return array{seguidores: ?int, publicaciones: ?int, interacciones_7d: ?int, datos: array} */
    private function instagram(RedCuenta $cuenta): array
    {
        if ($cuenta->tieneAccesoPropio() && $cuenta->external_id) {
            $datos = $this->meta->get($cuenta->external_id, [
                'fields' => 'id,username,followers_count,media_count,'.self::CAMPOS_MEDIA,
            ], $cuenta->access_token);

            return $this->resumir($datos, 'propio');
        }

        $igPropio = $this->meta->integracion()->credencial('ig_user_id');

        if (blank($igPropio)) {
            throw new \RuntimeException(
                'Para medir Instagram sin acceso del candidato hace falta el ID de TU cuenta de Instagram profesional en la configuración de Meta.'
            );
        }

        $usuario = ltrim($cuenta->usuario, '@');

        $respuesta = $this->meta->get($igPropio, [
            'fields' => "business_discovery.username({$usuario}){id,username,followers_count,media_count,".self::CAMPOS_MEDIA.'}',
        ]);

        if (empty($respuesta['business_discovery'])) {
            throw new \RuntimeException("Instagram no devolvió datos de @{$usuario}. ¿Es una cuenta profesional (empresa o creador)?");
        }

        return $this->resumir($respuesta['business_discovery'], 'business_discovery');
    }

    /** @return array{seguidores: ?int, publicaciones: ?int, interacciones_7d: ?int, datos: array} */
    private function facebook(RedCuenta $cuenta): array
    {
        if (! $cuenta->tieneAccesoPropio()) {
            throw new \RuntimeException(
                'Facebook no da métricas de páginas ajenas: hace falta el token de la página (que el candidato te dé acceso).'
            );
        }

        $datos = $this->meta->get($cuenta->external_id ?: $cuenta->usuario, [
            'fields' => 'id,name,followers_count,fan_count,'
                .'posts.limit(50){created_time,reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0)}',
        ], $cuenta->access_token);

        $desde = now()->subDays(7);
        $interacciones = collect($datos['posts']['data'] ?? [])
            ->filter(fn ($p) => isset($p['created_time']) && Carbon::parse($p['created_time'])->gte($desde))
            ->sum(fn ($p) => (int) ($p['reactions']['summary']['total_count'] ?? 0)
                + (int) ($p['comments']['summary']['total_count'] ?? 0));

        return [
            'seguidores' => $datos['followers_count'] ?? $datos['fan_count'] ?? null,
            'publicaciones' => null,
            'interacciones_7d' => $interacciones,
            'datos' => [
                'id' => $datos['id'] ?? null,
                'nombre' => $datos['name'] ?? null,
                'fuente' => 'propio',
            ],
        ];
    }

    /** @return array{seguidores: ?int, publicaciones: ?int, interacciones_7d: ?int, datos: array} */
    private function resumir(array $datos, string $fuente): array
    {
        $desde = now()->subDays(7);

        $recientes = collect($datos['media']['data'] ?? [])
            ->filter(fn ($m) => isset($m['timestamp']) && Carbon::parse($m['timestamp'])->gte($desde));

        return [
            'seguidores' => $datos['followers_count'] ?? null,
            'publicaciones' => $datos['media_count'] ?? null,
            // like_count puede venir oculto por el dueño: cuenta como 0
            'interacciones_7d' => $recientes->sum(fn ($m) => (int) ($m['like_count'] ?? 0) + (int) ($m['comments_count'] ?? 0)),
            'datos' => [
                'id' => $datos['id'] ?? null,
                'usuario' => $datos['username'] ?? null,
                'publicaciones_7d' => $recientes->count(),
                'fuente' => $fuente,
            ],
        ];
    }
}
