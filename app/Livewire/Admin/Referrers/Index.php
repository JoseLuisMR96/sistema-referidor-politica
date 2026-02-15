<?php

namespace App\Livewire\Admin\Referrers;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Referrer;

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
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        abort_unless(auth()->user()->can('referidores.editar'), 403);

        $ref = Referrer::findOrFail($id);
        $ref->update([
            'is_active' => ! $ref->is_active,
        ]);
    }

    public function render()
    {
        abort_unless(auth()->user()->can('referidores.ver'), 403);

        $referrers = Referrer::query()
            ->when($this->search !== '', function ($query) {
                $s = trim($this->search);
                $query->where(function ($q) use ($s) {
                    $q->where('name', 'like', "%{$s}%")
                      ->orWhere('code', 'like', "%{$s}%")
                      ->orWhere('phone', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('livewire.admin.referrers.index', compact('referrers'));
    }
}
