<?php

namespace App\Http\Controllers;

use App\Actions\WhatsApp\CaptureButtonResponseAction;
use App\DTOs\ButtonResponseDTO;
use App\Events\ButtonResponseCaptured;
use App\Models\WhatsappMessage;
use App\Models\WhatsAppCampaignResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TwilioWhatsappIncomingController extends Controller
{
    public function handle(Request $request)
    {
        $params = $request->all();
        
        Log::info('=== INCOMING MESSAGE FROM TWILIO ===', ['params' => $params]);

        // Extract message details
        $from = $params['From'] ?? null;
        $to = $params['To'] ?? null;
        $messageId = $params['MessageSid'] ?? null;
        $body = $params['Body'] ?? '';
        $originalMessageSid = $params['OriginalRepliedMessageSid'] ?? null;

        if (!$from || !$messageId) {
            Log::warning('⚠️ INCOMING MESSAGE: Missing From or MessageSid', $params);
            return response()->noContent();
        }

        // 🎯 Try to recognize if this is a button response
        $buttonId = $this->recognizeButtonResponse($body);

        if ($buttonId) {
            Log::info('🎯 BUTTON RESPONSE DETECTED FROM INCOMING MESSAGE', [
                'from' => $from,
                'button_id' => $buttonId,
                'body' => $body,
                'message_id' => $messageId,
                'original_message_sid' => $originalMessageSid,
            ]);

            // Find the original message using the Twilio SID
            $originalMessage = null;
            if ($originalMessageSid) {
                $originalMessage = WhatsappMessage::where('twilio_sid', $originalMessageSid)->first();
            }

            if (!$originalMessage) {
                Log::warning('⚠️ Original message not found in database', [
                    'original_sid' => $originalMessageSid,
                    'incoming_from' => $from,
                ]);
                return response()->noContent();
            }

            try {
                // Strip 'whatsapp:' prefix for storage (phone column is smaller)
                $phoneNumber = str_replace('whatsapp:', '', $from);

                // Extract MSID from the incoming webhook
                $messagingServiceId = $params['MessagingServiceSid'] ?? null;

                // Create DTO with actual data from database
                $dto = new ButtonResponseDTO(
                    campaignId: $originalMessage->campaign_id,
                    campaignMessageId: $originalMessage->id,
                    phone: $phoneNumber,
                    buttonId: $buttonId,
                    buttonText: $body,
                    responseTimestamp: Carbon::now(),
                    twiloSid: $messageId,
                    rawWebhook: $params,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                    messagingServiceId: $messagingServiceId,
                );

                $action = new CaptureButtonResponseAction();
                $action->execute($dto);

                // Retrieve the saved response from database
                $response = WhatsAppCampaignResponse::query()
                    ->where('campaign_id', $originalMessage->campaign_id)
                    ->where('button_id', $buttonId)
                    ->where('phone', $phoneNumber)
                    ->latest('id')
                    ->first();

                if ($response) {
                    // Dispatch event with the saved model
                    ButtonResponseCaptured::dispatch($response);

                    Log::info('✅ Button response captured and event dispatched', [
                        'button_id' => $buttonId,
                        'phone' => $phoneNumber,
                        'campaign_id' => $originalMessage->campaign_id,
                        'response_id' => $response->id,
                    ]);
                } else {
                    Log::warning('⚠️ Response saved but not found in database', [
                        'button_id' => $buttonId,
                        'phone' => $phoneNumber,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('❌ Failed to capture button response', [
                    'error' => $e->getMessage(),
                    'button_id' => $buttonId,
                    'phone' => $from,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } else {
            Log::info('📱 INCOMING MESSAGE (not a button)', [
                'from' => $from,
                'body' => $body,
                'message_id' => $messageId,
            ]);
        }

        return response()->noContent();
    }

    /**
     * Recognizes if the incoming message body matches a button response
     */
    private function recognizeButtonResponse(string $body): ?string
    {
        $body = strtolower(trim($body));

        $buttonMappings = [
            'paloma valencia' => 'palom',
            'paloma' => 'palom',
            'iván cepeda' => 'cepeda',
            'ivan cepeda' => 'cepeda',
            'cepeda' => 'cepeda',
            'otro candidato' => 'otro_candidato',
            'otro' => 'otro_candidato',
        ];

        foreach ($buttonMappings as $text => $buttonId) {
            if ($body === strtolower($text) || strpos($body, strtolower($text)) !== false) {
                return $buttonId;
            }
        }

        return null;
    }
}
