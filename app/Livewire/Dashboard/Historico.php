<?php

namespace App\Livewire\Dashboard;

use App\Enums\CargoEnum;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Services\Historico\HistoricoElectoralStats;
use App\Services\Historico\ImportadorHistorico;
use App\Support\TenantContext;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Histórico electoral: quién ganó en cada departamento y municipio, por año
 * y cargo, con mapa. Los datos son públicos y globales (no por candidato);
 * con un candidato elegido se cruzan con sus registros.
 */
class Historico extends Component
{
    use WithFileUploads;

    #[Url]
    public ?int $anio = null;

    #[Url]
    public ?string $cargo = null;

    #[Url(as: 'depto')]
    public ?int $departamentoId = null;

    #[Url(as: 'mpio')]
    public ?int $municipioId = null;

    #[Url]
    public ?string $comuna = null;

    // Solo presidencia: 1.ª o 2.ª vuelta
    #[Url]
    public int $vuelta = 1;

    // Importación (solo usuarios globales con historico.importar)
    public $archivo;

    public ?int $anioImportar = null;

    public ?string $cargoImportar = null;

    public ?int $vueltaImportar = null;

    public ?array $resumenImportacion = null;

    public function mount(HistoricoElectoralStats $stats): void
    {
        abort_unless(auth()->user()->can('historico.ver'), 403);

        $tenant = app(TenantContext::class)->tenant();
        $anios = $stats->anios();

        $this->anio ??= $anios[0] ?? null;
        $this->departamentoId ??= $tenant?->departamento_id;

        $cargos = array_map(fn (CargoEnum $c) => $c->value, $stats->cargos($this->anio));
        if (! in_array($this->cargo, $cargos, true)) {
            $this->cargo = in_array($tenant?->cargo?->value, $cargos, true)
                ? $tenant->cargo->value
                : ($cargos[0] ?? null);
        }
    }

    public function updatedAnio(): void
    {
        $cargos = array_map(fn (CargoEnum $c) => $c->value, app(HistoricoElectoralStats::class)->cargos($this->anio));
        if (! in_array($this->cargo, $cargos, true)) {
            $this->cargo = $cargos[0] ?? null;
        }
        $this->comuna = null;
        $this->vuelta = 1;
    }

    public function updatedCargo(): void
    {
        $this->comuna = null;
        $this->vuelta = 1;
    }

    public function updatedDepartamentoId(): void
    {
        $this->municipioId = null;
        $this->comuna = null;
    }

    public function updatedMunicipioId(): void
    {
        $this->comuna = null;
    }

    // Clic en un municipio del mapa
    public function verMunicipio(int $id): void
    {
        $this->municipioId = Municipio::where('departamento_id', $this->departamentoId)->whereKey($id)->exists() ? $id : null;
        $this->comuna = null;
    }

    public function puedeImportar(): bool
    {
        $user = auth()->user();

        return $user->esGlobal() && $user->can('historico.importar');
    }

    public function importar(ImportadorHistorico $importador): void
    {
        abort_unless($this->puedeImportar(), 403);

        $this->validate([
            'archivo' => 'required|file|max:51200|mimes:csv,txt,xlsx,xls',
            'anioImportar' => 'nullable|integer|min:1950|max:2100',
            'vueltaImportar' => 'nullable|in:1,2',
            'cargoImportar' => 'nullable|in:'.implode(',', array_map(fn ($c) => $c->value, CargoEnum::cases())),
        ], [], ['archivo' => 'archivo', 'anioImportar' => 'año', 'cargoImportar' => 'cargo']);

        $this->resumenImportacion = $importador->importar(
            $this->archivo->getRealPath(),
            $this->anioImportar,
            $this->cargoImportar,
            $this->archivo->getClientOriginalName(),
            $this->vueltaImportar,
        );

        $this->reset('archivo');

        if ($this->resumenImportacion['insertadas'] > 0) {
            $this->anio = $this->anioImportar ?? $this->anio ?? app(HistoricoElectoralStats::class)->anios()[0] ?? null;
            $this->mount(app(HistoricoElectoralStats::class));
        }
    }

