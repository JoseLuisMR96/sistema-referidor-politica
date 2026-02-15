<?php

namespace App\Livewire\Registrations;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\PublicRegistration;
use App\Models\Referrer;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $referrer = '';
    public int $perPage = 10;
    // public string $status = '';

    // Modal / edición (si no lo vas a usar, lo puedes quitar)
    public bool $showEditModal = false;
    public ?int $editingId = null;

    public string $full_name = '';
    public string $phone = '';
    public string $document_number = '';
    public ?int $referrer_id = null;

    protected $queryString = [
        'search' => ['except' => ''],
        // 'status' => ['except' => ''],
        'referrer' => ['except' => ''],
        'perPage' => ['except' => 10],
    ];

    // Reglas básicas (ajusta a tu modelo real)
    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'document_number' => ['required', 'string', 'max:30'],
            'referrer_id' => ['nullable', 'integer', 'exists:referrers,id'],
        ];
    }

    private function authorizeAdminOnly(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('Administrador'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    // public function updatedStatus()
    // {
    //     $this->resetPage();
    // }
    public function updatedReferrer(): void
    {
        $this->resetPage();
    }
    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    // Abre modal de edición
    public function openEdit(int $id): void
    {
        $this->authorizeAdminOnly();

        $reg = PublicRegistration::findOrFail($id);

        $this->editingId = $reg->id;
        $this->full_name = (string) $reg->full_name;
        $this->phone = (string) ($reg->phone ?? '');
        $this->document_number = (string) $reg->document_number;
        $this->referrer_id = $reg->referrer_id;

        $this->showEditModal = true;
    }

    public function closeEdit(): void
    {
        $this->resetEditForm();
        $this->showEditModal = false;
    }

    private function resetEditForm(): void
    {
        $this->editingId = null;
        $this->full_name = '';
        $this->phone = '';
        $this->document_number = '';
        $this->referrer_id = null;
        $this->resetValidation();
    }

    // Guarda cambios
    public function saveEdit(): void
    {
        $this->authorizeAdminOnly();

        $this->validate();

        $reg = PublicRegistration::findOrFail($this->editingId);

        $reg->update([
            'full_name' => $this->full_name,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'document_number' => $this->document_number,
            'referrer_id' => $this->referrer_id,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Registro actualizado.');
        $this->closeEdit();
    }

    public function render()
    {
        abort_unless(auth()->user()->can('registros.ver_todos'), 403);

        $query = PublicRegistration::query()
            ->with(['referrer', 'residenceMunicipality', 'votingMunicipality'])
            ->when($this->search, function ($q) {
                $s = trim($this->search);
                $q->where(function ($qq) use ($s) {
                    $qq->where('full_name', 'like', "%{$s}%")
                        ->orWhere('document_number', 'like', "%{$s}%")
                        ->orWhere('phone', 'like', "%{$s}%");
                });
            })
            ->when($this->referrer, fn($q) => $q->where('referrer_id', $this->referrer))
            ->latest();

        return view('livewire.registrations.index', [
            'registrations' => $query->paginate($this->perPage),
            'referrers' => Referrer::orderBy('name')->get(),

            // Útil para la vista: mostrar/ocultar botones
            'isAdmin' => auth()->check() && auth()->user()->hasRole('Administrador'),
        ]);
    }
}
