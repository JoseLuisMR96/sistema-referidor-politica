<?php

namespace App\Livewire\Admin\Tenants;

use App\Enums\CargoEnum;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\PartidoIdeologia;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Marca;
use App\Support\Partidos;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Registro y edición de un candidato (tenant): datos, circunscripción e
 * identidad visual. El listado está en Index. Solo usuarios globales con
 * tenants.gestionar.
 *
 * Al crearlo se puede crear también la cuenta del candidato: un usuario
 * restringido a su tenant con rol Administrador, que después agrega a su
 * equipo desde Usuarios (solo puede crear usuarios de su candidato).
 */
class Formulario extends Component
{
    use WithFileUploads;

    public ?Tenant $tenant = null;

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

    // Tema completo: fondo, tarjetas y textos de toda la app con su color
    public bool $tema_completo = false;

    // false = la app con sus colores por defecto; los del candidato quedan guardados
    public bool $usar_colores = false;

    public $logo = null;   // archivo subido (Livewire)

    public $foto = null;

    public $fondo = null;  // imagen decorativa detrás del contenido (bandera, etc.)

    /**
     * Select del partido: '' = sin partido, OTRO = se escribe a mano, o una
     * clave de partidos_ideologia (así el Análisis reconoce su partido).
     */
    public string $partidoOpcion = '';

    public const OTRO_PARTIDO = '__otro';

    // --- Cuenta del candidato (solo al crear; se crea si se escribe el correo) ---
    public string $cuentaNombre = '';

    public string $cuentaEmail = '';

    public string $cuentaPassword = '';

    public string $cuentaPassword_confirmation = '';

    private function autorizar(): void
    {
        $user = auth()->user();
        abort_unless($user->can('tenants.gestionar') && $user->esGlobal(), 403);
    }

    public function mount(?Tenant $tenant = null): void
    {
        $this->autorizar();

        if (! $tenant?->exists) {
            // Candidato nuevo: arranca con la apariencia por defecto de la app
            $this->tenant = null;
            $this->coloresPorDefecto();

            return;
        }

        $this->tenant = $tenant;
        $this->nombre = $tenant->nombre;
        $this->descripcion = (string) $tenant->descripcion;
        $this->activo = $tenant->activo;
        $this->twilio_messaging_service_sid = (string) $tenant->twilio_messaging_service_sid;
        $this->cargo = (string) $tenant->cargo?->value;
        $this->departamento_id = $tenant->departamento_id;
        $this->municipio_id = $tenant->municipio_id;
        $this->partido = (string) $tenant->partido;
        $this->partidoOpcion = $this->opcionDe($this->partido);
        $this->slogan = (string) $tenant->slogan;
        $this->color_primario = (string) $tenant->color_primario;
        $this->color_secundario = (string) $tenant->color_secundario;
        $this->tema_completo = (bool) $tenant->tema_completo;
        $this->usar_colores = $tenant->usar_colores !== false;
    }

    /** Llena los campos de color con los de la app (fondos blancos, índigo). */
    public function coloresPorDefecto(): void
    {
        $this->color_primario = Marca::PRIMARIO_DEFECTO;
        $this->color_secundario = Marca::SECUNDARIO_DEFECTO;
        $this->tema_completo = false;
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
            'tema_completo' => ['boolean'],
            'usar_colores' => ['boolean'],
            // Sin SVG: puede llevar scripts y se serviría desde nuestro dominio
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'foto' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'fondo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:6144'],
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

    public function guardar(): void
    {
        $this->autorizar();

        $data = $this->validate();
        $cuenta = $this->validarCuenta();
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
        unset($data['logo'], $data['foto'], $data['fondo']);

        if ($this->tenant) {
            $tenant = $this->tenant;
            $tenant->update($data);
            session()->flash('success', "Candidato \"{$tenant->nombre}\" actualizado.");
        } else {
            // El candidato y su cuenta van juntos: si falla uno, no queda ninguno
            $tenant = DB::transaction(function () use ($data, $cuenta) {
                $tenant = Tenant::create($data);
                if ($cuenta) {
                    User::create($cuenta + ['tenant_id' => $tenant->id])->assignRole(Roles::ADMINISTRADOR);
                }

                return $tenant;
            });
            session()->flash('success', "Candidato \"{$tenant->nombre}\" creado".($cuenta
                ? ". Su cuenta ({$cuenta['email']}) ya puede entrar y agregar a su equipo en Usuarios."
                : '. Elígelo en el menú lateral para empezar a cargarle datos.'));
        }

        $this->guardarArchivo($tenant, 'logo', $this->logo);
        $this->guardarArchivo($tenant, 'foto', $this->foto);
        $this->guardarArchivo($tenant, 'fondo', $this->fondo);

        // Recarga completa: el menú lateral también lista los candidatos
        $this->redirectRoute('tenants.index');
    }

