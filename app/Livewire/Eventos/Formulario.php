<?php

namespace App\Livewire\Eventos;

use App\Livewire\Concerns\RequiereTenant;
use App\Models\EventoCampana;
use App\Models\EvidenciaEvento;
use App\Models\IntegranteEquipo;
use App\Support\LugaresCampana;
use App\Support\TenantContext;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Registro y edición de un evento de campaña del candidato elegido, con sus
 * evidencias. El lugar se limita a la circunscripción (LugaresCampana).
 */
class Formulario extends Component
{
    use RequiereTenant;
    use WithFileUploads;

    public ?EventoCampana $evento = null;

    public string $titulo = '';

    public string $tipo = 'reunion';

    public string $estado = 'realizado';

    public string $fecha = '';

    public ?int $departamento_id = null;

    public ?int $municipio_id = null;

    public string $lugar = '';

    public string $direccion = '';

    /** "4.1420, -73.6266": se pega de Google Maps o sale de "Usar mi ubicación" */
    public string $coordenadas = '';

    public bool $asistio_candidato = false;

    public ?int $responsable_id = null;

    /** Si el candidato fue en persona, por defecto responde él: el responsable queda vacío (se puede elegir a alguien). */
    public function updatedAsistioCandidato(bool $valor): void
    {
        if ($valor) {
            $this->responsable_id = null;
        }
    }

    /** Otros integrantes del equipo que fueron (ids) */
    public array $integrantes = [];

    public ?int $asistentes = null;

    public string $monto_asignado = '';

    public string $monto_gastado = '';

    public string $descripcion = '';

    public string $resultado = '';

    /** Archivos nuevos (fotos o PDF) */
    public array $archivos = [];

    public const MAX_ARCHIVOS = 20;

    private function autorizar(): void
    {
        abort_unless(auth()->user()->can($this->evento ? 'eventos.editar' : 'eventos.crear'), 403);
    }

    public function mount(?EventoCampana $evento = null): void
    {
        $this->evento = $evento?->exists ? $evento : null;
        $this->autorizar();

        if (! $this->evento) {
            $this->fecha = now()->format('Y-m-d\TH:i');
            $this->ajustarLugar();
            $this->desdePlan();

            return;
        }

        $e = $this->evento;
        $this->fill([
            'titulo' => $e->titulo,
            'tipo' => $e->tipo,
            'estado' => $e->estado,
            'fecha' => $e->fecha->format('Y-m-d\TH:i'),
            'departamento_id' => $e->departamento_id,
            'municipio_id' => $e->municipio_id,
            'lugar' => (string) $e->lugar,
            'direccion' => (string) $e->direccion,
            'coordenadas' => $e->tieneUbicacion() ? "{$e->latitud}, {$e->longitud}" : '',
            'asistio_candidato' => $e->asistio_candidato,
            'responsable_id' => $e->responsable_id,
            'integrantes' => $e->integrantes()->pluck('equipo_campana.id')->map(fn ($id) => (int) $id)->all(),
            'asistentes' => $e->asistentes,
            'monto_asignado' => $e->monto_asignado > 0 ? (string) (float) $e->monto_asignado : '',
            'monto_gastado' => $e->monto_gastado !== null ? (string) (float) $e->monto_gastado : '',
            'descripcion' => (string) $e->descripcion,
            'resultado' => (string) $e->resultado,
        ]);
    }

    private function lugares(): ?LugaresCampana
    {
        $tenant = app(TenantContext::class)->tenant();

        return $tenant ? LugaresCampana::para($tenant) : null;
    }

    /** Pone el departamento y el municipio fijos de la circunscripción. */
    private function ajustarLugar(): void
    {
        $lugares = $this->lugares();
        if (! $lugares) {
            return;
        }
        $this->departamento_id = $lugares->departamentoFijo() ?? $this->departamento_id;
        $this->municipio_id = $lugares->municipioFijo() ?? $this->municipio_id;
    }

