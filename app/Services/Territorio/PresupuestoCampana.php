<?php

namespace App\Services\Territorio;

use App\Models\EventoCampana;
use App\Models\GastoCampana;
use App\Models\Presupuesto;
use App\Models\PresupuestoRubro;

/**
 * Cuentas del candidato del contexto: tope legal, presupuesto por categoría
 * de Cuentas Claras, lo gastado y lo comprometido.
 *
 * - Gastado = gastos registrados + eventos ya legalizados (monto gastado).
 * - Comprometido = dinero asignado a eventos que aún no se legaliza.
 * - Un evento con gastos ligados no se cuenta aparte (se contaría dos veces).
 * - Los eventos cancelados no cuentan.
 */
class PresupuestoCampana
{
    /** Avisos al acercarse al tope: % => nivel */
    public const UMBRALES = [100 => 'rojo', 95 => 'naranja', 80 => 'amarillo'];

    public const DIAS_LEGALIZAR = 15;

    public function resumen(): array
    {
        $presupuesto = Presupuesto::first();
        $tope = $presupuesto?->tope_legal !== null ? (float) $presupuesto->tope_legal : null;
        $rubros = PresupuestoRubro::query()->pluck('monto', 'categoria')->map(fn ($m) => (float) $m);

        $gastos = GastoCampana::query()->get(['id', 'categoria', 'valor', 'estado', 'soporte_ruta', 'evento_id', 'fecha']);
        $conGastos = $gastos->pluck('evento_id')->filter()->unique()->all();
        $eventos = EventoCampana::query()->where('estado', '!=', 'cancelado')
            ->where(fn ($q) => $q->where('monto_asignado', '>', 0)->orWhereNotNull('monto_gastado'))
            ->whereNotIn('id', $conGastos)
            ->get(['id', 'titulo', 'fecha', 'monto_asignado', 'monto_gastado', 'responsable_id']);

        $categorias = [];
        foreach (GastoCampana::CATEGORIAS as $codigo => $nombre) {
            $g = $gastos->where('categoria', $codigo);
            $categorias[$codigo] = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'presupuesto' => $rubros[$codigo] ?? 0.0,
                'gastado' => (float) $g->sum('valor'),
                'por_pagar' => (float) $g->where('estado', 'por_pagar')->sum('valor'),
                'comprometido' => 0.0,
            ];
        }

        // Eventos: lo legalizado es gasto; lo asignado sin legalizar, compromiso
        $legalizados = $eventos->whereNotNull('monto_gastado');
        $sinLegalizar = $eventos->whereNull('monto_gastado');
        $cat = GastoCampana::CATEGORIA_EVENTOS;
        $categorias[$cat]['gastado'] += (float) $legalizados->sum('monto_gastado');
        $categorias[$cat]['comprometido'] += (float) $sinLegalizar->sum('monto_asignado');

        foreach ($categorias as &$c) {
            $usado = $c['gastado'] + $c['comprometido'];
            $c['usado'] = $usado;
            $c['pct'] = $c['presupuesto'] > 0 ? round($usado * 100 / $c['presupuesto'], 1) : null;
            $c['excedido'] = $c['presupuesto'] > 0 && $usado > $c['presupuesto'];
        }
        unset($c);

        $gastado = array_sum(array_column($categorias, 'gastado'));
        $comprometido = array_sum(array_column($categorias, 'comprometido'));
        $presupuestado = array_sum(array_column($categorias, 'presupuesto'));
        $pctTope = $tope ? round(($gastado + $comprometido) * 100 / $tope, 1) : null;

        $nivel = null;
        foreach (self::UMBRALES as $umbral => $n) {
            if ($pctTope !== null && $pctTope >= $umbral) {
                $nivel = $n;
                break;
            }
        }

        // Eventos con dinero asignado que no se han legalizado en 15 días
        $vencidos = $sinLegalizar->filter(fn ($e) => $e->fecha->lt(now()->subDays(self::DIAS_LEGALIZAR)));

