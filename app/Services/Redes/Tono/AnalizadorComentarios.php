<?php

namespace App\Services\Redes\Tono;

use App\Models\RedCuenta;
use App\Models\RedTono;
use App\Services\Redes\MetaGraphClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lee los comentarios recientes de una cuenta DEL CANDIDATO y guarda el tono.
 *
 * Privacidad (Ley 1581, la opinión política es dato sensible):
 * - a Meta solo se le piden id, texto y fecha del comentario, nunca el autor;
 * - el texto solo vive en memoria mientras se clasifica;
 * - en la base quedan conteos y temas por día (redes_tono) y una huella HMAC
 *   del id (redes_comentarios_vistos) para no clasificar dos veces.
 *
 * Requiere el token del candidato: Meta no deja leer comentarios ajenos.
 */
class AnalizadorComentarios
{
    // Comentarios por llamada a la IA: lote mediano para no cortar la salida
    private const LOTE = 80;

    // Publicaciones recientes a revisar y comentarios por publicación
    private const PUBLICACIONES = 25;

    private const COMENTARIOS_POR_PUBLICACION = 100;

    public function __construct(
        private MetaGraphClient $meta,
        private ClasificadorTono $clasificador,
    ) {}

    public function puedeAnalizar(RedCuenta $cuenta): bool
    {
        return ! $cuenta->esRival() && $cuenta->tieneAccesoPropio() && $this->clasificador->configurado();
    }

    /**
     * @return array{nuevos: int, a_favor: int, en_contra: int, neutro: int}
     */
    public function analizar(RedCuenta $cuenta): array
    {
        if ($cuenta->esRival()) {
            throw new \RuntimeException('Los comentarios solo se analizan en cuentas del candidato, no de rivales.');
        }

        if (! $cuenta->tieneAccesoPropio()) {
            throw new \RuntimeException('Para leer comentarios hace falta el token del candidato: Meta no deja leer comentarios de cuentas ajenas.');
        }

        if (! $this->clasificador->configurado()) {
            throw new \RuntimeException('Falta configurar la API key de Anthropic en Redes sociales.');
        }

        $comentarios = $this->nuevos($cuenta, $this->leerComentarios($cuenta));

        $conteo = ['nuevos' => count($comentarios), 'a_favor' => 0, 'en_contra' => 0, 'neutro' => 0];

        if ($comentarios === []) {
            return $conteo;
        }

        $tenant = $cuenta->tenant;
        $postulacion = $tenant?->cargo ? $tenant->circunscripcionTexto() : 'candidato';

        foreach (array_chunk($comentarios, self::LOTE, true) as $lote) {
            $r = $this->clasificador->clasificar($tenant?->nombre ?? 'el candidato', $postulacion, array_values($lote));

            foreach ($r['tonos'] as $tono) {
                $conteo[$tono]++;
            }

            // Guardar lote por lote: si un lote falla, lo ya clasificado queda
            $this->guardar($cuenta, $r['tonos'], $r['temas']);
            $this->marcarVistos($cuenta, array_keys($lote));
        }

        return $conteo;
    }

