<?php

namespace App\Livewire\Whatsapp;

use App\Jobs\SendWhatsappCampaignMessage;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    public string $recipientsSource = 'manual'; // manual|excel
    public $recipientsFile; // .xlsx upload
    public string $recipientsText = '';

    public bool $useTwilioCampaign = false;
    public string $twilioMode = 'custom'; // custom | template
    public ?string $messagingServiceSid = null; // opcional (si quieres override del .env)
    public ?string $templateName = null; // opcional
    public ?string $templateVarsJson = null; // opcional

    public ?string $contentSid = null;        // HX... (Twilio Content SID)
    public ?string $contentVarsJson = null;   // JSON de variables



    public function createAndSend()
    {
        $usingTemplate = (bool) $this->useTwilioCampaign;

        $rules = [
            'recipientsSource' => 'required|in:manual,excel',

            'testMode' => 'boolean',
            'testPhone' => 'nullable|string',
            'testOnlyFirstN' => 'boolean',
            'testN' => 'nullable|integer|min:1|max:50',

            'useTwilioCampaign' => 'boolean',
            'messagingServiceSid' => 'nullable|string|min:10',
        ];

        if ($this->recipientsSource === 'manual') {
            $rules['recipientsText'] = 'required|string|min:5';
        } else {
            $rules['recipientsFile'] = 'required|file|mimes:xlsx|max:5120'; // 5MB (ajústalo)
        }

        // Reglas SOLO si NO usa template
        if (!$usingTemplate) {
            $rules['name'] = 'required|min:3';
            $rules['type'] = 'required|in:text,media,location';
            $rules['body'] = 'nullable|string';

            $rules['mediaFile'] = $this->type === 'media'
                ? 'required|file|max:20480'
                : 'nullable|file|max:20480';
        }

        // Reglas SOLO si usa template (inicia conversación)
        if ($usingTemplate) {
            $rules['contentSid'] = 'required|string|min:5'; // HX...
            $rules['contentVarsJson'] = 'nullable|string';
        }

        // Si está en modo prueba, exige número de prueba (a menos que uses first N)
        if ($this->testMode && !$this->testOnlyFirstN) {
            $rules['testPhone'] = 'required|string|min:8';
        }

        $this->validate($rules);

        // Validar JSON variables del template si vienen
        if ($usingTemplate && $this->contentVarsJson) {
            json_decode($this->contentVarsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('contentVarsJson', 'JSON inválido.');
                return;
            }
        }

        // Crear campaña
        $campaign = new WhatsappCampaign();
        $campaign->status = 'queued';

        if ($usingTemplate) {
            // Campaña Twilio (template)
            $campaign->name = ($this->name ?: 'Campaña Twilio') . ($this->testMode ? ' (TEST)' : '');
            $campaign->type = 'template';

            $campaign->messaging_service_sid = $this->messagingServiceSid ?: config('services.twilio.messaging_service_sid');
            $campaign->content_sid = $this->contentSid; // HX...
            $campaign->content_variables = $this->contentVarsJson ?: null;

            // No necesita body/media/location
            $campaign->body = null;
            $campaign->media_path = null;
            $campaign->media_mime = null;
            $campaign->location_label = null;
            $campaign->location_lat = null;
            $campaign->location_lng = null;
            $campaign->location_url = null;
        } else {
            // Campaña normal (mensaje libre)
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
        }

        $campaign->save();

        $rows = $this->recipientsSource === 'excel'
            ? $this->parseRecipientsFromExcel($this->recipientsFile)
            : $this->parseRecipients($this->recipientsText);

        if ($this->testMode && $this->testOnlyFirstN) {
            $rows = array_slice($rows, 0, (int) $this->testN);
        }

        // Si testMode y NO testOnlyFirstN, enviamos SOLO al número de prueba
        if ($this->testMode && !$this->testOnlyFirstN) {
            $to = $this->normalizeWhatsapp($this->testPhone);

            $msg = WhatsappMessage::create([
                'campaign_id' => $campaign->id,
                'to' => $to,
                'contact_name' => 'TEST',
                'status' => 'created',
            ]);

            SendWhatsappCampaignMessage::dispatch($msg->id)->onQueue('whatsapp');

            $campaign->update(['total' => 1, 'status' => 'active']);

            session()->flash('ok', '✅ Envío de PRUEBA en cola. Revisa WhatsApp y el dashboard de estados.');
            $this->resetForm();
            return;
        }

        // Envío normal (o primeros N)
        foreach ($rows as $r) {
            $to = $this->normalizeWhatsapp($r['phone']);

            $msg = WhatsappMessage::create([
                'campaign_id' => $campaign->id,
                'to' => $to,
                'contact_name' => $r['name'] ?? null,
                'status' => 'pending',
            ]);

            SendWhatsappCampaignMessage::dispatch($msg->id)->onQueue('whatsapp');
        }

        $campaign->update(['total' => $campaign->messages()->count(), 'status' => 'active']);

        session()->flash('ok', '✅ Campaña en cola. La cola se encarga y tu dashboard se actualiza con estados.');
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'name',
            'type',
            'body',
            'mediaFile',
            'location_label',
            'location_lat',
            'location_lng',
            'recipientsText',
            'testPhone',
            'testOnlyFirstN',
            'testN',
            'messagingServiceSid',
            'contentSid',
            'contentVarsJson',
            'useTwilioCampaign',
            'recipientsSource',
            'recipientsFile',
        ]);
        $this->testMode = true;
        $this->recipientsSource = 'manual';
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

    private function parseRecipientsFromExcel($file): array
    {
        // Livewire tmp path
        $path = $file->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        // a array (fila/columna)
        $data = $sheet->toArray(null, true, true, true); // keys A,B,C...

        if (count($data) < 2) {
            $this->addError('recipientsFile', 'El Excel está vacío o no tiene filas de datos.');
            return [];
        }

        // Encabezados en la primera fila
        $headersRow = array_shift($data);

        // normaliza headers: "Nombre", "CELULAR", "phone", etc.
        $map = [];
        foreach ($headersRow as $col => $h) {
            $key = $this->normalizeHeader((string)$h);
            if ($key !== '') $map[$key] = $col;
        }

        // busca columnas posibles
        $nameCol  = $map['nombre'] ?? $map['name'] ?? null;
        $phoneCol = $map['celular'] ?? $map['telefono'] ?? $map['movil'] ?? $map['phone'] ?? $map['cel'] ?? null;

        if (!$phoneCol) {
            $this->addError('recipientsFile', 'No encontré la columna "celular" (o phone/telefono).');
            return [];
        }
        if (!$nameCol) {
            // nombre es opcional, pero tú lo quieres para variables
            // lo dejamos opcional: si no viene, se envía vacío o "Cliente"
            $nameCol = null;
        }

        $out = [];
        foreach ($data as $row) {
            $rawPhone = isset($row[$phoneCol]) ? trim((string)$row[$phoneCol]) : '';
            if ($rawPhone === '') continue;

            $rawName = $nameCol ? trim((string)($row[$nameCol] ?? '')) : '';

            // construye
            $out[] = [
                'phone' => $this->normalizePhoneLoose($rawPhone),
                'name'  => $rawName !== '' ? $rawName : null,
            ];
        }

        if (empty($out)) {
            $this->addError('recipientsFile', 'No se encontraron registros válidos (celular vacío).');
        }

        return $out;
    }

    private function normalizeHeader(string $h): string
    {
        $h = trim(mb_strtolower($h));
        // quita acentos básicos
        $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        // deja letras/números
        $h = preg_replace('/[^a-z0-9_ ]/i', '', $h);
        $h = preg_replace('/\s+/', ' ', $h);
        return trim($h);
    }

    private function normalizePhoneLoose(string $p): string
    {
        $p = trim($p);

        // elimina espacios, guiones, paréntesis
        $p = preg_replace('/[^\d\+]/', '', $p);

        // si viene tipo 3142874901 (10 dígitos) => asumimos Colombia +57
        if (!str_starts_with($p, '+') && preg_match('/^\d{10}$/', $p)) {
            $p = '+57' . $p;
        }

        // si viene 573142... sin +, se lo ponemos
        if (!str_starts_with($p, '+') && preg_match('/^57\d{10}$/', $p)) {
            $p = '+' . $p;
        }

        return $p;
    }

    public function render()
    {
        return view('livewire.whatsapp.campaign-composer');
    }
}
