<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferidorPregonero;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ReferidoresPregonerosMasivoExportController extends Controller
{
    public function xlsx(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()?->can('pregoneros_referidores.exportar_masivo'), 403);

        $fileName = 'referidores_pregoneros_masivo_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            /*
             |------------------------------------------------------------
             | Hoja 1: Referidores
             |------------------------------------------------------------
             */
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('Referidores');

            $headers1 = [
                'ID',
                'ID Único',
                'Nombre',
                'Cédula',
                'Puesto de votación',
                'Monto a pagar',
                'Pago realizado',
                'Hora pago',
                'Imagen pago',
                'Referidos (count)',
                'Creado en',
            ];

            $sheet1->fromArray($headers1, null, 'A1');
            $sheet1->freezePane('A2');
            $sheet1->getStyle('A1:K1')->getFont()->setBold(true);

            $row = 2;

            ReferidorPregonero::query()
                ->withCount('referidos')
                ->select([
                    'id',
                    'id_unico',
                    'nombre',
                    'cedula',
                    'puesto_votacion',
                    'monto_pagar',
                    'pago_realizado',
                    'hora_pago',
                    'imagen_pago',
                    'created_at',
                ])
                ->orderByDesc('id')
                ->chunk(200, function ($referidores) use (&$row, $sheet1) {
                    foreach ($referidores as $r) {
                        // Para que Excel no dañe cédulas grandes (las vuelve notación científica),
                        // forzamos texto en cédula e id_unico.
                        $sheet1->setCellValueExplicit("A{$row}", (string) $r->id, DataType::TYPE_STRING);
                        $sheet1->setCellValueExplicit("B{$row}", (string) $r->id_unico, DataType::TYPE_STRING);
                        $sheet1->setCellValue("C{$row}", $r->nombre);
                        $sheet1->setCellValueExplicit("D{$row}", (string) $r->cedula, DataType::TYPE_STRING);
                        $sheet1->setCellValue("E{$row}", $r->puesto_votacion);
                        $sheet1->setCellValue("F{$row}", $r->monto_pagar);

                        $pago = is_null($r->pago_realizado) ? '' : ($r->pago_realizado ? 'SI' : 'NO');
                        $sheet1->setCellValue("G{$row}", $pago);

                        $sheet1->setCellValue("H{$row}", optional($r->hora_pago)?->format('Y-m-d H:i:s') ?? '');
                        $sheet1->setCellValue("I{$row}", $r->imagen_pago ?? '');
                        $sheet1->setCellValue("J{$row}", (int) $r->referidos_count);
                        $sheet1->setCellValue("K{$row}", optional($r->created_at)?->format('Y-m-d H:i:s') ?? '');

                        $row++;
                    }
                });

            // Auto-size (ligero; si es MUY masivo, lo puedes quitar)
            foreach (range('A', 'K') as $col) {
                $sheet1->getColumnDimension($col)->setAutoSize(true);
            }

            /*
             |------------------------------------------------------------
             | Hoja 2: Referidos
             |------------------------------------------------------------
             */
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Referidos');

            $headers2 = [
                'ID Referidor',
                'ID Único Referidor',
                'Nombre Referidor',
                'Cédula Referidor',
                'ID Referido',
                'Nombre Referido',
                'Cédula Referido',
                'Puesto de votación',
                'Creado en',
            ];

            $sheet2->fromArray($headers2, null, 'A1');
            $sheet2->freezePane('A2');
            $sheet2->getStyle('A1:I1')->getFont()->setBold(true);

            $row2 = 2;

            ReferidorPregonero::query()
                ->with(['referidos:id,referidor_pregonero_id,nombre,cedula,puesto_votacion,created_at'])
                ->select(['id','id_unico','nombre','cedula'])
                ->orderByDesc('id')
                ->chunk(150, function ($referidores) use (&$row2, $sheet2) {
                    foreach ($referidores as $r) {
                        if ($r->referidos->isEmpty()) {
                            // Si quieres omitir referidores sin referidos, comenta este bloque.
                            $sheet2->setCellValueExplicit("A{$row2}", (string) $r->id, DataType::TYPE_STRING);
                            $sheet2->setCellValueExplicit("B{$row2}", (string) $r->id_unico, DataType::TYPE_STRING);
                            $sheet2->setCellValue("C{$row2}", $r->nombre);
                            $sheet2->setCellValueExplicit("D{$row2}", (string) $r->cedula, DataType::TYPE_STRING);
                            // columnas de referido vacías
                            $row2++;
                            continue;
                        }

                        foreach ($r->referidos as $ref) {
                            $sheet2->setCellValueExplicit("A{$row2}", (string) $r->id, DataType::TYPE_STRING);
                            $sheet2->setCellValueExplicit("B{$row2}", (string) $r->id_unico, DataType::TYPE_STRING);
                            $sheet2->setCellValue("C{$row2}", $r->nombre);
                            $sheet2->setCellValueExplicit("D{$row2}", (string) $r->cedula, DataType::TYPE_STRING);

                            $sheet2->setCellValueExplicit("E{$row2}", (string) $ref->id, DataType::TYPE_STRING);
                            $sheet2->setCellValue("F{$row2}", $ref->nombre);
                            $sheet2->setCellValueExplicit("G{$row2}", (string) $ref->cedula, DataType::TYPE_STRING);
                            $sheet2->setCellValue("H{$row2}", $ref->puesto_votacion);
                            $sheet2->setCellValue("I{$row2}", optional($ref->created_at)?->format('Y-m-d H:i:s') ?? '');

                            $row2++;
                        }
                    }
                });

            foreach (range('A', 'I') as $col) {
                $sheet2->getColumnDimension($col)->setAutoSize(true);
            }

            // Stream XLSX
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}