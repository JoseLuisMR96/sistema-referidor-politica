<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\WppCampaignService;
use Illuminate\Http\Request;

class WppCampaignController extends Controller
{
    public function send(Request $request, WppCampaignService $service)
    {
        // Endpoint público de la API: sin token configurado no se autoriza a
        // nadie, igual que WhatsAppWorkerController.
        $expected = (string) config('services.wppconnect.api_token');
        $token = (string) $request->bearerToken();

        abort_if($expected === '', 401, 'Unauthorized');
        abort_unless(hash_equals($expected, $token), 401, 'Unauthorized');

        $request->validate([
            'name' => 'required|string|max:255',
            'message' => 'required|string',
            'contacts' => 'required|array|min:1',
            'contacts.*.phone' => 'required|string',
            'contacts.*.name' => 'nullable|string',
            'session' => 'nullable|string|max:255',
            // slug del tenant (candidato) al que pertenece la campaña
            'tenant' => 'required|string',
        ]);

        $tenant = Tenant::where('slug', $request->input('tenant'))->where('activo', true)->first();

        if (! $tenant) {
            return response()->json(['ok' => false, 'error' => 'tenant_invalido'], 422);
        }

        $campaign = $service->createCampaignAndQueue(
            tenantId: $tenant->id,
            contacts: $request->input('contacts'),
            name: $request->input('name'),
            template: $request->input('message'),
            session: $request->input('session')
        );

        return response()->json([
            'ok' => true,
            'campaign_id' => $campaign->id,
            'total_contacts' => $campaign->total_contacts,
        ]);
    }
}