    /** Partidos de la tabla de ideología (lista curada, por clave). */
    private function partidosConocidos(): array
    {
        return PartidoIdeologia::query()->orderBy('clave')->pluck('clave')->all();
    }

    /** Opción del select que corresponde a un nombre de partido guardado. */
    private function opcionDe(string $partido): string
    {
        if (trim($partido) === '') {
            return '';
        }
        $clave = Partidos::clave($partido);

        return in_array($clave, $this->partidosConocidos(), true) ? $clave : self::OTRO_PARTIDO;
    }

    public function updatedPartidoOpcion(): void
    {
        $this->partido = match ($this->partidoOpcion) {
            '' => '',
            // Si ya había uno escrito a mano, se deja para editarlo
            self::OTRO_PARTIDO => $this->opcionDe($this->partido) === self::OTRO_PARTIDO ? $this->partido : '',
            default => in_array($this->partidoOpcion, $this->partidosConocidos(), true) ? $this->partidoOpcion : '',
        };
    }

    /**
     * Datos de la cuenta del candidato, validados (null = no se crea). Solo
     * al crear el candidato; después se agregan usuarios desde Usuarios.
     *
     * @return array{name:string, email:string, password:string}|null
     */
    private function validarCuenta(): ?array
    {
        if ($this->tenant || (trim($this->cuentaEmail) === '' && $this->cuentaPassword === '')) {
            return null;
        }

        $this->cuentaNombre = trim($this->cuentaNombre) ?: $this->nombre;
        $v = $this->validate([
            'cuentaNombre' => ['required', 'string', 'min:3', 'max:190'],
            'cuentaEmail' => ['required', 'email', 'max:190', 'unique:users,email'],
            'cuentaPassword' => ['required', 'confirmed', Password::min(8)],
        ], [
            'cuentaEmail.required' => 'Escribe el correo con que el candidato va a entrar.',
            'cuentaEmail.unique' => 'Ya hay un usuario con ese correo.',
            'cuentaPassword.required' => 'Ponle una contraseña inicial (mínimo 8 caracteres).',
            'cuentaPassword.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        return ['name' => $v['cuentaNombre'], 'email' => $v['cuentaEmail'], 'password' => Hash::make($v['cuentaPassword'])];
    }

    /** Guarda el logo, la foto o el fondo y borra el anterior. */
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
        abort_unless(in_array($tipo, ['logo', 'foto', 'fondo'], true) && $this->tenant, 404);

        if ($ruta = $this->tenant->{"{$tipo}_path"}) {
            Storage::disk('public')->delete($ruta);
        }

        $this->tenant->forceFill(["{$tipo}_path" => null])->touch();
        $this->reset($tipo);
    }

    public function render()
    {
        $cargo = $this->cargoEnum();

        // Botones y enlaces llevan texto blanco sobre el color primario: avisar
        // si no se lee bien (WCAG pide 4.5:1 para texto normal)
        $contrastePrimario = Marca::esHex($this->color_primario)
            ? Marca::contraste($this->color_primario, '#ffffff')
            : null;

        return view('livewire.admin.tenants.formulario', [
            'contrastePrimario' => $contrastePrimario,
            'partidosConocidos' => $this->partidosConocidos(),
            // Al editar: quiénes entran por este candidato
            'usuarios' => $this->tenant
                ? User::where('tenant_id', $this->tenant->id)->with('roles:id,name')->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
            'cargos' => CargoEnum::cases(),
            'pideDepartamento' => (bool) $cargo?->requiereDepartamento(),
            'pideMunicipio' => (bool) $cargo?->requiereMunicipio(),
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
            'municipios' => $this->departamento_id
                ? Municipio::where('departamento_id', $this->departamento_id)->orderBy('nombre')->get(['id', 'nombre'])
                : collect(),
        ])->title($this->tenant ? "Editar {$this->tenant->nombre}" : 'Nuevo candidato');
    }
}
