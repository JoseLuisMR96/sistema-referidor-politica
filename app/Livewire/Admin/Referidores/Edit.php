<?php

namespace App\Livewire\Admin\Referidores;

use App\Models\ReferidorPregonero;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Edit extends Component
{
    public ReferidorPregonero $referidor;

    // Campos editables
    public string $nombre = '';
    public string $cedula = '';
    public string $celular = ''; // ✅ NUEVO
    public string $puesto_votacion = '';

    public $monto_pagar = null;          // numeric nullable
    public string $pago_realizado = '';  // '', '1', '0' para select
    public ?string $hora_pago = null;    // datetime-local string
    public ?string $imagen_pago = null;  // ruta/url nullable

    public function mount(ReferidorPregonero $referidor): void
    {
        abort_unless(auth()->user()->can('pregoneros_referidores.editar'), 403);

        $this->referidor = $referidor;

        $this->nombre = (string) $referidor->nombre;
        $this->cedula = (string) $referidor->cedula;
        $this->celular = (string) ($referidor->celular ?? ''); // ✅ NUEVO
        $this->puesto_votacion = (string) $referidor->puesto_votacion;

        $this->monto_pagar = $referidor->monto_pagar;
        $this->pago_realizado = is_null($referidor->pago_realizado) ? '' : ($referidor->pago_realizado ? '1' : '0');

        // datetime-local necesita formato Y-m-d\TH:i
        $this->hora_pago = $referidor->hora_pago ? $referidor->hora_pago->format('Y-m-d\TH:i') : null;

        $this->imagen_pago = $referidor->imagen_pago;
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
                Rule::unique('referidores_pregoneros', 'cedula')->ignore($this->referidor->id),
            ],
            'celular' => ['nullable', 'string', 'max:20'], // ✅ ya estaba, ok
            'puesto_votacion' => ['required', 'string', 'min:3', 'max:255'],
            'monto_pagar' => ['nullable', 'numeric', 'min:0'],
            'pago_realizado' => ['nullable', Rule::in(['', '1', '0'])],
            'hora_pago' => ['nullable', 'date'],
            'imagen_pago' => ['nullable', 'string', 'max:2048'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'cedula.required' => 'La cédula es obligatoria.',
            'cedula.unique' => 'Ya existe otro referidor con esta cédula.',
            'puesto_votacion.required' => 'El puesto de votación es obligatorio.',

            'monto_pagar.numeric' => 'El monto debe ser numérico.',
            'hora_pago.date' => 'La hora de pago no tiene un formato válido.',
        ];
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('pregoneros_referidores.editar'), 403);

        $data = $this->validate();

        $pago = null;
        if ($data['pago_realizado'] === '1') $pago = true;
        if ($data['pago_realizado'] === '0') $pago = false;

        $this->referidor->update([
            'nombre' => $data['nombre'],
            'cedula' => $data['cedula'],
            'celular' => ($data['celular'] ?? '') !== '' ? $data['celular'] : null,
            'puesto_votacion' => $data['puesto_votacion'],

            'monto_pagar' => $data['monto_pagar'] !== '' ? $data['monto_pagar'] : null,
            'pago_realizado' => $pago,
            'hora_pago' => $data['hora_pago'] ?? null,
            'imagen_pago' => $data['imagen_pago'] ?? null,
        ]);

        session()->flash('success', 'Referidor actualizado correctamente. Operación estable, sin sorpresas.');
        $this->redirectRoute('pregoneros.referidores.index', navigate: true);
    }

    public function render()
    {
        abort_unless(auth()->user()->can('pregoneros_referidores.editar'), 403);

        return view('livewire.admin.referidores.edit', [
            'referidor' => $this->referidor,
        ]);
    }
}