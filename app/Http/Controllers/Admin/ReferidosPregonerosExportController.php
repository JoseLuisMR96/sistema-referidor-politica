<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferidorPregonero;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferidosPregonerosExportController extends Controller
{
    public function csv(ReferidorPregonero $referidor): StreamedResponse
    {
        $filename = 'referidos_' . $referidor->cedula . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($referidor) {
            $out = fopen('php://output', 'w');

            // Excel-friendly (UTF-8 BOM)
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, ['referidor_id', 'referidor_nombre', 'referidor_cedula', 'referido_id', 'nombre', 'cedula', 'puesto_votacion', 'created_at']);

            $referidor->referidos()
                ->select(['id', 'referidor_pregonero_id', 'nombre', 'cedula', 'puesto_votacion', 'created_at'])
                ->orderBy('created_at', 'desc')
                ->chunk(1000, function ($rows) use ($out, $referidor) {
                    foreach ($rows as $r) {
                        fputcsv($out, [
                            $referidor->id,
                            $referidor->nombre,
                            $referidor->cedula,
                            $r->id,
                            $r->nombre,
                            $r->cedula,
                            $r->puesto_votacion,
                            optional($r->created_at)->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}