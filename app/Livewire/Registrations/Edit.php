<?php

namespace App\Livewire\Registrations;

use Livewire\Component;
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
    public string $residence_municipality = '';
    public string $voting_municipality = '';
    public $referrer_id = null;

    public function mount(PublicRegistration $publicRegistration): void
    {
        abort_unless(auth()->user()?->hasRole('Administrador'), 403);

        $this->publicRegistration = $publicRegistration;

        $this->full_name = (string) $publicRegistration->full_name;
        $this->phone = (string) ($publicRegistration->phone ?? '');
        $this->document_type = (string) ($publicRegistration->document_type ?? '');
        $this->document_number = (string) $publicRegistration->document_number;
        $this->age = $publicRegistration->age;
        $this->gender = (string) ($publicRegistration->gender ?? '');
        $this->residence_municipality = (string) ($publicRegistration->residence_municipality ?? '');
        $this->voting_municipality = (string) ($publicRegistration->voting_municipality ?? '');
        $this->referrer_id = $publicRegistration->referrer_id;
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
            'residence_municipality' => ['nullable', 'string', 'max:120'],
            'voting_municipality' => ['nullable', 'string', 'max:120'],
            'referrer_id' => ['nullable', 'integer', 'exists:referrers,id'],
        ];
    }

    public function update(): void
    {
        abort_unless(auth()->user()?->hasRole('Administrador'), 403);

        $this->validate();

        $this->publicRegistration->update([
            'full_name' => $this->full_name,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'document_type' => $this->document_type !== '' ? $this->document_type : null,
            'document_number' => $this->document_number,
            'age' => $this->age !== '' ? $this->age : null,
            'gender' => $this->gender !== '' ? $this->gender : null,
            'residence_municipality' => $this->residence_municipality !== '' ? $this->residence_municipality : null,
            'voting_municipality' => $this->voting_municipality !== '' ? $this->voting_municipality : null,
            'referrer_id' => $this->referrer_id ?: null,
        ]);

        session()->flash('success', 'Registro actualizado correctamente.');
    }

    public function render()
    {
        return view('livewire.registrations.edit', [
            'referrers' => Referrer::orderBy('name')->get(),
        ]);
    }
}
