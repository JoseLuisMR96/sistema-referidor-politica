<?php

namespace App\Services;

use App\Jobs\SendWppMessage;
use App\Models\WppCampaign;
use App\Models\WppContact;
use App\Models\WppMessage;

class WppCampaignService
{
    public function createCampaignAndQueue(
        array $contacts,
        string $name,
        string $template,
        ?string $session = null,
        ?string $imagePath = null
    ): WppCampaign {
        $session = $session ?: (string) config('services.wppconnect.default_session');

        $campaign = WppCampaign::create([
            'name' => $name,
            'message' => $template,
            'session' => $session,
            'image_path' => $imagePath,
            'started_at' => now(),
        ]);

        $delayMin = (int) config('services.wppconnect.delay_min_seconds', 10);
        $delayMax = (int) config('services.wppconnect.delay_max_seconds', 25);

        $currentDelay = 0;
        $count = 0;

        foreach ($contacts as $row) {
            $phone = $this->normalizePhone($row['phone'] ?? '');
            $contactName = trim((string) ($row['name'] ?? ''));

            if ($phone === '') {
                continue;
            }

            $contact = WppContact::firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => $contactName !== '' ? $contactName : null,
                    'opt_in' => true,
                ]
            );

            $messageText = str_replace('{name}', $contact->name ?: 'Cliente', $template);

            $message = WppMessage::create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'phone' => $phone,
                'message' => $messageText,
                'status' => 'pending',
            ]);

            SendWppMessage::dispatch($message->id)
                ->onQueue('wpp')
                ->delay(now()->addSeconds($currentDelay));

            $currentDelay += random_int($delayMin, $delayMax);
            $count++;
        }

        $campaign->update([
            'total_contacts' => $count,
        ]);

        return $campaign;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', trim($phone));

        if (preg_match('/^\d{10}$/', $phone)) {
            $phone = '57' . $phone;
        }

        return $phone;
    }
}