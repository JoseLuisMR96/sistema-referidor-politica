<?php

namespace App\Livewire\Registrations;

use Livewire\Component;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\Referrer;

class Edit extends Component
{
    public PublicRegistration $publicRegistration;

    public string $full_name = '';
    public string $phone = '';
    public string $document_type = '';
    public string $document_number = '';
    public $age = null;
    public string $gender = '';
    public $referrer_id = null;

    // El municipio se edita por FK: el dashboard cuenta por FK y el texto
    // legacy solo cuando la FK es nula, así que editar solo el texto no
    // cambiaba nada en las métricas.
    public ?int $residence_department_id = null;
    public ?int $voting_department_id = null;
    public ?int $residence_municipality_id = null;
    public ?int $voting_municipality_id = null;

    // Texto legacy, solo informativo para registros viejos sin FK
    public string $legacy_residence = '';
    public string $legacy_voting = '';

    public function mount(PublicRegistration $publicRegistration): void
    {
        abort_unless(auth()->user()->can('registros.editar'), 403);

        $this->publicRegistration = $publicRegistration;

        $this->full_name = (string) $publicRegistration->full_name;
        $this->phone = (string) ($publicRegistration->phone ?? '');
        $this->document_type = (string) ($publicRegistration->document_type ?? '');
        $this->document_number = (string) $publicRegistration->document_number;
        $this->age = $publicRegistration->age;
        $this->gender = (string) ($publicRegistration->gender ?? '');
        $this->referrer_id = $publicRegistration->referrer_id;

        $this->residence_municipality_id = $publicRegistration->residence_municipality_id;
        $this->voting_municipality_id = $publicRegistration->voting_municipality_id;

        $this->residence_department_id = $this->departamentoDe($this->residence_municipality_id);
        $this->voting_department_id = $this->departamentoDe($this->voting_municipality_id);

        $this->legacy_residence = (string) ($publicRegistration->residence_municipality ?? '');
        $this->legacy_voting = (string) ($publicRegistration->voting_municipality ?? '');
    }

    private function departamentoDe(?int $municipioId): ?int
    {
        if (! $municipioId) {
            return null;
        }

        $id = Municipio::query()->whereKey($municipioId)->value('departamento_id');

        return $id ? (int) $id : null;
    }

    public function updated($property): void
    {
        if ($property === 'residence_department_id') {
            $this->residence_municipality_id = null;
        }

        if ($property === 'voting_department_id') {
            $this->voting_municipality_id = null;
        }
    }

    public function getMunicipiosResidenciaProperty()
    {
        return $this->municipiosDe($this->residence_department_id);
    }

    public function getMunicipiosVotoProperty()
    {
        return $this->municipiosDe($this->voting_department_id);
    }

    private function municipiosDe(?int $departamentoId)
    {
        if (! $departamentoId) {
            return collect();
        }

        return Municipio::query()
            ->where('departamento_id', $departamentoId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'document_type' => ['nullable', 'string', 'max:20'],
            'document_number' => ['required', 'string', 'max:30'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gender' => ['nullable', 'string', 'max:30'],
            'referrer_id' => ['nullable', 'integer', 'exists:referrers,id'],
            'residence_municipality_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'voting_municipality_id' => ['nullable', 'integer', 'exists:municipios,id'],
        ];
    }

    public function update(): void
    {
        abort_unless(auth()->user()->can('registros.editar'), 403);

        $this->validate();

        $data = [
            'full_name' => $this->full_name,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'document_type' => $this->document_type !== '' ? $this->document_type : null,
            'document_number' => $this->document_number,
            'age' => $this->age !== '' ? $this->age : null,
            'gender' => $this->gender !== '' ? $this->gender : null,
            'referrer_id' => $this->referrer_id ?: null,
            'residence_municipality_id' => $this->residence_municipality_id ?: null,
            'voting_municipality_id' => $this->voting_municipality_id ?: null,
        ];

        // Mantener el texto legacy alineado con la FK elegida
        if ($this->residence_municipality_id) {
            $data['residence_municipality'] = Municipio::query()->whereKey($this->residence_municipality_id)->value('nombre');
        }

        if ($this->voting_municipality_id) {
            $data['voting_municipality'] = Municipio::query()->whereKey($this->voting_municipality_id)->value('nombre');
        }

        $this->publicRegistration->update($data);

        session()->flash('success', 'Registro actualizado correctamente.');
    }

    public function render()
    {
        return view('livewire.registrations.edit', [
            'referrers' => Referrer::orderBy('name')->get(),
            'departamentos' => Departamento::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
