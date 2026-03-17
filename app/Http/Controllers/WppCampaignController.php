<?php

namespace App\Http\Controllers;

use App\Services\WppCampaignService;
use Illuminate\Http\Request;

class WppCampaignController extends Controller
{
    public function send(Request $request, WppCampaignService $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'message' => 'required|string',
            'contacts' => 'required|array|min:1',
            'contacts.*.phone' => 'required|string',
            'contacts.*.name' => 'nullable|string',
            'session' => 'nullable|string|max:255',
        ]);

        $campaign = $service->createCampaignAndQueue(
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