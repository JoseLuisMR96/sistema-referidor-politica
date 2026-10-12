<?php

namespace App\Livewire\Admin\Redes;

use App\Livewire\Concerns\RequiereTenant;
use App\Models\Integracion;
use App\Models\RedCuenta;
use App\Services\Redes\MetaGraphClient;
use App\Services\Redes\RedesSincronizador;
use App\Support\TenantContext;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Configuración del análisis de redes:
 * - credenciales GLOBALES de la app de Meta (solo usuarios globales);
 * - cuentas de Facebook/Instagram del candidato elegido.
 *
 * Los secretos nunca vuelven a la vista: se muestran enmascarados y un campo
 * vacío al guardar significa "conservar el actual".
 */
class Index extends Component
{
    use RequiereTenant;

    // --- Credenciales de Meta ---
    public string $app_id = '';

    public string $app_secret = '';

    public string $graph_url = '';

    public string $graph_version = '';

    public string $ig_user_id = '';

    public string $access_token = '';

    public ?string $resultadoPrueba = null;

    // --- Cuenta del candidato ---
    public ?int $cuentaId = null;

    public string $red = RedCuenta::INSTAGRAM;

    public string $url = '';

    public string $usuario = '';

    public string $external_id = '';

    public string $cuenta_token = '';

    private function autorizar(): void
    {
        abort_unless(auth()->user()->can('redes.configurar'), 403);
    }

    private function autorizarCredenciales(): void
    {
        $this->autorizar();
        abort_unless(auth()->user()->esGlobal(), 403);
    }

    public function mount(): void
    {
        $this->autorizar();

        $meta = Integracion::meta();
        $this->app_id = (string) $meta->credencial('app_id', '');
        $this->graph_url = (string) $meta->credencial('graph_url', MetaGraphClient::URL_POR_DEFECTO);
        $this->graph_version = (string) $meta->credencial('graph_version', MetaGraphClient::VERSION_POR_DEFECTO);
        $this->ig_user_id = (string) $meta->credencial('ig_user_id', '');
    }

    public function guardarCredenciales(): void
    {
        $this->autorizarCredenciales();

        $this->validate([
            'app_id' => ['nullable', 'string', 'max:50'],
            'app_secret' => ['nullable', 'string', 'max:255'],
            'graph_url' => ['required', 'url', 'starts_with:https://'],
            'graph_version' => ['required', 'regex:/^v\d+\.\d+$/'],
            'ig_user_id' => ['nullable', 'string', 'max:50'],
            'access_token' => ['nullable', 'string', 'max:2000'],
        ], [
            'graph_version.regex' => 'La versión tiene el formato v26.0',
            'graph_url.starts_with' => 'La URL debe ser https.',
        ]);

        $meta = Integracion::meta();
        $actual = $meta->credenciales ?? [];

        $meta->credenciales = [
            'app_id' => $this->app_id,
            // Vacío = conservar el guardado
            'app_secret' => $this->app_secret !== '' ? $this->app_secret : ($actual['app_secret'] ?? null),
            'graph_url' => rtrim($this->graph_url, '/'),
            'graph_version' => $this->graph_version,
            'ig_user_id' => $this->ig_user_id,
            'access_token' => $this->access_token !== '' ? $this->access_token : ($actual['access_token'] ?? null),
        ];
        $meta->activo = true;
        $meta->save();

        $this->reset(['app_secret', 'access_token']);
        session()->flash('success', 'Credenciales de Meta guardadas.');
    }

    public function probarConexion(): void
    {
        $this->autorizarCredenciales();

        try {
            $cliente = new MetaGraphClient(Integracion::meta());
            $yo = $cliente->get('me', ['fields' => 'id,name']);
            $texto = "Token válido: {$yo['name']} ({$yo['id']}).";

            if ($igId = Integracion::meta()->credencial('ig_user_id')) {
                $ig = $cliente->get($igId, ['fields' => 'username,followers_count']);
                $texto .= " Instagram: @{$ig['username']} con {$ig['followers_count']} seguidores.";

                // Lo que de verdad usa la sincronización: consultar una cuenta
                // como si fuera ajena (Business Discovery). Falla con #10 si
                // el token no tiene instagram_manage_insights.
                try {
                    $cliente->get($igId, [
                        'fields' => "business_discovery.username({$ig['username']}){followers_count}",
                    ]);
                    $texto .= ' Consulta de otras cuentas: OK.';
                } catch (\Throwable $e) {
                    $this->resultadoPrueba = "⚠️ {$texto} Pero la consulta de otras cuentas falla: {$e->getMessage()}"
                        .' Revisa que el token tenga el permiso instagram_manage_insights.';

                    return;
                }
            }

            $this->resultadoPrueba = '✅ '.$texto;
        } catch (\Throwable $e) {
            $this->resultadoPrueba = '❌ '.$e->getMessage();
        }
    }

