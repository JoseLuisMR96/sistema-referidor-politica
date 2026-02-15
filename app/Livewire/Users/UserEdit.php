<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserEdit extends Component
{
    public User $user;

    public string $name = '';
    public string $email = '';

    // Password opcional
    public string $password = '';
    public string $password_confirmation = '';

    // Roles seleccionados (IDs)
    public array $roleIds = [];

    public function mount(User $user): void
    {
        $this->user = $user;

        $this->name = $user->name;
        $this->email = $user->email;

        $this->roleIds = $user->roles()->pluck('id')->map(fn($x) => (int)$x)->toArray();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($this->user->id),
            ],
            'roleIds' => ['array'],
            'roleIds.*' => ['integer', 'exists:roles,id'],

            // Password solo si se diligencia
            'password' => ['nullable', 'string', 'min:8', 'same:password_confirmation'],
            'password_confirmation' => ['nullable', 'string', 'min:8'],
        ];
    }

    public function update(): void
    {
        $this->validate();

        $this->user->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        if (filled($this->password)) {
            $this->user->update([
                'password' => Hash::make($this->password),
            ]);

            // Limpieza UX
            $this->password = '';
            $this->password_confirmation = '';
        }

        // Sincroniza roles por IDs -> nombres
        $roles = Role::query()->whereIn('id', $this->roleIds)->pluck('name')->toArray();
        $this->user->syncRoles($roles);

        session()->flash('success', 'Usuario actualizado correctamente.');
    }

    public function render()
    {
        return view('livewire.users.edit', [
            'allRoles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'userRoleNames' => $this->user->getRoleNames(),
        ]);
    }
}
