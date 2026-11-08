<?php

namespace App\Http\Controllers;

use App\Models\GastoCampana;
use App\Services\Territorio\PresupuestoCampana;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Storage;

/**
 * Soporte de un gasto (disco privado) e informe CSV de gastos por categoría de
 * Cuentas Claras. Los dos son del candidato del contexto: el gasto de otro
 * candidato da 404.
 */
class PresupuestoController extends Controller
{
    public function soporte(GastoCampana $gasto)
    {
        abort_unless(auth()->user()->can('presupuesto.ver'), 403);
        $disco = Storage::disk(GastoCampana::DISCO);
        abort_unless($gasto->soporte_ruta && $disco->exists($gasto->soporte_ruta), 404);

        return $disco->response($gasto->soporte_ruta, $gasto->soporte_nombre, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportar(PresupuestoCampana $cuentas)
    {
        abort_unless(auth()->user()->can('presupuesto.ver'), 403);
        $tenant = app(TenantContext::class)->tenant();
        abort_unless($tenant, 404);

        $nombre = 'gastos-'.$tenant->slug.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($cuentas) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF"); // BOM: Excel abre bien las tildes
            fputcsv($f, ['Fecha', 'Código', 'Categoría', 'Concepto', 'Proveedor o responsable', 'Documento', 'Valor', 'Estado', 'Soporte', 'Evento'], ';');
            foreach ($cuentas->filasInforme() as $fila) {
                // Un texto que empieza por = + - @ Excel lo ejecuta como fórmula
                fputcsv($f, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) && ! is_numeric($v) ? "'".$v : $v, $fila), ';');
            }
            fclose($f);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
