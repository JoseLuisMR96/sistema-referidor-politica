<?php

namespace App\Livewire\Admin\Redes;

use App\Livewire\Concerns\RequiereTenant;
use App\Models\RedCuenta;
use App\Support\TenantContext;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Registro y edición de una cuenta de Facebook o Instagram del candidato
 * elegido (propia o rival). El listado está en Index.
 *
 * El token nunca vuelve a la vista: vacío al guardar = conservar el actual.
 */
class Cuenta extends Component
{
    use RequiereTenant;

    public ?RedCuenta $cuenta = null;

    public string $red = RedCuenta::INSTAGRAM;

    public string $tipo = RedCuenta::TIPO_PROPIA;

    public string $etiqueta = '';

    public string $url = '';

    public string $usuario = '';

    public string $external_id = '';

    public string $cuenta_token = '';

    private function autorizar(): void
    {
        abort_unless(auth()->user()->can('redes.configurar'), 403);
    }

    /** La cuenta llega por route model binding, filtrada por el tenant (otro tenant = 404). */
    public function mount(?RedCuenta $cuenta = null): void
    {
        $this->autorizar();

        if (! $cuenta?->exists) {
            $this->cuenta = null;

            return;
        }

        $this->cuenta = $cuenta;
        $this->red = $cuenta->red;
        $this->tipo = $cuenta->tipo ?? RedCuenta::TIPO_PROPIA;
        $this->etiqueta = (string) $cuenta->etiqueta;
        $this->url = (string) $cuenta->url;
        $this->usuario = $cuenta->usuario;
        $this->external_id = (string) $cuenta->external_id;
    }

    public function guardar(): void
    {
        $this->autorizar();

        if (! $this->exigirTenant()) {
            return;
        }

        $tenantId = app(TenantContext::class)->id();
        $this->usuario = ltrim(trim($this->usuario), '@');

        $data = $this->validate([
            'red' => [
                'required', Rule::in(RedCuenta::REDES),
                // Rivales: solo Instagram, que se mide con datos públicos.
                // Facebook ajeno no se puede medir sin acceso.
                Rule::when($this->tipo === RedCuenta::TIPO_RIVAL, Rule::in([RedCuenta::INSTAGRAM])),
            ],
            'tipo' => ['required', Rule::in([RedCuenta::TIPO_PROPIA, RedCuenta::TIPO_RIVAL])],
            'etiqueta' => [Rule::requiredIf($this->tipo === RedCuenta::TIPO_RIVAL), 'nullable', 'string', 'max:150'],
            'url' => ['nullable', 'url', 'max:500'],
            'usuario' => [
                'required', 'string', 'max:150',
                Rule::unique('redes_cuentas', 'usuario')
                    ->where('tenant_id', $tenantId)
                    ->where('red', $this->red)
                    ->ignore($this->cuenta?->id),
            ],
            'external_id' => ['nullable', 'string', 'max:100'],
            'cuenta_token' => ['nullable', 'string', 'max:2000'],
        ], [
            'usuario.required' => 'Indica el usuario de Instagram o el id/alias de la página de Facebook.',
            'usuario.unique' => 'Esa cuenta ya está registrada para este candidato.',
            'red.in' => 'Los rivales solo se pueden medir en Instagram: Meta no da datos de páginas de Facebook ajenas.',
            'etiqueta.required' => 'Escribe el nombre del rival (p. ej. "Juan Pérez").',
        ]);

        $valores = [
            'red' => $data['red'],
            'tipo' => $data['tipo'],
            'etiqueta' => $data['etiqueta'] ?: null,
            'url' => $data['url'] ?: null,
            'usuario' => $data['usuario'],
            'external_id' => $data['external_id'] ?: null,
        ];

        // Vacío = conservar el token guardado. Un rival nunca lleva token.
        if ($data['tipo'] === RedCuenta::TIPO_RIVAL) {
            $valores['access_token'] = null;
        } elseif ($this->cuenta_token !== '') {
            $valores['access_token'] = $this->cuenta_token;
        }

        if ($this->cuenta) {
            $this->cuenta->update($valores);
        } else {
            RedCuenta::create($valores);
        }

        session()->flash('success', 'Cuenta guardada.');
        $this->redirectRoute('redes.index');
    }

    public function render()
    {
        return view('livewire.admin.redes.cuenta', [
            'redes' => RedCuenta::REDES,
            'tenant' => app(TenantContext::class)->tenant(),
        ])->title($this->cuenta ? 'Editar cuenta' : 'Nueva cuenta');
    }
}
