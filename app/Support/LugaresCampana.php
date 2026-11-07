<?php

namespace App\Support;

use App\Enums\CargoEnum;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Dónde puede registrar eventos un candidato, según el ámbito de su cargo:
 *
 * - municipal (alcaldía, concejo, JAL): solo su municipio;
 * - departamental (gobernación, asamblea, Cámara): los municipios de su departamento;
 * - nacional (Senado, Presidencia): cualquier municipio del país.
 *
 * Un candidato sin cargo o sin lugar completo no puede registrar eventos.
 */
class LugaresCampana
{
    public function __construct(private Tenant $tenant) {}

    public static function para(Tenant $tenant): self
    {
        return new self($tenant);
    }

    public function ambito(): ?string
    {
        return $this->tenant->ambito();
    }

    public function completo(): bool
    {
        return $this->ambito() !== null;
    }

    /** Departamento fijo (municipal y departamental) o null si se elige (nacional). */
    public function departamentoFijo(): ?int
    {
        return $this->ambito() === CargoEnum::AMBITO_NACIONAL ? null : $this->tenant->departamento_id;
    }

    /** Municipio fijo (solo en cargos municipales). */
    public function municipioFijo(): ?int
    {
        return $this->ambito() === CargoEnum::AMBITO_MUNICIPAL ? $this->tenant->municipio_id : null;
    }

    public function departamentos(): Collection
    {
        if (! $this->completo()) {
            return collect();
        }

        return Departamento::query()
            ->when($this->departamentoFijo(), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('nombre')->get(['id', 'nombre']);
    }

    /** Municipios que se pueden elegir dentro de un departamento. */
    public function municipios(?int $departamentoId): Collection
    {
        if (! $this->completo()) {
            return collect();
        }
        if ($id = $this->municipioFijo()) {
            return Municipio::whereKey($id)->get(['id', 'nombre', 'departamento_id']);
        }

        $departamentoId = $this->departamentoFijo() ?? $departamentoId;

        return $departamentoId
            ? Municipio::where('departamento_id', $departamentoId)->orderBy('nombre')->get(['id', 'nombre', 'departamento_id'])
            : collect();
    }

    /** ¿El municipio está dentro de la circunscripción del candidato? */
    public function permite(?int $municipioId): bool
    {
        if (! $municipioId || ! $this->completo()) {
            return false;
        }
        if ($id = $this->municipioFijo()) {
            return $municipioId === $id;
        }

        $municipio = Municipio::find($municipioId, ['id', 'departamento_id']);

        return $municipio && ($this->departamentoFijo() === null || $municipio->departamento_id === $this->departamentoFijo());
    }

    /** Cuántos municipios abarca la circunscripción (para "visitados X de Y"). */
    public function totalMunicipios(): ?int
    {
        return match ($this->ambito()) {
            CargoEnum::AMBITO_MUNICIPAL => 1,
            CargoEnum::AMBITO_DEPARTAMENTAL => Municipio::where('departamento_id', $this->tenant->departamento_id)->count(),
            default => null,
        };
    }
}
