<?php

namespace App\Livewire\Admin\Referidores;

use App\Models\ReferidorPregonero;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;


class Create extends Component
{
    public string $nombre = '';
    public string $cedula = '';
    public string $celular = '';
    public string $puesto_votacion = '';

    // Opcionales
    public ?string $monto_pagar = null;
    public ?bool $pago_realizado = null;
    public ?string $hora_pago = null; // si lo usas como input datetime-local
    public ?string $imagen_pago = null;

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'cedula' => ['required', 'string', 'min:5', 'max:30'],
            'celular' => ['nullable', 'string', 'max:20'],
            'puesto_votacion' => ['required', 'string', 'min:3', 'max:255'],
            'monto_pagar' => ['nullable', 'numeric', 'min:0'],
            'pago_realizado' => ['nullable', 'boolean'],
            'hora_pago' => ['nullable', 'date'],
            'imagen_pago' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function guardar()
    {
        $data = $this->validate();

        $referidor = ReferidorPregonero::create([
            'nombre' => $data['nombre'],
            'cedula' => $data['cedula'],
            'celular' => $data['celular'] ?: null,
            'puesto_votacion' => $data['puesto_votacion'],

            'monto_pagar' => $data['monto_pagar'] ?? null,
            'pago_realizado' => $data['pago_realizado'] ?? null,
            'hora_pago' => $data['hora_pago'] ?? null,
            'imagen_pago' => $data['imagen_pago'] ?? null,
            // id_unico se genera solo en booted() del modelo
        ]);

        return redirect()->route('pregoneros.referidores.index')
            ->with('success', 'Referidor creado. Ahora sí: link público habilitado y operación escalable.');
    }

    public function render()
    {
        return view('livewire.admin.referidores.create');
    }
}
