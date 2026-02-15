<?php

namespace App\Livewire\Whatsapp;

use App\Jobs\SendWhatsappCampaignMessage;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class CampaignComposer extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $type = 'media'; // text|media|location
    public string $body = "Hola {name}, ";

    public bool $testMode = true;
    public string $testPhone = '';
    public bool $testOnlyFirstN = false;
    public int $testN = 5;

    public $mediaFile; // upload
    public ?string $location_label = null;
    public ?string $location_lat = null;
    public ?string $location_lng = null;

    public string $recipientsText = '';

    public function render()
    {
        return view('livewire.whatsapp.campaign-composer');
    }

    public function createAndSend()
    {
        $rules = [
            'name' => 'required|min:3',
            'type' => 'required|in:text,media,location',
            'body' => 'nullable|string',
            'recipientsText' => 'required|string|min:5',
            'mediaFile' => 'nullable|required_if:type,media|file|max:20480',
            'testMode' => 'boolean',
            'testPhone' => 'nullable|string',
            'testOnlyFirstN' => 'boolean',
            'testN' => 'nullable|integer|min:1|max:50',
        ];

        // Si está en modo prueba, exige el número de prueba (a menos que uses first N)
        if ($this->testMode && !$this->testOnlyFirstN) {
            $rules['testPhone'] = 'required|string|min:8';
        }

        $this->validate($rules);

        $campaign = new WhatsappCampaign();
        $campaign->name = $this->name . ($this->testMode ? ' (TEST)' : '');
        $campaign->type = $this->type;
        $campaign->body = $this->body;

        if ($this->type === 'location') {
            $campaign->location_label = $this->location_label;
            $campaign->location_lat = $this->location_lat ? (float) $this->location_lat : null;
            $campaign->location_lng = $this->location_lng ? (float) $this->location_lng : null;

            if ($campaign->location_lat && $campaign->location_lng) {
                $campaign->location_url = "https://www.google.com/maps?q={$campaign->location_lat},{$campaign->location_lng}";
            }
        }

        if ($this->type === 'media' && $this->mediaFile) {
            $path = $this->mediaFile->store('whatsapp-media', 'public');
            $campaign->media_path = $path;
            $campaign->media_mime = $this->mediaFile->getMimeType();
        }

        $campaign->status = 'queued';
        $campaign->save();

        $rows = $this->parseRecipients($this->recipientsText);

        // ✅ Si testOnlyFirstN, recorta lista
        if ($this->testMode && $this->testOnlyFirstN) {
            $rows = array_slice($rows, 0, (int) $this->testN);
        }

        // ✅ Si testMode y NO testOnlyFirstN, enviamos SOLO al número de prueba
        if ($this->testMode && !$this->testOnlyFirstN) {
            $to = $this->normalizeWhatsapp($this->testPhone);

            $msg = WhatsappMessage::create([
                'campaign_id' => $campaign->id,
                'to' => $to,
                'contact_name' => 'TEST',
                'status' => 'created',
            ]);

            SendWhatsappCampaignMessage::dispatch($msg->id)->onQueue('whatsapp');

            $campaign->update([
                'total' => 1,
                'status' => 'sending',
            ]);

            session()->flash('ok', '✅ Envío de PRUEBA en cola. Revisa tu WhatsApp y el dashboard de estados.');
            $this->reset(['name', 'type', 'body', 'mediaFile', 'location_label', 'location_lat', 'location_lng', 'recipientsText', 'testPhone', 'testOnlyFirstN', 'testN']);
            $this->testMode = true;
            return;
        }

        // ✅ Envío normal masivo (o first N si está activo)
        foreach ($rows as $r) {
            $to = $this->normalizeWhatsapp($r['phone']);
            $msg = WhatsappMessage::create([
                'campaign_id' => $campaign->id,
                'to' => $to,
                'contact_name' => $r['name'] ?? null,
                'status' => 'created',
            ]);

            SendWhatsappCampaignMessage::dispatch($msg->id)->onQueue('whatsapp');
        }

        $campaign->update([
            'total' => $campaign->messages()->count(),
            'status' => 'sending',
        ]);

        session()->flash('ok', '✅ Campaña en cola. La cola se encarga y tu dashboard se actualiza con estados.');
        $this->reset(['name', 'type', 'body', 'mediaFile', 'location_label', 'location_lat', 'location_lng', 'recipientsText', 'testPhone', 'testOnlyFirstN', 'testN']);
        $this->testMode = true;
    }


    private function parseRecipients(string $text): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($text));
        $out = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // phone|name
            $parts = explode('|', $line, 2);
            $phone = trim($parts[0]);
            $name  = isset($parts[1]) ? trim($parts[1]) : null;

            $out[] = ['phone' => $phone, 'name' => $name];
        }
        return $out;
    }

    private function normalizeWhatsapp(string $phone): string
    {
        $p = trim($phone);

        // Si ya viene con whatsapp:
        if (str_starts_with($p, 'whatsapp:')) return $p;

        // Acepta +57..., etc.
        return 'whatsapp:' . $p;
    }
}
