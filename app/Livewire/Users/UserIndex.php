<?php

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class UserIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;

    // Si cambias búsqueda o perPage, reinicia a la página 1
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedPerPage(): void { $this->resetPage(); }

    public function render()
    {
        $q = User::query()
            ->when($this->search !== '', function ($query) {
                $s = trim($this->search);
                $query->where(function ($qq) use ($s) {
                    $qq->where('name', 'like', "%{$s}%")
                       ->orWhere('email', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('id');

        return view('livewire.users.index', [
            'users' => $q->paginate($this->perPage),
        ]);
    }
}
