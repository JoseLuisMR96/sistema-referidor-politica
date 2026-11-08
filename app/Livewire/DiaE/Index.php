<?php

namespace App\Livewire\DiaE;

use App\Enums\CargoEnum;
use App\Livewire\Concerns\RequiereTenant;
use App\Models\Eleccion;
use App\Models\PuestoVotacion;
use App\Models\Testigo;
use App\Services\Territorio\ResultadosDiaE;
use App\Support\LugaresCampana;
use App\Support\PhoneNumber;
use App\Support\TenantContext;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Día de elecciones: elección que se vigila, testigos con su puesto, mesas y
 * enlace para reportar, y el conteo propio en vivo.
 */
class Index extends Component
{
    use RequiereTenant;

    #[Url(as: 'e')]
    public ?int $eleccionId = null;

    #[Url]
    public string $vista = 'resultados';

    // --- Modal de elección ---
    public bool $modalEleccion = false;

    public array $eleccion = [];

    /** Una opción por línea: "Nombre | Partido"; con * al inicio, la del candidato */
    public string $opcionesTexto = '';

    // --- Modal de testigo ---
    public bool $modalTestigo = false;

    public ?int $testigoId = null;

    public array $testigo = [];

    private function puede(): bool
    {
        return auth()->user()->can('diae.gestionar');
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->can('diae.ver'), 403);
    }

    // ===================== Elección =====================

    public function abrirEleccion(bool $nueva = false): void
    {
        abort_unless($this->puede(), 403);
        $this->resetValidation();
        $e = $nueva ? null : Eleccion::find($this->eleccionId);
        $tenant = app(TenantContext::class)->tenant();

        $this->eleccion = [
            'id' => $e?->id,
            'nombre' => $e?->nombre ?? ($tenant?->cargo ? $tenant->cargo->label().' '.now()->year : ''),
            'cargo' => $e?->cargo ?? $tenant?->cargo?->value ?? 'gobernacion',
            'fecha' => ($e?->fecha ?? now())->format('Y-m-d'),
            'abierta' => $e?->abierta ?? true,
        ];
        $this->opcionesTexto = $e
            ? $e->opciones->map(fn ($o) => ($o->propia ? '* ' : '').$o->nombre.($o->partido ? ' | '.$o->partido : ''))->implode("\n")
            : ($tenant ? "* {$tenant->nombre}".($tenant->partido ? " | {$tenant->partido}" : '') : '');
        $this->modalEleccion = true;
    }

    /** @return array<int, array{nombre:string, partido:?string, propia:bool}> */
    public static function opcionesDe(string $texto): array
    {
        $out = [];
        foreach (preg_split('/\R/', $texto) as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            $propia = str_starts_with($linea, '*');
            [$nombre, $partido] = array_pad(array_map('trim', explode('|', ltrim($linea, '* '), 2)), 2, null);
            if ($nombre !== '') {
                $out[] = ['nombre' => mb_substr($nombre, 0, 150), 'partido' => $partido ? mb_substr($partido, 0, 150) : null, 'propia' => $propia];
            }
        }

        return $out;
    }

    public function guardarEleccion(): void
    {
        abort_unless($this->puede(), 403);
        if (! $this->exigirTenant()) {
            return;
        }

        $this->validate([
            'eleccion.nombre' => ['required', 'string', 'min:3', 'max:150'],
            'eleccion.cargo' => ['required', Rule::enum(CargoEnum::class)],
            'eleccion.fecha' => ['required', 'date'],
            'eleccion.abierta' => ['boolean'],
        ], ['eleccion.nombre.required' => 'Ponle un nombre a la elección.']);

        $opciones = self::opcionesDe($this->opcionesTexto);
        if (count($opciones) < 1 || count($opciones) > 60) {
            $this->addError('opcionesTexto', 'Escribe entre 1 y 60 candidatos o listas, uno por línea.');

            return;
        }

        $valores = collect($this->eleccion)->only(['nombre', 'cargo', 'fecha', 'abierta'])->all();
        $e = $this->eleccion['id'] ? tap(Eleccion::findOrFail($this->eleccion['id']))->update($valores) : Eleccion::create($valores);

        // Se actualizan por posición: así no se pierden los votos ya reportados por opción
        $existentes = $e->opciones()->get()->values();
        foreach ($opciones as $i => $o) {
            if ($ex = $existentes[$i] ?? null) {
                $ex->update($o + ['orden' => $i]);
            } else {
                $e->opciones()->create($o + ['orden' => $i]);
            }
        }
        foreach ($existentes->slice(count($opciones)) as $sobra) {
            $sobra->delete();
        }

        $this->eleccionId = $e->id;
        $this->modalEleccion = false;
        session()->flash('success', 'Elección guardada.');
    }

    // ===================== Testigos =====================

    public function abrirTestigo(?int $id = null): void
    {
        abort_unless($this->puede(), 403);
        $this->resetValidation();
        $t = $id ? Testigo::with('puesto.municipio')->findOrFail($id) : null;
        $lugares = LugaresCampana::para(app(TenantContext::class)->tenant());

        $this->testigoId = $t?->id;
        $this->testigo = [
            'nombre' => (string) $t?->nombre,
            'telefono' => (string) $t?->telefono,
            'departamento_id' => $t?->puesto?->municipio?->departamento_id ?? $lugares->departamentoFijo(),
            'municipio_id' => $t?->puesto?->municipio_id ?? $lugares->municipioFijo(),
            'puesto_id' => $t?->puesto_id,
            'mesas' => (string) $t?->mesas,
            'activo' => $t?->activo ?? true,
        ];
        $this->modalTestigo = true;
    }

    public function updatedTestigoDepartamentoId(): void
    {
        $this->testigo['municipio_id'] = null;
        $this->testigo['puesto_id'] = null;
    }

    public function updatedTestigoMunicipioId(): void
    {
        $this->testigo['puesto_id'] = null;
    }

    public function guardarTestigo(): void
    {
        abort_unless($this->puede(), 403);
        if (! $this->exigirTenant() || ! $this->eleccionId) {
            return;
        }

        $this->validate([
            'testigo.nombre' => ['required', 'string', 'min:3', 'max:150'],
            'testigo.telefono' => ['nullable', 'string', 'max:20'],
            'testigo.puesto_id' => ['required', 'integer', 'exists:puestos_votacion,id'],
            'testigo.mesas' => ['required', 'string', 'max:120'],
            'testigo.activo' => ['boolean'],
        ], [
            'testigo.nombre.required' => 'Escribe el nombre del testigo.',
            'testigo.puesto_id.required' => 'Elige el puesto de votación.',
            'testigo.mesas.required' => 'Escribe las mesas (p. ej. 1-5, 8).',
        ]);

        $puesto = PuestoVotacion::findOrFail($this->testigo['puesto_id']);
        $tenant = app(TenantContext::class)->tenant();
        if (! LugaresCampana::para($tenant)->permite($puesto->municipio_id)) {
            $this->addError('testigo.puesto_id', 'Ese puesto está fuera de la circunscripción del candidato.');

            return;
        }
        if (Testigo::mesasDe($this->testigo['mesas'], $puesto->mesas ?: 300) === null) {
            $this->addError('testigo.mesas', 'Mesas no válidas'.($puesto->mesas ? " (el puesto tiene {$puesto->mesas})" : '').': usa números y rangos, como 1-5, 8.');

            return;
        }

        $valores = [
            'eleccion_id' => $this->eleccionId,
            'nombre' => trim($this->testigo['nombre']),
            'telefono' => $this->testigo['telefono'] !== '' ? PhoneNumber::digits($this->testigo['telefono']) : null,
            'puesto_id' => $puesto->id,
            'mesas' => preg_replace('/\s+/', ' ', trim($this->testigo['mesas'])),
            'activo' => (bool) $this->testigo['activo'],
        ];
        $this->testigoId ? Testigo::findOrFail($this->testigoId)->update($valores) : Testigo::create($valores);

        $this->modalTestigo = false;
        session()->flash('success', 'Testigo guardado. Envíale su enlace para reportar.');
    }

    /** Un enlace filtrado se corta: se genera otro y el anterior deja de servir. */
    public function nuevoEnlace(int $id): void
    {
        abort_unless($this->puede(), 403);
        Testigo::findOrFail($id)->update(['token' => \Illuminate\Support\Str::random(40)]);
        session()->flash('success', 'Enlace nuevo generado: el anterior ya no sirve.');
    }

    public function eliminarTestigo(int $id): void
    {
        abort_unless($this->puede(), 403);
        $t = Testigo::findOrFail($id);
        // Si ya reportó, se desactiva: sus reportes se conservan
        $t->reportes()->exists() ? $t->update(['activo' => false]) : $t->delete();
    }

    public function render(ResultadosDiaE $resultados)
    {
        $tenant = app(TenantContext::class)->tenant();
        if (! $tenant) {
            return view('livewire.dia-e.index', ['tenant' => null])->title('Día de elecciones');
        }

        $elecciones = Eleccion::query()->orderByDesc('fecha')->get();
        if (! $elecciones->contains('id', $this->eleccionId)) {
            $this->eleccionId = $elecciones->first()?->id;
        }
        $actual = $elecciones->firstWhere('id', $this->eleccionId);
        $lugares = LugaresCampana::para($tenant);
        $municipioTestigo = $this->testigo['municipio_id'] ?? null;

        return view('livewire.dia-e.index', [
            'tenant' => $tenant,
            'puede' => $this->puede(),
            'elecciones' => $elecciones,
            'actual' => $actual,
            'r' => $actual && $this->vista === 'resultados' ? $resultados->calcular($actual) : null,
            'testigos' => $actual && $this->vista === 'testigos'
                ? $actual->testigos()->with(['puesto.municipio:id,nombre'])->withCount('reportes')->orderBy('nombre')->get() : collect(),
            // Cargos nacionales eligen primero el departamento
            'departamentos' => $this->modalTestigo && $lugares->departamentoFijo() === null ? $lugares->departamentos() : collect(),
            'municipios' => $this->modalTestigo ? $lugares->municipios($lugares->departamentoFijo() ?? ($this->testigo['departamento_id'] ?? null)) : collect(),
            'puestos' => $this->modalTestigo && $municipioTestigo
                ? PuestoVotacion::where('municipio_id', $municipioTestigo)->orderBy('zona')->orderBy('codigo')->get(['id', 'nombre', 'zona', 'codigo', 'mesas'])
                : collect(),
            'cargos' => CargoEnum::cases(),
        ])->title('Día de elecciones');
    }
}
