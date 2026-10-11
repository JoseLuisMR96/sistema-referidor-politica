<?php

namespace App\Livewire\Admin\Tenants;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Alta y edición de tenants (candidatos/campañas). Solo usuarios globales
 * con tenants.gestionar: un usuario restringido no debe ver los demás.
 */
class Index extends Component
{
    public ?int $editingId = null;

    public string $nombre = '';

    public string $descripcion = '';

    public bool $activo = true;

    public string $twilio_messaging_service_sid = '';

    private function autorizar(): void
    {
        $user = auth()->user();
        abort_unless($user->can('tenants.gestionar') && $user->esGlobal(), 403);
    }

    public function mount(): void
    {
        $this->autorizar();
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
            'twilio_messaging_service_sid' => ['nullable', 'string', 'starts_with:MG', 'max:64'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del candidato es obligatorio.',
            'twilio_messaging_service_sid.starts_with' => 'El Messaging Service SID empieza por MG.',
        ];
    }

    public function editar(int $id): void
    {
        $this->autorizar();

        $tenant = Tenant::findOrFail($id);

        $this->editingId = $tenant->id;
        $this->nombre = $tenant->nombre;
        $this->descripcion = (string) $tenant->descripcion;
        $this->activo = $tenant->activo;
        $this->twilio_messaging_service_sid = (string) $tenant->twilio_messaging_service_sid;
        $this->resetValidation();
    }

    public function cancelar(): void
    {
        $this->reset(['editingId', 'nombre', 'descripcion', 'twilio_messaging_service_sid']);
        $this->activo = true;
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $this->autorizar();

        $data = $this->validate();
        $data['descripcion'] = $data['descripcion'] ?: null;
        $data['twilio_messaging_service_sid'] = $data['twilio_messaging_service_sid'] ?: null;

        if ($this->editingId) {
            Tenant::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Tenant actualizado.');
        } else {
            $tenant = Tenant::create($data);
            session()->flash('success', "Tenant \"{$tenant->nombre}\" creado. Elígelo en el menú lateral para empezar a cargarle datos.");
        }

        $this->cancelar();
    }

    public function render()
    {
        // Conteos con DB::table a propósito: con Eloquent el scope del tenant
        // elegido en el menú dejaría en 0 a todos los demás.
        $contar = fn (string $tabla) => DB::table($tabla)
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        return view('livewire.admin.tenants.index', [
            'tenants' => Tenant::query()
                ->withCount(['users'])
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->get(),
            'registros' => $contar('public_registrations'),
            'pregoneros' => $contar('referidores_pregoneros'),
            'campanas' => $contar('campaigns'),
        ]);
    }
}
