<?php
// app/Http/Controllers/WhatsAppWorkerController.php
namespace App\Http\Controllers;

use App\Models\WhatsAppOutbox;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppWorkerController extends Controller
{
    private function authOrFail(Request $request): void
    {
        $token = $request->query('token') ?? $request->input('token');
        abort_unless(hash_equals(config('services.whatsapp_worker.token'), (string)$token), 401, 'Unauthorized');
    }

    public function pull(Request $request)
    {
        $this->authOrFail($request);

        $worker = (string)($request->query('worker', 'pc-unknown'));
        $limit  = max(1, min(20, (int)$request->query('limit', 5)));

        $reserveSeconds = config('services.whatsapp_worker.reserve_seconds', 180);
        $maxAttempts = config('services.whatsapp_worker.max_attempts', 5);

        // 1) liberar reservas vencidas
        WhatsAppOutbox::query()
            ->where('status', 'RESERVED')
            ->where('reserved_at', '<', now()->subSeconds($reserveSeconds))
            ->update([
                'status' => 'PENDING',
                'reserved_by' => null,
                'reserved_at' => null,
            ]);

        // 2) reservar en transacción
        $jobs = DB::transaction(function () use ($worker, $limit, $maxAttempts) {
            // lockForUpdate evita carreras
            $rows = WhatsAppOutbox::query()
                ->where('status', 'PENDING')
                ->where('attempts', '<', $maxAttempts)
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($limit)
                ->get(['id', 'phone', 'message']);

            if ($rows->isEmpty()) return $rows;

            $ids = $rows->pluck('id')->all();

            WhatsAppOutbox::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'RESERVED',
                    'reserved_by' => $worker,
                    'reserved_at' => now(),
                ]);

            return $rows;
        });

        return response()->json([
            'ok' => true,
            'jobs' => $jobs,
        ]);
    }

    public function report(Request $request)
    {
        $this->authOrFail($request);

        $worker = (string)($request->input('worker', 'pc-unknown'));
        $id = (int)$request->input('id');
        $status = (string)$request->input('status'); // SENT o FAILED
        $error = $request->input('error');

        if ($id <= 0 || !in_array($status, ['SENT', 'FAILED'], true)) {
            return response()->json(['ok' => false, 'error' => 'bad_request'], 400);
        }

        if ($status === 'SENT') {
            $updated = WhatsAppOutbox::query()
                ->where('id', $id)
                ->where('status', 'RESERVED')
                ->where('reserved_by', $worker)
                ->update([
                    'status' => 'SENT',
                    'sent_at' => now(),
                    'last_error' => null,
                ]);

            return response()->json(['ok' => true, 'updated' => $updated]);
        }

        // FAILED: vuelve a PENDING y suma attempts
        $updated = WhatsAppOutbox::query()
            ->where('id', $id)
            ->where('status', 'RESERVED')
            ->where('reserved_by', $worker)
            ->update([
                'status' => 'PENDING',
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => is_string($error) ? $error : 'failed',
                'reserved_by' => null,
                'reserved_at' => null,
            ]);

        return response()->json(['ok' => true, 'updated' => $updated]);
    }
}