    /**
     * "Agendar" del plan de giras: ?municipio=ID&estado=planeado&lugar=...
     * Solo se toma el municipio si está en la circunscripción.
     */
    private function desdePlan(): void
    {
        $municipio = (int) request()->query('municipio');
        if ($municipio && $this->lugares()?->permite($municipio)) {
            $this->municipio_id = $municipio;
            $this->departamento_id = \App\Models\Municipio::whereKey($municipio)->value('departamento_id');
        }
        if (request()->query('estado') === 'planeado') {
            $this->estado = 'planeado';
            // Por defecto, mañana a las 9:00
            $this->fecha = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        }
        if ($lugar = trim((string) request()->query('lugar'))) {
            $this->lugar = mb_substr($lugar, 0, 150);
        }
    }

    public function updatedDepartamentoId(): void
    {
        $this->municipio_id = null;
        $this->ajustarLugar();
    }

    public function quitarArchivo(int $i): void
    {
        unset($this->archivos[$i]);
        $this->archivos = array_values($this->archivos);
    }

    /** "1.200.000" o "1200000,50" => "1200000.50"; vacío => null. */
    public static function monto(string $texto): ?string
    {
        $t = preg_replace('/[^\d,.]/', '', trim($texto));
        if ($t === '') {
            return null;
        }
        // Los puntos son de miles; la coma, decimal
        $t = str_replace(['.', ','], ['', '.'], $t);

        return is_numeric($t) ? $t : 'x';
    }

    protected function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $delEquipo = Rule::exists('equipo_campana', 'id')->where('tenant_id', $tenantId);

