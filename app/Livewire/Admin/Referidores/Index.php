<?php

namespace App\Livewire\Admin\Referidores;

use App\Models\ReferidorPregonero;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';
    public int $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => 10],
        'page' => ['except' => 1],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

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
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->user()->can('pregoneros_referidores.ver'), 403);

        $s = trim($this->search);

        $referidores = ReferidorPregonero::query()
            ->when($s !== '', function ($query) use ($s) {
                $query->where(function ($q) use ($s) {
                    $q->where('nombre', 'like', "%{$s}%")
                        ->orWhere('cedula', 'like', "%{$s}%")
                        ->orWhere('celular', 'like', "%{$s}%")
                        ->orWhere('puesto_votacion', 'like', "%{$s}%")
                        ->orWhere('id_unico', 'like', "%{$s}%");
                });
            })
            ->withCount('referidos')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.referidores.index', compact('referidores'));
    }
}
