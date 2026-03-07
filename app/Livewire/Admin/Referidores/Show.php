<?php

namespace App\Livewire\Admin\Referidores;

use App\Models\ReferidorPregonero;
use App\Models\ReferidoPregonero;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    public ReferidorPregonero $referidor;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public int $perPage = 25;

    #[Url]
    public string $sortField = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

    public function mount(ReferidorPregonero $referidor): void
    {
        $this->referidor = $referidor;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }

    public function render()
    {
        $q = trim($this->search);

        $referidos = ReferidoPregonero::query()
            ->where('referidor_pregonero_id', $this->referidor->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('nombre', 'like', "%{$q}%")
                       ->orWhere('cedula', 'like', "%{$q}%")
                       ->orWhere('puesto_votacion', 'like', "%{$q}%");
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.referidores.show', [
            'referidos' => $referidos,
        ]);
    }
}