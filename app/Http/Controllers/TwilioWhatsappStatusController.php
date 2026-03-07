<?php

namespace App\Http\Controllers;

use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Twilio\Security\RequestValidator;

class TwilioWhatsappStatusController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('X-Twilio-Signature');
        $validator = new RequestValidator(config('services.twilio.token'));

        $url = $request->fullUrl();
        $params = $request->all();

        if (!$validator->validate($signature, $url, $params)) {
            return response('Invalid signature', 403);
        }

        $sid = $request->input('MessageSid');
        $status = $request->input('MessageStatus'); 
        $errorCode = $request->input('ErrorCode');
        $errorMessage = $request->input('ErrorMessage');

        $row = WhatsappMessage::query()->where('twilio_sid', $sid)->first();
        if (!$row) return response('ok', 200);

        $update = [
            'status' => $status,
            'error_code' => $errorCode ? (string)$errorCode : null,
            'error_message' => $errorMessage ?: null,
            'last_status_at' => now(),
            'raw_webhook' => $request->all(),
        ];

        if ($status === 'delivered' && !$row->delivered_at) {
            $update['delivered_at'] = now();
        }

        $row->update($update);

        $campaign = $row->campaign()->first();
        if ($campaign) {
            $campaign->total = $campaign->messages()->count();
            $campaign->delivered = $campaign->messages()->where('status', 'delivered')->count();
            $campaign->failed_count = $campaign->messages()->whereIn('status', ['failed','undelivered'])->count();
            $campaign->save();
        }

        return response('ok', 200);
    }
}