    /**
     * @return array<string, string> id del comentario => texto
     */
    private function leerComentarios(RedCuenta $cuenta): array
    {
        $desde = now()->subDays(7);
        $comentarios = [];

        if ($cuenta->red === RedCuenta::INSTAGRAM) {
            if (! $cuenta->external_id) {
                throw new \RuntimeException('Falta el ID de la cuenta de Instagram en Meta (se llena al sincronizar).');
            }

            $medios = $this->meta->get("{$cuenta->external_id}/media", [
                'fields' => 'id,timestamp',
                'limit' => self::PUBLICACIONES,
            ], $cuenta->access_token);

            foreach ($medios['data'] ?? [] as $medio) {
                if (! isset($medio['timestamp']) || Carbon::parse($medio['timestamp'])->lt($desde)) {
                    continue;
                }

                // Sin el campo username/from: el autor nunca sale de Meta
                $r = $this->meta->get("{$medio['id']}/comments", [
                    'fields' => 'id,text',
                    'limit' => self::COMENTARIOS_POR_PUBLICACION,
                ], $cuenta->access_token);

                foreach ($r['data'] ?? [] as $c) {
                    if (filled($c['text'] ?? null)) {
                        $comentarios[$c['id']] = mb_substr($c['text'], 0, 1000);
                    }
                }
            }

            return $comentarios;
        }

        // Facebook: comentarios de las publicaciones de la página
        $r = $this->meta->get(($cuenta->external_id ?: $cuenta->usuario).'/posts', [
            'fields' => 'id,created_time,comments.limit('.self::COMENTARIOS_POR_PUBLICACION.'){id,message}',
            'limit' => self::PUBLICACIONES,
        ], $cuenta->access_token);

        foreach ($r['data'] ?? [] as $post) {
            if (! isset($post['created_time']) || Carbon::parse($post['created_time'])->lt($desde)) {
                continue;
            }

            foreach ($post['comments']['data'] ?? [] as $c) {
                if (filled($c['message'] ?? null)) {
                    $comentarios[$c['id']] = mb_substr($c['message'], 0, 1000);
                }
            }
        }

        return $comentarios;
    }

    private function huella(string $comentarioId): string
    {
        return hash_hmac('sha256', $comentarioId, (string) config('app.key'));
    }

    /**
     * Quita los ya clasificados antes.
     *
     * @param  array<string, string>  $comentarios
     * @return array<string, string>
     */
    private function nuevos(RedCuenta $cuenta, array $comentarios): array
    {
        if ($comentarios === []) {
            return [];
        }

        $huellas = [];
        foreach (array_keys($comentarios) as $id) {
            $huellas[$this->huella((string) $id)] = $id;
        }

        $vistas = DB::table('redes_comentarios_vistos')
            ->where('red_cuenta_id', $cuenta->id)
            ->whereIn('huella', array_keys($huellas))
            ->pluck('huella')
            ->all();

        foreach ($vistas as $h) {
            unset($comentarios[$huellas[$h]]);
        }

        return $comentarios;
    }

    /** @param  list<string|int>  $ids */
    private function marcarVistos(RedCuenta $cuenta, array $ids): void
    {
        $ahora = now();

        DB::table('redes_comentarios_vistos')->insertOrIgnore(array_map(fn ($id) => [
            'red_cuenta_id' => $cuenta->id,
            'huella' => $this->huella((string) $id),
            'created_at' => $ahora,
        ], $ids));
    }

    /**
     * Suma el lote al conteo del día.
     *
     * @param  list<string>  $tonos
     * @param  list<string>  $temas
     */
    private function guardar(RedCuenta $cuenta, array $tonos, array $temas): void
    {
        $hoy = Carbon::today();

        // whereDate y no where('fecha', …): sqlite guarda la fecha con hora
        $fila = RedTono::query()
            ->withoutGlobalScope('tenant')
            ->where('red_cuenta_id', $cuenta->id)
            ->whereDate('fecha', $hoy)
            ->first() ?? new RedTono(['red_cuenta_id' => $cuenta->id, 'fecha' => $hoy]);

        // Sin usuario (comando) no hay tenant en contexto: va explícito
        $fila->tenant_id = $cuenta->tenant_id;

        $porTono = array_count_values($tonos);
        $fila->a_favor = (int) $fila->a_favor + ($porTono[ClasificadorTono::A_FAVOR] ?? 0);
        $fila->en_contra = (int) $fila->en_contra + ($porTono[ClasificadorTono::EN_CONTRA] ?? 0);
        $fila->neutro = (int) $fila->neutro + ($porTono[ClasificadorTono::NEUTRO] ?? 0);

        $acumulados = $fila->temas ?? [];
        foreach ($temas as $tema) {
            $acumulados[$tema] = ($acumulados[$tema] ?? 0) + 1;
        }
        $fila->temas = $acumulados;

        $fila->save();
    }
}