    public function render(HistoricoElectoralStats $stats)
    {
        $cargoEnum = $this->cargo ? CargoEnum::tryFrom($this->cargo) : null;
        $departamento = $this->departamentoId ? Departamento::find($this->departamentoId) : null;
        $municipio = $this->municipioId ? Municipio::find($this->municipioId) : null;
        $tenant = app(TenantContext::class)->tenant();

        $vueltas = $this->anio && $cargoEnum ? $stats->vueltas($this->anio, $cargoEnum->value) : [];
        if (! in_array($this->vuelta, $vueltas, true)) {
            $this->vuelta = 1;
        }
        $stats->vuelta = $this->vuelta;

        $ganadores = [];
        $porDepartamento = [];
        $contienda = [];
        $comunas = [];
        $registros = null;

        if ($this->anio && $cargoEnum) {
            if ($departamento) {
                $ganadores = $stats->ganadoresPorMunicipio($this->anio, $cargoEnum->value, $departamento->id);
                $registros = $stats->registrosCampania();

                // La contienda: departamental = todo el depto; municipal = el municipio elegido
                if ($cargoEnum->ambito() !== CargoEnum::AMBITO_MUNICIPAL || $municipio) {
                    if ($cargoEnum === CargoEnum::JAL && $municipio) {
                        $comunas = $stats->comunas($this->anio, $cargoEnum->value, $municipio->id);
                    }
                    $contienda = $stats->contienda($this->anio, $cargoEnum->value, $departamento->id, $municipio?->id, $this->comuna);
                }
            } elseif ($cargoEnum->ambito() === CargoEnum::AMBITO_MUNICIPAL) {
                $ganadores = $stats->ganadoresPorMunicipio($this->anio, $cargoEnum->value);
            } else {
                $porDepartamento = $stats->ganadoresPorDepartamento($this->anio, $cargoEnum->value);

                // Presidencia y Senado: resultado de todo el país
                if ($cargoEnum->ambito() === CargoEnum::AMBITO_NACIONAL) {
                    $contienda = $stats->contienda($this->anio, $cargoEnum->value, null);
                }
            }
        }

        $mapa = $departamento ? [
            'geo' => asset('data/deptos/'.rawurlencode($departamento->nombre).'.geojson'),
            'municipios' => collect($ganadores)
                ->filter(fn ($g) => $g['codigo_dane'])
                ->mapWithKeys(fn ($g) => [$g['codigo_dane'] => [
                    'id' => $g['municipio_id'],
                    'nombre' => $g['municipio'],
                    'color' => $g['color'],
                    'partido' => $g['partido'],
                    'candidato' => $g['candidato'],
                    'votos' => $g['votos'],
                    'porcentaje' => $g['porcentaje'],
                    'registros' => $registros[$g['municipio_id']] ?? null,
                ]])->all(),
            'seleccionado' => $municipio?->codigo_dane,
        ] : null;

        $this->dispatch('historico-mapa', mapa: $mapa);

        return view('livewire.dashboard.historico', [
            'anios' => $stats->anios(),
            'vueltas' => $vueltas,
            'cargos' => $stats->cargos($this->anio),
            'cargoEnum' => $cargoEnum,
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
            'municipios' => $departamento
                ? Municipio::where('departamento_id', $departamento->id)->orderBy('nombre')->get(['id', 'nombre'])
                : collect(),
            'departamento' => $departamento,
            'municipio' => $municipio,
            'ganadores' => $ganadores,
            'partidos' => $stats->partidosGanadores($ganadores ?: $porDepartamento),
            'porDepartamento' => $porDepartamento,
            'contienda' => $contienda,
            'comunas' => $comunas,
            'registros' => $registros,
            'mapa' => $mapa,
            'partidoCandidato' => $tenant?->partido ? HistoricoElectoralStats::normalizar($tenant->partido) : null,
            'todosLosCargos' => CargoEnum::cases(),
        ])->title('Histórico electoral');
    }
}
