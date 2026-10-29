<?php

namespace App\Livewire\Admin\Tenants;

use App\Enums\CargoEnum;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use App\Support\Marca;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Alta y edición de tenants (candidatos/campañas). Solo usuarios globales
 * con tenants.gestionar: un usuario restringido no debe ver los demás.
 */
class Index extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    public string $nombre = '';

    public string $descripcion = '';

    public bool $activo = true;

    public string $twilio_messaging_service_sid = '';

    // Circunscripción: qué se exige depende del ámbito del cargo
    public string $cargo = '';

    public ?int $departamento_id = null;

    public ?int $municipio_id = null;

    // Identidad visual: pinta la app y los formularios públicos del candidato
    public string $partido = '';

    public string $slogan = '';

    public string $color_primario = '';

    public string $color_secundario = '';

    public $logo = null;   // archivo subido (Livewire)

    public $foto = null;

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
            'partido' => ['nullable', 'string', 'max:150'],
            'slogan' => ['nullable', 'string', 'max:200'],
            'color_primario' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_secundario' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // Sin SVG: puede llevar scripts y se serviría desde nuestro dominio
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'foto' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
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
            'color_primario.regex' => 'Usa un color en formato #RRGGBB.',
            'color_secundario.regex' => 'Usa un color en formato #RRGGBB.',
            'logo.image' => 'El logo debe ser una imagen PNG, JPG o WEBP.',
            'logo.mimes' => 'El logo debe ser PNG, JPG o WEBP.',
            'logo.max' => 'El logo no puede pesar más de 2 MB.',
            'foto.image' => 'La foto debe ser una imagen PNG, JPG o WEBP.',
            'foto.mimes' => 'La foto debe ser PNG, JPG o WEBP.',
            'foto.max' => 'La foto no puede pesar más de 4 MB.',
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
        $this->partido = (string) $tenant->partido;
        $this->slogan = (string) $tenant->slogan;
        $this->color_primario = (string) $tenant->color_primario;
        $this->color_secundario = (string) $tenant->color_secundario;
        $this->reset(['logo', 'foto']);
        $this->resetValidation();
    }

    public function cancelar(): void
    {
        $this->reset([
            'editingId', 'nombre', 'descripcion', 'twilio_messaging_service_sid', 'cargo', 'departamento_id', 'municipio_id',
            'partido', 'slogan', 'color_primario', 'color_secundario', 'logo', 'foto',
        ]);
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

        foreach (['partido', 'slogan', 'color_primario', 'color_secundario'] as $campo) {
            $data[$campo] = $data[$campo] ?: null;
        }

        // Los archivos se guardan aparte, ya con el id del tenant
        unset($data['logo'], $data['foto']);

        if ($this->editingId) {
            $tenant = Tenant::findOrFail($this->editingId);
            $tenant->update($data);
            session()->flash('success', 'Tenant actualizado.');
        } else {
            $tenant = Tenant::create($data);
            session()->flash('success', "Tenant \"{$tenant->nombre}\" creado. Elígelo en el menú lateral para empezar a cargarle datos.");
        }

        $this->guardarArchivo($tenant, 'logo', $this->logo);
        $this->guardarArchivo($tenant, 'foto', $this->foto);

        $this->cancelar();
    }

    /** Guarda el logo o la foto y borra el anterior. */
    private function guardarArchivo(Tenant $tenant, string $tipo, $archivo): void
    {
        if (! $archivo) {
            return;
        }

        $anterior = $tenant->{"{$tipo}_path"};
        $ruta = $archivo->store("tenants/{$tenant->id}", 'public');

        // touch: cambia updated_at y con él el ?v= de la URL de la imagen
        $tenant->forceFill(["{$tipo}_path" => $ruta])->touch();

        if ($anterior && $anterior !== $ruta) {
            Storage::disk('public')->delete($anterior);
        }
    }

    public function quitarArchivo(string $tipo): void
    {
        $this->autorizar();
        abort_unless(in_array($tipo, ['logo', 'foto'], true) && $this->editingId, 404);

        $tenant = Tenant::findOrFail($this->editingId);

        if ($ruta = $tenant->{"{$tipo}_path"}) {
            Storage::disk('public')->delete($ruta);
        }

        $tenant->forceFill(["{$tipo}_path" => null])->touch();
        $this->reset($tipo);
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

        // Botones y enlaces llevan texto blanco sobre el color primario: avisar
        // si no se lee bien (WCAG pide 4.5:1 para texto normal)
        $contrastePrimario = Marca::esHex($this->color_primario)
            ? Marca::contraste($this->color_primario, '#ffffff')
            : null;

        return view('livewire.admin.tenants.index', [
            'contrastePrimario' => $contrastePrimario,
            'tenantEditado' => $this->editingId ? Tenant::find($this->editingId) : null,
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
