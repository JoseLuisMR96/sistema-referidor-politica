<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class UserCreate extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public array $roles = [];

    public function mount(): void
    {
        $this->authorize('usuarios.crear');
    }

    public function save(): void
    {
        $this->authorize('usuarios.crear');

        $data = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:190'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($data['roles'] ?? []);

        session()->flash('success', 'Usuario creado correctamente.');
        $this->reset(['name', 'email', 'password', 'password_confirmation', 'roles']);
    }

    public function render()
    {
        return view('livewire.users.create', [
            'availableRoles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ])->title('Crear usuario');
    }
}
