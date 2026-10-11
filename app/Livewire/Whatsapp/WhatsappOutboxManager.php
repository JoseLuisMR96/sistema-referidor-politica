<?php

namespace App\Livewire\Whatsapp;

use App\Livewire\Concerns\RequiereTenant;
use App\Models\WhatsAppOutbox;
use App\Support\PhoneNumber;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class WhatsappOutboxManager extends Component
{
    use RequiereTenant;
    use WithFileUploads;
    use WithPagination;

    public $xlsx;
    public string $defaultMessage = '';
    public bool $useRowMessage = false; // si true, usa message del XLSX; si no, usa defaultMessage

    public string $statusFilter = 'ALL'; // ALL, PENDING, RESERVED, SENT, FAILED, CANCELLED
    public string $search = '';
    public int $perPage = 25;

    public array $importSummary = [
        'inserted' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('whatsapp.enviar'), 403);
    }

    protected function rules(): array
    {
        return [
            'xlsx' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'], // 10MB
            'defaultMessage' => ['nullable', 'string', 'max:5000'],
            'useRowMessage' => ['boolean'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function import(): void
    {
        abort_unless(auth()->user()->can('whatsapp.enviar'), 403);

        if (! $this->exigirTenant()) {
            return;
        }

        // Insert masivo con DB::table: se salta el trait del modelo, así que
        // el tenant va a mano.
        $tenantId = app(TenantContext::class)->id();

        $this->validate();

        $this->importSummary = ['inserted' => 0, 'skipped' => 0, 'errors' => []];

        // Lee la primera hoja como array
        $sheets = Excel::toArray([], $this->xlsx);
        $rows = $sheets[0] ?? [];

        if (count($rows) < 2) {
            $this->importSummary['errors'][] = 'El archivo no tiene filas suficientes (asegura encabezados + datos).';
            return;
        }

        // Encabezados (fila 0)
        $headers = array_map(fn($h) => Str::of((string)$h)->trim()->lower()->replace(' ', '_')->toString(), $rows[0]);

        // Helper: obtener valor por nombre de columna
        $idx = function (string $key) use ($headers) {
            $pos = array_search($key, $headers, true);
            return $pos === false ? null : $pos;
        };

        $phoneIdx = $idx('phone');
        $msgIdx   = $idx('message');

        // Si tu XLSX usa nombres diferentes (telefono, celular, etc.), puedes agregar fallback aquí:
        if ($phoneIdx === null) {
            $phoneIdx = $idx('celular') ?? $idx('CELULAR') ??  $idx('celulares') ?? $idx('CELULARES') ?? $idx('celular_') ?? $idx('celular.') ?? $idx('telefono') ?? $idx('movil') ?? $idx('whatsapp');
        }
        if ($msgIdx === null) {
            $msgIdx = $idx('mensaje') ?? $idx('texto') ?? $idx('msg');
        }

        if ($phoneIdx === null) {
            $this->importSummary['errors'][] = 'No encuentro la columna del teléfono. Usa encabezado "phone" o "telefono".';
            return;
        }

        if (!$this->useRowMessage && trim($this->defaultMessage) === '') {
            $this->importSummary['errors'][] = 'Si desactivas "usar mensaje por fila", debes definir un mensaje por defecto.';
            return;
        }

        $now = now();

        // Inserta en lotes para no matar memoria/tiempo
        $batch = [];
        $batchSize = 300;

        // Itera filas desde la 2da (índice 1)
        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];

            $rawPhone = $r[$phoneIdx] ?? '';
            $phone = $this->normalizePhone((string)$rawPhone);

            if ($phone === null) {
                $this->importSummary['skipped']++;
                continue;
            }

            $rowMessage = $msgIdx !== null ? trim((string)($r[$msgIdx] ?? '')) : '';
            $message = $this->useRowMessage ? $rowMessage : trim($this->defaultMessage);

            // Si no hay mensaje, se salta (evita envíos vacíos)
            if ($message === '') {
                $this->importSummary['skipped']++;
                continue;
            }

            $payload = [
                'phone' => $phone,
                'message' => $message,
                'tenant_id' => $tenantId,
                'status' => 'PENDING',
                'attempts' => 0,
                'reserved_by' => null,
                'reserved_at' => null,
                'sent_at' => null,
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // whatsapp_outbox no tiene columna de nombre: antes se insertaba
            // 'name' y fallaban todos los lotes cuando el Excel la traía.

            $batch[] = $payload;

            if (count($batch) >= $batchSize) {
                $this->flushBatch($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->flushBatch($batch);
        }

        // Limpia input
        $this->reset('xlsx');
        $this->dispatch('imported');
    }

    private function flushBatch(array $batch): void
    {
        try {
            DB::table('whatsapp_outbox')->insert($batch);
            $this->importSummary['inserted'] += count($batch);
        } catch (\Throwable $e) {
            // Si falla un batch, lo registramos (sin reventar toda la importación)
            $this->importSummary['errors'][] = 'Error insertando lote: ' . $e->getMessage();
        }
    }

    private function normalizePhone(string $value): ?string
    {
        return PhoneNumber::isValid($value) ? PhoneNumber::digits($value) : null;
    }

    public function cancel(int $id): void
    {
        abort_unless(auth()->user()->can('whatsapp.enviar'), 403);

        WhatsAppOutbox::query()
            ->where('id', $id)
            ->whereIn('status', ['PENDING'])
            ->update(['status' => 'CANCELLED']);

        $this->dispatch('cancelled');
    }

    public function render()
    {
        $q = WhatsAppOutbox::query()->orderByDesc('id');

        if ($this->statusFilter !== 'ALL') {
            $q->where('status', $this->statusFilter);
        }

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $q->where(function ($qq) use ($term) {
                $qq->where('phone', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%");
            });
        }

        return view('livewire.whatsapp.whatsapp-outbox-manager', [
            'jobs' => $q->paginate($this->perPage),
        ]);
    }
}