        $alertas = [];
        if ($tope === null) {
            $alertas[] = ['amarillo', 'No hay tope legal registrado: escríbelo en "Editar presupuesto" (lo fija el CNE para la circunscripción).'];
        } elseif ($nivel) {
            $alertas[] = [$nivel, $pctTope >= 100
                ? "Se pasó del tope legal: va en {$pctTope}%. Revisa con el contador antes de seguir gastando."
                : "Va en {$pctTope}% del tope legal de gastos."];
        }
        if ($tope && $presupuestado > $tope) {
            $alertas[] = ['naranja', 'El presupuesto por categorías suma más que el tope legal.'];
        }
        foreach ($categorias as $c) {
            if ($c['excedido']) {
                $alertas[] = ['amarillo', "{$c['nombre']}: usado {$c['pct']}% de lo presupuestado."];
            }
        }
        if ($vencidos->isNotEmpty()) {
            $alertas[] = ['amarillo', $vencidos->count().' evento(s) con dinero asignado llevan más de '.self::DIAS_LEGALIZAR
                .' días sin legalizar ($ '.number_format($vencidos->sum('monto_asignado'), 0, ',', '.').').'];
        }
        if ($sinSoporte = $gastos->whereNull('soporte_ruta')->count()) {
            $alertas[] = ['amarillo', "{$sinSoporte} gasto(s) sin soporte: Cuentas Claras pide la factura o cuenta de cobro."];
        }

        return [
            'tope' => $tope,
            'tope_fuente' => $presupuesto?->tope_fuente,
            'presupuestado' => $presupuestado,
            'gastado' => $gastado,
            'comprometido' => $comprometido,
            'disponible' => $tope !== null ? $tope - $gastado - $comprometido : null,
            'pct_tope' => $pctTope,
            'nivel' => $nivel,
            'categorias' => $categorias,
            'alertas' => $alertas,
            'eventos_sin_legalizar' => $sinLegalizar->sortBy('fecha')->values(),
            'por_mes' => $this->porMes($gastos, $legalizados),
        ];
    }

    /** Gasto por mes (gastos + eventos legalizados), para la gráfica de ritmo. */
    private function porMes($gastos, $legalizados): array
    {
        $meses = [];
        foreach ($gastos as $g) {
            $meses[$g->fecha->format('Y-m')] = ($meses[$g->fecha->format('Y-m')] ?? 0) + (float) $g->valor;
        }
        foreach ($legalizados as $e) {
            $meses[$e->fecha->format('Y-m')] = ($meses[$e->fecha->format('Y-m')] ?? 0) + (float) $e->monto_gastado;
        }
        ksort($meses);

        return $meses;
    }

    /**
     * Filas para el informe (CSV): cada gasto y cada evento legalizado sin
     * gastos ligados, con su categoría de Cuentas Claras.
     */
    public function filasInforme(): array
    {
        $filas = [];
        foreach (GastoCampana::query()->with('evento:id,titulo')->orderBy('fecha')->get() as $g) {
            $filas[] = [
                $g->fecha->format('Y-m-d'), $g->categoria, $g->categoriaTexto(), $g->concepto,
                (string) $g->proveedor, (string) $g->documento, number_format((float) $g->valor, 2, '.', ''),
                GastoCampana::ESTADOS[$g->estado] ?? $g->estado, $g->soporte_ruta ? 'Sí' : 'No', $g->evento?->titulo ?? '',
            ];
        }

        $conGastos = GastoCampana::query()->whereNotNull('evento_id')->pluck('evento_id')->unique()->all();
        foreach (EventoCampana::query()->where('estado', '!=', 'cancelado')->whereNotNull('monto_gastado')
            ->whereNotIn('id', $conGastos)->with('responsable:id,nombre')->orderBy('fecha')->get() as $e) {
            $filas[] = [
                $e->fecha->format('Y-m-d'), GastoCampana::CATEGORIA_EVENTOS, GastoCampana::CATEGORIAS[GastoCampana::CATEGORIA_EVENTOS],
                'Evento: '.$e->titulo, (string) $e->responsableTexto(), '', number_format((float) $e->monto_gastado, 2, '.', ''),
                'Legalizado', $e->evidencias()->where('tipo', 'documento')->exists() ? 'Sí' : 'No', $e->titulo,
            ];
        }
        usort($filas, fn ($a, $b) => strcmp($a[0], $b[0]));

        return $filas;
    }
}
