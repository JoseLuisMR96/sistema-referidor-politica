<?php

namespace App\Livewire\Users;

use App\Models\Tenant;
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

    // '' = usuario global; id = restringido a ese tenant
    public string $tenant_id = '';

    public function mount(User $user): void
    {
        abort_unless(auth()->user()->can('usuarios.editar'), 403);
        $this->autorizarSobre($user);

        $this->user = $user;
        $this->tenant_id = (string) ($user->tenant_id ?? '');

        $this->name = $user->name;
        $this->email = $user->email;

        $this->roleIds = $user->roles()->pluck('id')->map(fn($x) => (int)$x)->toArray();
    }

    /**
     * Un usuario restringido solo administra usuarios de su propio tenant
     * (nunca globales ni de otro tenant).
     */
    private function autorizarSobre(User $user): void
    {
        $yo = auth()->user();

        abort_unless($yo->esGlobal() || (string) $user->tenant_id === (string) $yo->tenant_id, 403);
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
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],

            // Password solo si se diligencia
            'password' => ['nullable', 'string', 'min:8', 'same:password_confirmation'],
            'password_confirmation' => ['nullable', 'string', 'min:8'],
        ];
    }

    public function update(): void
    {
        abort_unless(auth()->user()->can('usuarios.editar'), 403);
        $this->autorizarSobre($this->user);

        $this->validate();

        $this->user->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        // Cambiar el alcance (global / tenant) solo lo hace un usuario global
        if (auth()->user()->esGlobal()) {
            $this->user->update(['tenant_id' => $this->tenant_id !== '' ? (int) $this->tenant_id : null]);
        }

        if (filled($this->password)) {
            $this->user->update([
                'password' => Hash::make($this->password),
            ]);

            // Limpieza UX
            $this->password = '';
            $this->password_confirmation = '';
        }

        // Sincroniza roles por IDs -> nombres. Cambiar roles es escalar
        // privilegios: sin usuarios.roles se ignora lo que venga del form.
        if (auth()->user()->can('usuarios.roles')) {
            $roles = Role::query()->whereIn('id', $this->roleIds)->pluck('name')->toArray();
            $this->user->syncRoles($roles);
        }

        session()->flash('success', 'Usuario actualizado correctamente.');
    }

    public function render()
    {
        return view('livewire.users.edit', [
            'allRoles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'userRoleNames' => $this->user->getRoleNames(),
            'tenants' => auth()->user()->esGlobal() ? Tenant::orderBy('nombre')->get(['id', 'nombre']) : collect(),
        ]);
    }
}
