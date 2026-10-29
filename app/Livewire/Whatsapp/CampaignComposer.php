<?php

namespace App\Livewire\Whatsapp;

use App\Jobs\SendWhatsappCampaignMessage;
use App\Livewire\Concerns\RequiereTenant;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappMessage;
use App\Support\PhoneNumber;
use App\Support\TenantContext;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CampaignComposer extends Component
{
    use RequiereTenant;
    use WithFileUploads;

    // Siempre por template aprobado de Twilio: es lo único que WhatsApp
    // permite para iniciar una conversación (el mensaje libre solo vale dentro
    // de las 24 h siguientes a que la persona escriba).
    public string $name = '';

    public bool $testMode = true;
    public string $testPhone = '';
    public bool $testOnlyFirstN = false;
    public int $testN = 5;

    public string $recipientsSource = 'manual'; // manual|excel
    public $recipientsFile; // .xlsx upload
    public string $recipientsText = '';

    public ?string $messagingServiceSid = null; // opcional: si no, el del candidato o .env

    public ?string $contentSid = null;        // HX... (Twilio Content SID)
    public ?string $contentVarsJson = null;   // JSON de variables

    // Números descartados por inválidos en el último envío
    public int $invalidPhones = 0;



    public function mount(): void
    {
        abort_unless(auth()->user()->can('whatsapp.enviar'), 403);
    }

    public function createAndSend()
    {
        abort_unless(auth()->user()->can('whatsapp.enviar'), 403);

        if (! $this->exigirTenant()) {
            return;
        }

        $tenant = app(TenantContext::class)->tenant();

        $rules = [
            'recipientsSource' => 'required|in:manual,excel',

            'testMode' => 'boolean',
            'testPhone' => 'nullable|string',
            'testOnlyFirstN' => 'boolean',
            'testN' => 'nullable|integer|min:1|max:50',

            'name' => 'nullable|string|max:255',
            'messagingServiceSid' => 'nullable|string|min:10',
            'contentSid' => 'required|string|min:5', // HX...
            'contentVarsJson' => 'nullable|string',
        ];

        if ($this->recipientsSource === 'manual') {
            $rules['recipientsText'] = 'required|string|min:5';
        } else {
            $rules['recipientsFile'] = 'required|file|mimes:xlsx|max:5120'; // 5MB (ajústalo)
        }

        // Si está en modo prueba, exige número de prueba (a menos que uses first N)
        if ($this->testMode && !$this->testOnlyFirstN) {
            $rules['testPhone'] = 'required|string|min:8';
        }

        $this->validate($rules);

        $this->invalidPhones = 0;

        // Validar JSON variables del template si vienen
        if ($this->contentVarsJson) {
            json_decode($this->contentVarsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('contentVarsJson', 'JSON inválido.');
                return;
            }
        }

        // El número de prueba se valida antes de crear la campaña, para no
        // dejar campañas huérfanas si viene mal escrito.
        $testTo = null;
        if ($this->testMode && !$this->testOnlyFirstN) {
            $testTo = PhoneNumber::toWhatsApp($this->testPhone);

            if (! $testTo) {
                $this->addError('testPhone', 'Número de prueba inválido. Usa 10 dígitos (3001234567) o el formato +573001234567.');
                return;
            }
        }

        $rows = [];
        if (! $testTo) {
            $rows = $this->recipientsSource === 'excel'
                ? $this->parseRecipientsFromExcel($this->recipientsFile)
                : $this->parseRecipients($this->recipientsText);

            if ($this->testMode && $this->testOnlyFirstN) {
                $rows = array_slice($rows, 0, (int) $this->testN);
            }

            if (empty($rows)) {
                $this->addError(
                    $this->recipientsSource === 'excel' ? 'recipientsFile' : 'recipientsText',
                    'No hay ningún número válido para enviar.'
                );
                return;
            }
        }

        // Crear campaña
        $campaign = new WhatsappCampaign();
        $campaign->status = 'queued';

        $campaign->name = ($this->name ?: 'Campaña Twilio').($this->testMode ? ' (TEST)' : '');
        $campaign->type = 'template';
        $campaign->content_sid = trim((string) $this->contentSid); // HX...
        $campaign->content_variables = $this->contentVarsJson ?: null;

        // Cada candidato envía por su propio Messaging Service (su número) si
        // no se indica otro aquí. El job usa este valor.
        $campaign->messaging_service_sid = $this->messagingServiceSid ?: $tenant->messagingServiceSid();

        $campaign->save();

        // Si testMode y NO testOnlyFirstN, enviamos SOLO al número de prueba.
        // OJO con los estados: campaign_messages.status es un ENUM en MySQL
        // (sin 'created') y campaigns.status debe ser un caso de
        // CampaignStatusEnum (sin 'active').
        if ($testTo) {
            $msg = WhatsappMessage::create([
                'campaign_id' => $campaign->id,
                'tenant_id' => $campaign->tenant_id,
                'to' => $testTo,
                'contact_name' => 'TEST',
                'status' => 'pending',
            ]);

            SendWhatsappCampaignMessage::dispatch($msg->id)->onQueue('whatsapp');

            $campaign->update(['total' => 1, 'status' => 'sending']);

            session()->flash('ok', '✅ Envío de PRUEBA en cola. Revisa WhatsApp y el dashboard de estados.');
            $this->resetForm();
            return;
        }

        // Envío normal (o primeros N). parseRecipients* ya dejó solo números
        // válidos en formato whatsapp:+57...
        foreach ($rows as $r) {
            $to = $r['phone'];

            $msg = WhatsappMessage::create([
                'campaign_id' => $campaign->id,
                'tenant_id' => $campaign->tenant_id,
                'to' => $to,
                'contact_name' => $r['name'] ?? null,
                'status' => 'pending',
            ]);

            SendWhatsappCampaignMessage::dispatch($msg->id)->onQueue('whatsapp');
        }

        $campaign->update(['total' => $campaign->messages()->count(), 'status' => 'sending']);

        $mensaje = '✅ Campaña en cola. La cola se encarga y tu dashboard se actualiza con estados.';
        if ($this->invalidPhones > 0) {
            $mensaje .= " Se omitieron {$this->invalidPhones} números inválidos.";
        }

        session()->flash('ok', $mensaje);
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'name',
            'recipientsText',
            'testPhone',
            'testOnlyFirstN',
            'testN',
            'messagingServiceSid',
            'contentSid',
            'contentVarsJson',
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

            $to = PhoneNumber::toWhatsApp($phone);
            if (! $to) {
                $this->invalidPhones++;
                continue;
            }

            $out[] = ['phone' => $to, 'name' => $name];
        }
        return $out;
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

            $to = PhoneNumber::toWhatsApp($rawPhone);
            if (! $to) {
                $this->invalidPhones++;
                continue;
            }

            $out[] = [
                'phone' => $to,
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


    public function render()
    {
        return view('livewire.whatsapp.campaign-composer');
    }
}
