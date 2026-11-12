<?php

namespace App\Services\Redes\Temas;

use App\Models\RedCuenta;
use App\Models\RedPublicacion;

/**
 * Pone el tema a las publicaciones de una cuenta que aún no lo tienen.
 * Corre en redes:sincronizar, después de guardar las publicaciones.
 */
class TemasPublicaciones
{
    // Publicaciones por llamada a la IA
    private const LOTE = 40;

    // Solo se clasifica lo reciente: es lo que muestra el dashboard
    private const DIAS = 60;

    public function __construct(private ClasificadorTemas $clasificador) {}

    public function puedeClasificar(): bool
    {
        return $this->clasificador->configurado();
    }

    /** @return int publicaciones clasificadas */
    public function clasificar(RedCuenta $cuenta): int
    {
        $pendientes = RedPublicacion::query()
            ->withoutGlobalScope('tenant')
            ->where('red_cuenta_id', $cuenta->id)
            ->whereNull('tema')
            ->whereNotNull('texto')
            ->where('publicada_at', '>=', now()->subDays(self::DIAS))
            ->orderByDesc('publicada_at')
            ->get(['id', 'texto']);

        $hechas = 0;
        foreach ($pendientes->chunk(self::LOTE) as $lote) {
            $lote = $lote->values();
            $temas = $this->clasificador->clasificar($lote->pluck('texto')->all());

            // Guardar lote por lote: si uno falla, lo ya clasificado queda
            foreach ($lote as $i => $p) {
                if (isset($temas[$i])) {
                    RedPublicacion::withoutGlobalScope('tenant')->whereKey($p->id)->update(['tema' => $temas[$i]]);
                    $hechas++;
                }
            }
        }

        return $hechas;
    }
}
