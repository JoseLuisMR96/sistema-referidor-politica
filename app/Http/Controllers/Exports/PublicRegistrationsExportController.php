<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Models\PublicRegistration;
use App\Models\Referrer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicRegistrationsExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        // Permiso general (ajústalo a tu esquema)
        abort_unless($request->user()->can('registros.exportar'), 403);

        // Filtros
        $referrerId = $request->integer('referrer_id');
        $from = $request->string('from')->toString(); // YYYY-MM-DD opcional
        $to   = $request->string('to')->toString();   // YYYY-MM-DD opcional

        $query = PublicRegistration::query()
            ->with(['referrer:id,name,code'])
            ->when($referrerId, fn ($q) => $q->where('referrer_id', $referrerId))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at');

        $refName = 'todos';
        if ($referrerId) {
            $ref = Referrer::select('id','name','code')->find($referrerId);
            $refName = $ref ? ($ref->code . '_' . str($ref->name)->slug('_')) : 'referidor';
        }

        $fileName = 'registros_' . $refName . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM para Excel (evita caracteres raros)
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados
            fputcsv($out, [
                'ID',
                'Fecha Registro',
                'Nombre Completo',
                'Tipo Documento',
                'Número Documento',
                'Edad',
                'Género',
                'Municipio Residencia',
                'Municipio Votación',
                'Teléfono',
                'Estado',
                'Código Referido Usado',
                'Referidor',
                'Código Referidor',
            ], ';');

            $query->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        optional($r->created_at)->format('Y-m-d H:i:s'),
                        $r->full_name,
                        $r->document_type,
                        $r->document_number,
                        $r->age,
                        $r->gender,
                        $r->residence_municipality,
                        $r->voting_municipality,
                        $r->phone,
                        $r->status,
                        $r->ref_code_used,
                        optional($r->referrer)->name,
                        optional($r->referrer)->code,
                    ], ';');
                }
            });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
