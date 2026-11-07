<?php

namespace App\Livewire\Eventos;

use App\Enums\CargoEnum;
use App\Models\EventoCampana;
use App\Models\IntegranteEquipo;
use App\Models\Municipio;
use App\Support\LugaresCampana;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Eventos de campaña del candidato elegido: totales, mapa de dónde se ha
 * estado, resumen por municipio y por persona, y el listado con filtros.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'mun')]
    public ?int $municipioId = null;

    #[Url]
    public string $tipo = '';

    #[Url]
    public string $estado = '';

    /** candidato = fue el candidato; equipo = fue alguien en su nombre */
    #[Url]
    public string $quien = '';

    #[Url]
    public ?int $persona = null;

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('eventos.ver'), 403);
    }

    public function updating($propiedad): void
    {
        if ($propiedad !== 'paginators') {
            $this->resetPage();
        }
    }

    public function limpiar(): void
    {
        $this->reset(['search', 'municipioId', 'tipo', 'estado', 'quien', 'persona', 'desde', 'hasta']);
        $this->resetPage();
    }

    private function filtrados(): Builder
    {
        $buscar = trim($this->search);

        return EventoCampana::query()
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('titulo', 'like', "%{$buscar}%")
                ->orWhere('lugar', 'like', "%{$buscar}%")
                ->orWhere('direccion', 'like', "%{$buscar}%")))
            ->when($this->municipioId, fn ($q, $id) => $q->where('municipio_id', $id))
            ->when($this->tipo !== '', fn ($q) => $q->where('tipo', $this->tipo))
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado))
            ->when($this->quien === 'candidato', fn ($q) => $q->where('asistio_candidato', true))
            ->when($this->quien === 'equipo', fn ($q) => $q->where('asistio_candidato', false))
            ->when($this->persona, fn ($q, $id) => $q->where(fn ($w) => $w->where('responsable_id', $id)
                ->orWhereHas('integrantes', fn ($i) => $i->where('equipo_campana.id', $id))))
            ->when($this->desde !== '', fn ($q) => $q->where('fecha', '>=', $this->desde.' 00:00:00'))
            ->when($this->hasta !== '', fn ($q) => $q->where('fecha', '<=', $this->hasta.' 23:59:59'));
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->tenant();
        if (! $tenant) {
            return view('livewire.eventos.index', ['tenant' => null])->title('Eventos de campaña');
        }

        $lugares = LugaresCampana::para($tenant);
        $base = $this->filtrados();
        // Lo cancelado no cuenta como presencia ni como gasto
        $hechos = (clone $base)->where('estado', '!=', 'cancelado');

        $totales = (clone $hechos)->selectRaw('COUNT(*) as eventos,
                COUNT(DISTINCT municipio_id) as municipios,
                SUM(CASE WHEN asistio_candidato = 1 THEN 1 ELSE 0 END) as con_candidato,
                COALESCE(SUM(monto_asignado), 0) as asignado,
                COALESCE(SUM(monto_gastado), 0) as gastado,
                COALESCE(SUM(asistentes), 0) as asistentes')
            ->toBase()->first();

        $porMunicipio = (clone $hechos)->toBase()
            ->selectRaw('municipio_id, COUNT(*) as eventos, MAX(fecha) as ultima,
                SUM(CASE WHEN asistio_candidato = 1 THEN 1 ELSE 0 END) as con_candidato,
                COALESCE(SUM(monto_asignado), 0) as asignado, COALESCE(SUM(monto_gastado), 0) as gastado')
            ->groupBy('municipio_id')->orderByDesc('eventos')->get();
        $municipios = Municipio::whereIn('id', $porMunicipio->pluck('municipio_id')->filter())->get(['id', 'nombre', 'codigo_dane'])->keyBy('id');

        $porPersona = (clone $hechos)->toBase()->whereNotNull('responsable_id')
            ->selectRaw('responsable_id, COUNT(*) as eventos,
                COALESCE(SUM(monto_asignado), 0) as asignado, COALESCE(SUM(monto_gastado), 0) as gastado')
            ->groupBy('responsable_id')->orderByDesc('asignado')->get();
        $equipo = IntegranteEquipo::orderBy('nombre')->get()->keyBy('id');

        $eventos = (clone $base)
            ->with(['municipio:id,nombre', 'responsable:id,nombre,rol'])
            ->withCount('evidencias')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(15);

        $mapa = $this->mapa($tenant, $lugares, $porMunicipio, $municipios, $hechos);
        $this->dispatch('eventos-mapa', mapa: $mapa);

        return view('livewire.eventos.index', [
            'tenant' => $tenant,
            'lugares' => $lugares,
            'totales' => $totales,
            'totalMunicipios' => $lugares->totalMunicipios(),
            'porMunicipio' => $porMunicipio,
            'municipios' => $municipios,
            'porPersona' => $porPersona,
            'equipo' => $equipo,
            'eventos' => $eventos,
            'opcionesMunicipio' => $lugares->ambito() === CargoEnum::AMBITO_NACIONAL
                ? Municipio::whereIn('id', EventoCampana::query()->distinct()->pluck('municipio_id'))->orderBy('nombre')->get(['id', 'nombre'])
                : $lugares->municipios(null),
            'mapa' => $mapa,
        ])->title('Eventos de campaña');
    }

    /**
     * Mapa del departamento: municipios más intensos con más eventos, y un
     * punto por cada evento con coordenadas. En cargos nacionales no hay mapa.
     */
    private function mapa($tenant, LugaresCampana $lugares, $porMunicipio, $municipios, Builder $hechos): ?array
    {
        if ($lugares->ambito() === CargoEnum::AMBITO_NACIONAL || ! $tenant->departamento) {
            return null;
        }

        $maximo = max(1, (int) $porMunicipio->max('eventos'));
        $pintar = [];
        foreach ($porMunicipio as $r) {
            $m = $municipios[$r->municipio_id] ?? null;
            if (! $m?->codigo_dane) {
                continue;
            }
            $pintar[$m->codigo_dane] = [
                'opacidad' => round(0.2 + 0.7 * $r->eventos / $maximo, 2),
                'lineas' => [$m->nombre, "{$r->eventos} evento(s)", $r->con_candidato ? "Con el candidato: {$r->con_candidato}" : 'Sin visita del candidato'],
            ];
        }

        $puntos = (clone $hechos)->whereNotNull('latitud')->whereNotNull('longitud')
            ->orderByDesc('fecha')->limit(300)
            ->get(['id', 'titulo', 'fecha', 'latitud', 'longitud', 'asistio_candidato'])
            ->map(fn ($e) => [
                'lat' => $e->latitud, 'lng' => $e->longitud, 'candidato' => $e->asistio_candidato,
                'texto' => $e->titulo.' · '.$e->fecha->format('d/m/Y'), 'url' => route('eventos.show', $e),
            ])->all();

        return [
            'geo' => asset('data/deptos-web/'.rawurlencode($tenant->departamento->nombre).'.geojson'),
            'municipios' => $pintar,
            'puntos' => $puntos,
        ];
    }
}