        return [
            'titulo' => ['required', 'string', 'min:3', 'max:150'],
            'tipo' => ['required', Rule::in(array_keys(EventoCampana::TIPOS))],
            'estado' => ['required', Rule::in(array_keys(EventoCampana::ESTADOS))],
            'fecha' => ['required', 'date'],
            'municipio_id' => ['required', 'integer'],
            'lugar' => ['nullable', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'coordenadas' => ['nullable', 'regex:/^\s*-?\d{1,2}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?\s*$/'],
            'asistio_candidato' => ['boolean'],
            'responsable_id' => ['nullable', 'integer', $delEquipo],
            'integrantes' => ['array', 'max:50'],
            'integrantes.*' => ['integer', $delEquipo],
            'asistentes' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'monto_asignado' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'monto_gastado' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'resultado' => ['nullable', 'string', 'max:5000'],
            'archivos' => ['array', 'max:'.self::MAX_ARCHIVOS],
            // Sin SVG ni HTML: se sirven desde nuestro dominio
            'archivos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    protected function messages(): array
    {
        return [
            'titulo.required' => 'Escribe qué se hizo (p. ej. "Reunión con líderes de la vereda").',
            'municipio_id.required' => 'Elige el municipio donde fue el evento.',
            'coordenadas.regex' => 'Escribe latitud y longitud separadas por coma, como 4.1420, -73.6266.',
            'monto_asignado.numeric' => 'Escribe el monto solo con números (p. ej. 500000).',
            'monto_gastado.numeric' => 'Escribe el monto solo con números (p. ej. 450000).',
            'archivos.*.mimes' => 'Las evidencias deben ser fotos (JPG, PNG, WEBP) o PDF.',
            'archivos.*.max' => 'Cada archivo puede pesar hasta 10 MB.',
            'archivos.max' => 'Máximo '.self::MAX_ARCHIVOS.' archivos por vez.',
        ];
    }

    public function guardar()
    {
        $this->autorizar();

        if (! $this->exigirTenant()) {
            return null;
        }

        $lugares = $this->lugares();
        if (! $lugares?->completo()) {
            $this->addError('municipio_id', 'Completa el cargo y el lugar del candidato antes de registrar eventos.');

            return null;
        }

        $this->monto_asignado = (string) self::monto($this->monto_asignado);
        $this->monto_gastado = (string) self::monto($this->monto_gastado);
        $data = $this->validate();

        if (! $lugares->permite($data['municipio_id'])) {
            $this->addError('municipio_id', 'Ese municipio está fuera de la circunscripción del candidato.');

            return null;
        }

        $asignado = (float) ($data['monto_asignado'] ?: 0);
        // El dinero siempre queda a nombre de alguien: el responsable o, si fue
        // en persona y no se eligió a nadie, el candidato
        if ($asignado > 0 && ! $data['responsable_id'] && ! $data['asistio_candidato']) {
            $this->addError('responsable_id', 'Elige a quién se le asignó el dinero, o marca que el candidato estuvo en persona.');

            return null;
        }

        [$lat, $lng] = $data['coordenadas'] ? array_map('floatval', explode(',', $data['coordenadas'])) : [null, null];
        if ($lat !== null && (abs($lat) > 90 || abs($lng) > 180)) {
            $this->addError('coordenadas', 'Esas coordenadas no existen.');

            return null;
        }

        $valores = [
            'titulo' => trim($data['titulo']),
            'tipo' => $data['tipo'],
            'estado' => $data['estado'],
            'fecha' => $data['fecha'],
            'municipio_id' => $data['municipio_id'],
            'departamento_id' => \App\Models\Municipio::whereKey($data['municipio_id'])->value('departamento_id'),
            'lugar' => trim($data['lugar']) ?: null,
            'direccion' => trim($data['direccion']) ?: null,
            'latitud' => $lat,
            'longitud' => $lng,
            'asistio_candidato' => $data['asistio_candidato'],
            'responsable_id' => $data['responsable_id'] ?: null,
            'asistentes' => $data['asistentes'],
            'monto_asignado' => $asignado,
            'monto_gastado' => $data['monto_gastado'] !== '' ? (float) $data['monto_gastado'] : null,
            'descripcion' => trim($data['descripcion']) ?: null,
            'resultado' => trim($data['resultado']) ?: null,
        ];

        if ($this->evento) {
            $evento = $this->evento;
            $evento->update($valores);
        } else {
            $evento = EventoCampana::create($valores + ['creado_por' => auth()->id()]);
        }

        // El responsable no se repite entre los "otros integrantes"
        $evento->integrantes()->sync(array_values(array_diff(array_map('intval', $data['integrantes']), [(int) $valores['responsable_id']])));
        $this->guardarEvidencias($evento);

        session()->flash('success', $this->evento ? 'Evento actualizado.' : 'Evento registrado.');

        return $this->redirectRoute('eventos.show', $evento);
    }

    /** Archivos al disco privado: eventos/{tenant}/{evento}/... */
    private function guardarEvidencias(EventoCampana $evento): void
    {
        foreach ($this->archivos as $archivo) {
            $mime = $archivo->getMimeType();
            $ruta = $archivo->store("eventos/{$evento->tenant_id}/{$evento->id}", EvidenciaEvento::DISCO);

            $evento->evidencias()->create([
                'tenant_id' => $evento->tenant_id,
                'tipo' => str_starts_with((string) $mime, 'image/') ? 'foto' : 'documento',
                'ruta' => $ruta,
                'nombre' => mb_substr($archivo->getClientOriginalName(), 0, 190),
                'mime' => $mime,
                'tamano' => $archivo->getSize(),
                'subido_por' => auth()->id(),
            ]);
        }
        $this->archivos = [];
    }

    public function render()
    {
        $lugares = $this->lugares();
        $tenant = app(TenantContext::class)->tenant();

        return view('livewire.eventos.formulario', [
            'tenant' => $tenant,
            'lugares' => $lugares,
            'departamentos' => $lugares?->departamentos() ?? collect(),
            'municipios' => $lugares?->municipios($this->departamento_id) ?? collect(),
            // Activos, más los inactivos que ya estaban en este evento
            'equipo' => $tenant ? IntegranteEquipo::query()
                ->where(fn ($q) => $q->where('activo', true)
                    ->orWhereIn('id', array_filter([$this->responsable_id, ...$this->integrantes])))
                ->orderBy('nombre')->get() : collect(),
            'evidencias' => $this->evento?->evidencias()->latest()->get() ?? collect(),
        ])->title($this->evento ? 'Editar evento' : 'Nuevo evento');
    }
}
