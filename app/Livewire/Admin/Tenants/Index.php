<?php

namespace App\Livewire\Admin\Tenants;

use App\Enums\CargoEnum;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    // Circunscripción: qué se exige depende del ámbito del cargo
    public string $cargo = '';

    public ?int $departamento_id = null;

    public ?int $municipio_id = null;

    private function autorizar(): void
    {
        $user = auth()->user();
        abort_unless($user->can('tenants.gestionar') && $user->esGlobal(), 403);
    }

    public function mount(): void
    {
        $this->autorizar();
    }

    private function cargoEnum(): ?CargoEnum
    {
        return CargoEnum::tryFrom($this->cargo);
    }

    public function updatedCargo(): void
    {
        $cargo = $this->cargoEnum();

        if (! $cargo?->requiereDepartamento()) {
            $this->departamento_id = null;
        }

        if (! $cargo?->requiereMunicipio()) {
            $this->municipio_id = null;
        }
    }

    public function updatedDepartamentoId(): void
    {
        $this->municipio_id = null;
    }

    protected function rules(): array
    {
        $cargo = $this->cargoEnum();

        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
            'twilio_messaging_service_sid' => ['nullable', 'string', 'starts_with:MG', 'max:64'],
            'cargo' => ['required', Rule::enum(CargoEnum::class)],
            'departamento_id' => [
                Rule::requiredIf((bool) $cargo?->requiereDepartamento()),
                'nullable', 'integer', 'exists:departamentos,id',
            ],
            'municipio_id' => [
                Rule::requiredIf((bool) $cargo?->requiereMunicipio()),
                'nullable', 'integer',
                // El municipio tiene que ser del departamento elegido
                Rule::exists('municipios', 'id')->where('departamento_id', $this->departamento_id),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del candidato es obligatorio.',
            'cargo.required' => 'Elige el cargo al que se postula.',
            'departamento_id.required' => 'Para este cargo hay que indicar el departamento.',
            'municipio_id.required' => 'Para este cargo hay que indicar el municipio.',
            'municipio_id.exists' => 'El municipio no pertenece al departamento elegido.',
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
        $this->cargo = (string) $tenant->cargo?->value;
        $this->departamento_id = $tenant->departamento_id;
        $this->municipio_id = $tenant->municipio_id;
        $this->resetValidation();
    }

    public function cancelar(): void
    {
        $this->reset(['editingId', 'nombre', 'descripcion', 'twilio_messaging_service_sid', 'cargo', 'departamento_id', 'municipio_id']);
        $this->activo = true;
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $this->autorizar();

        $data = $this->validate();
        $cargo = CargoEnum::from($data['cargo']);

        $data['descripcion'] = $data['descripcion'] ?: null;
        $data['twilio_messaging_service_sid'] = $data['twilio_messaging_service_sid'] ?: null;

        // No guardar lugar que el cargo no usa (p. ej. municipio en Gobernación)
        $data['departamento_id'] = $cargo->requiereDepartamento() ? $data['departamento_id'] : null;
        $data['municipio_id'] = $cargo->requiereMunicipio() ? $data['municipio_id'] : null;

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

        $cargo = $this->cargoEnum();

        return view('livewire.admin.tenants.index', [
            'tenants' => Tenant::query()
                ->with(['departamento:id,nombre', 'municipio:id,nombre'])
                ->withCount(['users'])
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->get(),
            'registros' => $contar('public_registrations'),
            'pregoneros' => $contar('referidores_pregoneros'),
            'campanas' => $contar('campaigns'),
            'cargos' => CargoEnum::cases(),
            'pideDepartamento' => (bool) $cargo?->requiereDepartamento(),
            'pideMunicipio' => (bool) $cargo?->requiereMunicipio(),
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
            'municipios' => $this->departamento_id
                ? Municipio::where('departamento_id', $this->departamento_id)->orderBy('nombre')->get(['id', 'nombre'])
                : collect(),
        ]);
    }
}
