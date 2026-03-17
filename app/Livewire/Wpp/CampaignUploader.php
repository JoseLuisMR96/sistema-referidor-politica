<?php

namespace App\Livewire\Wpp;

use App\Services\WppCampaignService;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CampaignUploader extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $session = '';
    public string $message = 'Hola {name}, este es un mensaje de prueba.';
    public string $previewName = 'Jose Luis';

    public $file = null;
    public $image = null;

    public array $preview = [];

    public function mount(): void
    {
        $this->session = (string) config('services.wppconnect.default_session', 'cristian1');
    }

    public function previewExcel(): void
    {
        $this->validate([
            'file' => 'required|file|mimes:xlsx|max:10240',
        ]);

        $rows = $this->parseExcel($this->file);
        $this->preview = array_slice($rows, 0, 20);
    }

    public function save(WppCampaignService $service): void
    {
        $this->validate([
            'name' => 'required|string|min:3|max:255',
            'session' => 'required|string|max:255',
            'message' => 'required|string|min:3',
            'file' => 'required|file|mimes:xlsx|max:10240',
            'image' => 'nullable|image|max:2048',
        ]);

        $rows = $this->parseExcel($this->file);

        if (empty($rows)) {
            session()->flash('error', 'No se encontraron contactos válidos en el Excel.');
            return;
        }

        $imagePath = null;

        if ($this->image) {
            $imagePath = $this->image->store('wpp-campaigns', 'public');
        }

        $campaign = $service->createCampaignAndQueue(
            contacts: $rows,
            name: $this->name,
            template: $this->message,
            session: $this->session,
            imagePath: $imagePath
        );

        $this->reset([
            'name',
            'file',
            'image',
            'preview',
        ]);

        $this->session = (string) config('services.wppconnect.default_session', 'cristian1');
        $this->message = 'Hola {name}, este es un mensaje de prueba.';
        $this->previewName = 'Jose Luis';

        session()->flash(
            'ok',
            "Campaña #{$campaign->id} creada correctamente. Se pusieron {$campaign->total_contacts} mensajes en cola."
        );
    }

    private function parseExcel($file): array
    {
        $path = $file->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, true);

        if (count($data) < 2) {
            return [];
        }

        $headers = array_shift($data);

        $map = [];
        foreach ($headers as $col => $value) {
            $key = $this->normalizeHeader((string) $value);
            if ($key !== '') {
                $map[$key] = $col;
            }
        }

        $nameCol = $map['nombre'] ?? $map['name'] ?? null;
        $phoneCol = $map['celular'] ?? $map['telefono'] ?? $map['movil'] ?? $map['phone'] ?? null;

        if (!$phoneCol) {
            return [];
        }

        $rows = [];
        $seen = [];

        foreach ($data as $row) {
            $rawPhone = trim((string) ($row[$phoneCol] ?? ''));
            if ($rawPhone === '') {
                continue;
            }

            $phone = $this->normalizePhone($rawPhone);
            if ($phone === '') {
                continue;
            }

            if (isset($seen[$phone])) {
                continue;
            }

            $seen[$phone] = true;

            $name = $nameCol ? trim((string) ($row[$nameCol] ?? '')) : null;

            $rows[] = [
                'name' => $name !== '' ? $name : null,
                'phone' => $phone,
            ];
        }

        return $rows;
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9_ ]/i', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', trim($phone));

        if (preg_match('/^\d{10}$/', $phone)) {
            $phone = '57' . $phone;
        }

        if (!preg_match('/^57\d{10}$/', $phone)) {
            return '';
        }

        return $phone;
    }

    public function render()
    {
        return view('livewire.wpp.campaign-uploader');
    }
}