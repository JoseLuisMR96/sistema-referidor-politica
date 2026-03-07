<?php

namespace App\Livewire\Public;

use App\Models\ReferidorPregonero;
use App\Models\ReferidoPregonero;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ReferirPregonero extends Component
{
    public string $id_unico;

    public ?ReferidorPregonero $referidor = null;

    public string $nombre = '';
    public string $cedula = '';
    public string $puesto_votacion = '';

    public bool $enviado = false;

    public function mount(string $id_unico): void
    {
        $this->id_unico = $id_unico;
        $this->loadReferidor();
    }

    private function loadReferidor(): void
    {
        $this->referidor = ReferidorPregonero::query()
            ->where('id_unico', $this->id_unico)
            ->first();
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'cedula' => [
                'required',
                'string',
                'min:5',
                'max:30',
                Rule::unique('referidos_pregoneros', 'cedula')
                    ->where(fn ($q) => $q->where('referidor_pregonero_id', $this->referidor?->id)),
            ],
            'puesto_votacion' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'cedula.required' => 'La cédula es obligatoria.',
            'cedula.unique' => 'Esta cédula ya fue registrada para este referidor.',
            'puesto_votacion.required' => 'El puesto de votación es obligatorio.',
        ];
    }

    public function guardar(): void
    {
        // Revalidar por si el referidor se borró entre mount y submit
        $this->loadReferidor();

        if (! $this->referidor) {
            $this->addError('general', 'El enlace no es válido o ya no existe.');
            return;
        }

        $data = $this->validate();

        ReferidoPregonero::create([
            'referidor_pregonero_id' => $this->referidor->id,
            'nombre' => $data['nombre'],
            'cedula' => $data['cedula'],
            'puesto_votacion' => $data['puesto_votacion'],
        ]);

        $this->enviado = true;

        $this->reset(['nombre', 'cedula', 'puesto_votacion']);

        // Mantener el referidor cargado igual que tu otro módulo
        $this->loadReferidor();

        session()->flash('success', '¡Listo! Tus datos fueron registrados correctamente.');
    }

    public function render()
    {
        return view('livewire.public.referir-pregonero')
            ->layout('components.layouts.guest');
    }
}