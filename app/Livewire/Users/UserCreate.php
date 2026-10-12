<?php

namespace App\Livewire\Users;

use App\Models\Tenant;
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

    // '' = usuario global (ve todos los tenants); id = restringido a ese tenant
    public string $tenant_id = '';

    public function mount(): void
    {
        $this->authorize('usuarios.crear');

        // Un usuario restringido solo crea usuarios de su propio tenant
        if (! auth()->user()->esGlobal()) {
            $this->tenant_id = (string) auth()->user()->tenant_id;
        }
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
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);

        // Solo un usuario global puede crear globales o asignar otro tenant
        $tenantId = auth()->user()->esGlobal()
            ? (($data['tenant_id'] ?? null) ?: null)
            : auth()->user()->tenant_id;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'tenant_id' => $tenantId,
        ]);

        // Asignar roles es escalar privilegios: requiere su propio permiso
        if (! empty($data['roles'])) {
            abort_unless(auth()->user()->can('usuarios.roles'), 403);
            $user->syncRoles($data['roles']);
        }

        session()->flash('success', 'Usuario creado correctamente.');
        $this->reset(['name', 'email', 'password', 'password_confirmation', 'roles']);
        $this->tenant_id = auth()->user()->esGlobal() ? '' : (string) auth()->user()->tenant_id;
    }

    public function render()
    {
        return view('livewire.users.create', [
            'availableRoles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'tenants' => auth()->user()->esGlobal() ? Tenant::orderBy('nombre')->get(['id', 'nombre']) : collect(),
        ])->title('Crear usuario');
    }
}
