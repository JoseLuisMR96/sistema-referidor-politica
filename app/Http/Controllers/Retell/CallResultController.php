<?php

namespace App\Http\Controllers\Retell;

use App\Http\Controllers\Controller;
use App\Models\CallResult;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class CallResultController extends Controller
{
    public function store(Request $request)
    {
        $secret = $request->header('X-Retell-Secret');
        if (!$secret || !hash_equals((string) config('services.retell.secret'), (string) $secret)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        $call = $request->input('call', []);
        $args = $request->input('args', []);

        if (empty($args)) {
            $args = $payload;
        }

        $row = CallResult::create([
            'provider' => 'retell',
            'call_id' => $call['call_id'] ?? ($args['call_id'] ?? null),
            'agent_id' => $call['agent_id'] ?? ($args['agent_id'] ?? null),

            'status' => $call['call_status'] ?? ($args['status'] ?? null),
            
            'duration_sec' => data_get($call, 'call_cost.total_duration_seconds'),
            'name' => $args['name'] ?? null,
            'phone' => $args['phone'] ?? null,
            
            'senate_candidate' => $args['senate_candidate'] ?? null,
            'camara_candidate' => $args['camara_candidate'] ?? null,
            'condition_candidate_senate' => $args['condition_candidate_senate'] ?? null,
            'condition_candidate_camara' => $args['condition_candidate_camara'] ?? null,

            'raw_payload' => $payload,
        ]);

        return response()->json([
            'ok' => true,
            'id' => $row->id,
        ]);
    }
}
