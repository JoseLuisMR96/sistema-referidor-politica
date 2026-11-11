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
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Borra un usuario. Sus registros, eventos y gastos quedan, sin autor
     * (las FK a users son nullOnDelete). No se borra a sí mismo, un
     * restringido solo borra a los de su tenant y nunca a un global, y no
     * se puede quedar la app sin un administrador global.
     */
    public function eliminar(int $id): void
    {
        $yo = auth()->user();
        abort_unless($yo->can('usuarios.eliminar'), 403);

        $u = User::findOrFail($id);
        abort_unless($yo->esGlobal() || (! $u->esGlobal() && (string) $u->tenant_id === (string) $yo->tenant_id), 403);

        if ($u->id === $yo->id) {
            session()->flash('error', 'No puedes eliminar tu propio usuario.');

            return;
        }

        $adminGlobal = fn ($q) => $q->whereNull('tenant_id')->whereHas('roles', fn ($r) => $r->where('name', 'Administrador'));
        if ($u->esGlobal() && $u->hasRole('Administrador') && User::query()->where($adminGlobal)->count() <= 1) {
            session()->flash('error', 'Es el último administrador global: no se puede eliminar.');

            return;
        }

        $u->delete();
        session()->flash('success', 'Usuario eliminado.');
    }

    public function render()
    {
        abort_unless(auth()->user()->can('usuarios.ver'), 403);

        $yo = auth()->user();

        $q = User::query()
            ->with('tenant:id,nombre')
            // Un usuario restringido solo ve a los de su tenant
            ->when(! $yo->esGlobal(), fn ($query) => $query->where('tenant_id', $yo->tenant_id))
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