    // --- Cuentas del candidato ---

    public function editarCuenta(int $id): void
    {
        $this->autorizar();

        $cuenta = RedCuenta::findOrFail($id);

        $this->cuentaId = $cuenta->id;
        $this->red = $cuenta->red;
        $this->url = (string) $cuenta->url;
        $this->usuario = $cuenta->usuario;
        $this->external_id = (string) $cuenta->external_id;
        $this->cuenta_token = '';
        $this->resetValidation();
    }

    public function cancelarCuenta(): void
    {
        $this->reset(['cuentaId', 'url', 'usuario', 'external_id', 'cuenta_token']);
        $this->red = RedCuenta::INSTAGRAM;
        $this->resetValidation();
    }

    public function guardarCuenta(): void
    {
        $this->autorizar();

        if (! $this->exigirTenant()) {
            return;
        }

        $tenantId = app(TenantContext::class)->id();
        $this->usuario = ltrim(trim($this->usuario), '@');

        $data = $this->validate([
            'red' => ['required', Rule::in(RedCuenta::REDES)],
            'url' => ['nullable', 'url', 'max:500'],
            'usuario' => [
                'required', 'string', 'max:150',
                Rule::unique('redes_cuentas', 'usuario')
                    ->where('tenant_id', $tenantId)
                    ->where('red', $this->red)
                    ->ignore($this->cuentaId),
            ],
            'external_id' => ['nullable', 'string', 'max:100'],
            'cuenta_token' => ['nullable', 'string', 'max:2000'],
        ], [
            'usuario.required' => 'Indica el usuario de Instagram o el id/alias de la página de Facebook.',
            'usuario.unique' => 'Esa cuenta ya está registrada para este candidato.',
        ]);

        $valores = [
            'red' => $data['red'],
            'url' => $data['url'] ?: null,
            'usuario' => $data['usuario'],
            'external_id' => $data['external_id'] ?: null,
        ];

        // Vacío = conservar el token guardado
        if ($this->cuenta_token !== '') {
            $valores['access_token'] = $this->cuenta_token;
        }

        if ($this->cuentaId) {
            RedCuenta::findOrFail($this->cuentaId)->update($valores);
        } else {
            RedCuenta::create($valores);
        }

        $this->cancelarCuenta();
        session()->flash('success', 'Cuenta guardada.');
    }

    public function quitarToken(int $id): void
    {
        $this->autorizar();

        RedCuenta::findOrFail($id)->update(['access_token' => null]);
    }

    public function alternarCuenta(int $id): void
    {
        $this->autorizar();

        $cuenta = RedCuenta::findOrFail($id);
        $cuenta->update(['activo' => ! $cuenta->activo]);
    }

    public function sincronizar(int $id, RedesSincronizador $sincronizador): void
    {
        $this->autorizar();

        $cuenta = RedCuenta::findOrFail($id);

        try {
            $m = $sincronizador->sincronizar($cuenta);
            session()->flash('success', "@{$cuenta->usuario}: ".number_format((int) $m->seguidores).' seguidores.');
        } catch (\Throwable $e) {
            session()->flash('error', "@{$cuenta->usuario}: {$e->getMessage()}");
        }
    }

    private function enmascarar(?string $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        return '••••'.mb_substr($valor, -4);
    }

    public function render()
    {
        $meta = Integracion::meta();
        $contexto = app(TenantContext::class);

        return view('livewire.admin.redes.index', [
            'esGlobal' => auth()->user()->esGlobal(),
            'secretoGuardado' => $this->enmascarar($meta->credencial('app_secret')),
            'tokenGuardado' => $this->enmascarar($meta->credencial('access_token')),
            'tenant' => $contexto->tenant(),
            'cuentas' => $contexto->esTodos() ? collect() : RedCuenta::orderBy('red')->orderBy('usuario')->get(),
            'redes' => RedCuenta::REDES,
        ]);
    }
}
