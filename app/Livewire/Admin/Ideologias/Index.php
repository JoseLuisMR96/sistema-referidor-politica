<?php

namespace App\Livewire\Admin\Ideologias;

use App\Enums\CargoEnum;
use App\Models\Departamento;
use App\Models\PartidoIdeologia;
use App\Services\Historico\AnalisisElectoral;
use App\Services\Historico\HistoricoElectoralStats;
use App\Support\TenantContext;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Ideología de los partidos (tabla global partidos_ideologia), que usan la
 * tendencia política, la disputa y las prioridades del Análisis electoral.
 *
 * Los partidos salen del histórico de un departamento y cargo. Cualquiera con
 * historico.ver consulta; solo los usuarios globales con historico.importar
 * clasifican (la tabla es de todos los candidatos).
 */
class Index extends Component
{
    public const ESTADOS = [
        'todos' => 'Todos',
        'sin_clasificar' => 'Sin clasificar',
        'inferidos' => 'Inferidos por sus partidos',
        'clasificados' => 'Clasificados',
    ];

    #[Url(as: 'depto')]
    public ?int $departamentoId = null;

    #[Url]
    public ?string $cargo = null;

    #[Url]
    public string $estado = 'todos';

    #[Url(as: 'q')]
    public string $search = '';

    // Modal de clasificación
    public bool $modal = false;

    public string $clave = '';

    public string $ideologia = '';

    public string $nota = '';

    /** El modal se abrió sin partido: se elige ahí entre los sin clasificar */
    public bool $elegirPartido = false;

    public function mount(AnalisisElectoral $analisis): void
    {
        abort_unless(auth()->user()->can('historico.ver'), 403);

        $tenant = app(TenantContext::class)->tenant();
        $this->departamentoId ??= $tenant?->departamento_id
            ?? Departamento::where('nombre', 'META')->value('id')
            ?? Departamento::orderBy('nombre')->value('id');
        $this->ajustarCargo($analisis, $tenant?->cargo?->value);
    }

    private function ajustarCargo(AnalisisElectoral $analisis, ?string $preferido = null): void
    {
        $cargos = $this->departamentoId ? $analisis->cargos($this->departamentoId) : [];
        if (! in_array($this->cargo, $cargos, true)) {
            $this->cargo = collect([$preferido, 'gobernacion', 'alcaldia'])->first(fn ($c) => in_array($c, $cargos, true)) ?? ($cargos[0] ?? null);
        }
    }

    public function updatedDepartamentoId(): void
    {
        $this->ajustarCargo(app(AnalisisElectoral::class));
    }

    public function puedeClasificar(): bool
    {
        $user = auth()->user();

        return $user->esGlobal() && $user->can('historico.importar');
    }

    /** Abre el modal; sin clave, para elegir el partido en el modal. */
    public function abrir(string $clave = ''): void
    {
        abort_unless($this->puedeClasificar(), 403);

        $guardada = $clave !== '' ? PartidoIdeologia::where('clave', $clave)->first() : null;
        $this->clave = $clave;
        $this->elegirPartido = $clave === '';
        $this->ideologia = (string) $guardada?->ideologia;
        $this->nota = (string) $guardada?->nota;
        $this->resetValidation();
        $this->modal = true;
    }

    public function cerrar(): void
    {
        $this->reset(['modal', 'clave', 'ideologia', 'nota', 'elegirPartido']);
        $this->resetValidation();
    }

    public function guardar(): void
    {
        abort_unless($this->puedeClasificar(), 403);

        $data = $this->validate([
            'clave' => ['required', 'string', 'max:191'],
            'ideologia' => ['required', Rule::in(array_keys(PartidoIdeologia::IDEOLOGIAS))],
            'nota' => ['nullable', 'string', 'max:500'],
        ], [
            'clave.required' => 'Elige el partido.',
            'ideologia.required' => 'Elige la ideología.',
        ]);

        PartidoIdeologia::updateOrCreate(['clave' => $data['clave']], [
            'ideologia' => $data['ideologia'],
            'nota' => trim((string) $data['nota']) ?: null,
        ]);

        session()->flash('success', "\"{$data['clave']}\" quedó como ".PartidoIdeologia::IDEOLOGIAS[$data['ideologia']][0].'.');
        $this->cerrar();
    }

    public function quitar(string $clave): void
    {
        abort_unless($this->puedeClasificar(), 403);

        PartidoIdeologia::where('clave', $clave)->delete();
        session()->flash('success', "Se quitó la clasificación de \"{$clave}\".");
    }

    public function render(AnalisisElectoral $analisis)
    {
        $guardadas = PartidoIdeologia::query()->get()->keyBy('clave');
        $mapa = $guardadas->map->ideologia->all();
        $partidos = $this->departamentoId && $this->cargo
            ? $analisis->datos($this->departamentoId, $this->cargo)['partidos']
            : [];

        $filas = collect($partidos)
            ->reject(fn ($p, $clave) => $clave === 'VOTO EN BLANCO')
            ->map(fn ($p, $clave) => [
                'clave' => $clave,
                'nombre' => $p['nombre'],
                'votos' => $p['votos'],
                'ideologia' => $mapa[$clave] ?? null,
                'nota' => $guardadas[$clave]->nota ?? null,
                'inferida' => isset($mapa[$clave]) ? null : AnalisisElectoral::inferir($p['nombre'], $mapa),
            ]);

        // "Clasificados" muestra toda la tabla guardada, también los que no están en este cargo
        if ($this->estado === 'clasificados') {
            $filas = $guardadas->map(fn ($g) => $filas->get($g->clave) ?? [
                'clave' => $g->clave, 'nombre' => $g->clave, 'votos' => null,
                'ideologia' => $g->ideologia, 'nota' => $g->nota, 'inferida' => null,
            ]);
        }

        $buscar = HistoricoElectoralStats::normalizar(trim($this->search));
        $filtradas = $filas
            ->filter(fn ($f) => match ($this->estado) {
                'sin_clasificar' => ! $f['ideologia'] && ! $f['inferida'],
                'inferidos' => ! $f['ideologia'] && $f['inferida'],
                'clasificados' => (bool) $f['ideologia'],
                default => true,
            })
            ->filter(fn ($f) => $buscar === '' || str_contains(HistoricoElectoralStats::normalizar($f['nombre'].' '.$f['clave']), $buscar))
            ->sortBy(fn ($f) => [$f['ideologia'] || $f['inferida'] ? 1 : 0, -($f['votos'] ?? 0)])
            ->values();

        return view('livewire.admin.ideologias.index', [
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
            'cargos' => collect($this->departamentoId ? $analisis->cargos($this->departamentoId) : [])->map(fn ($c) => CargoEnum::from($c)),
            'filas' => $filtradas->take(150),
            'total' => $filtradas->count(),
            'conteo' => [
                'sin_clasificar' => $filas->filter(fn ($f) => ! $f['ideologia'] && ! $f['inferida'])->count(),
                'inferidos' => $filas->filter(fn ($f) => ! $f['ideologia'] && $f['inferida'])->count(),
                'clasificados' => $guardadas->count(),
            ],
            // Para el modal sin partido elegido: los de este cargo que aún no tienen clasificación
            'opciones' => $filas->filter(fn ($f) => ! $f['ideologia'])->sortByDesc('votos')->take(200)->values(),
        ])->title('Ideología de los partidos');
    }
}
