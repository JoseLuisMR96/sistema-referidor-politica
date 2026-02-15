<?php

namespace App\Livewire\ImportedPeople;

use App\Models\ImportedPerson;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public ?string $batch = '';
    public ?string $municipality = '';
    public int $perPage = 25;

    protected $queryString = [
        'search' => ['except' => ''],
        'batch' => ['except' => ''],
        'municipality' => ['except' => ''],
        'perPage' => ['except' => 25],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingPerPage(): void { $this->resetPage(); }
    public function updatingBatch(): void { $this->resetPage(); }
    public function updatingMunicipality(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'batch', 'municipality']);
        $this->resetPage();
    }

    public function render()
    {
        $q = trim($this->search);

        $rows = ImportedPerson::query()
            ->when($this->batch, fn($qq) => $qq->where('batch_id', $this->batch))
            ->when($this->municipality, fn($qq) => $qq->where('voting_municipality', $this->municipality))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('full_name', 'like', "%{$q}%")
                      ->orWhere('document_number', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%")
                      ->orWhere('voting_place', 'like', "%{$q}%")
                      ->orWhere('voting_municipality', 'like', "%{$q}%")
                      ->orWhere('batch_id', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($this->perPage);

        $batches = ImportedPerson::query()
            ->select('batch_id')
            ->whereNotNull('batch_id')
            ->where('batch_id', '!=', '')
            ->distinct()
            ->orderByDesc('batch_id')
            ->pluck('batch_id');

        $municipalities = ImportedPerson::query()
            ->select('voting_municipality')
            ->whereNotNull('voting_municipality')
            ->where('voting_municipality', '!=', '')
            ->distinct()
            ->orderBy('voting_municipality')
            ->pluck('voting_municipality');

        return view('livewire.imported-people.index', compact('rows', 'batches', 'municipalities'));
    }
}
