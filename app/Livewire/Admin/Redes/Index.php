<?php

namespace App\Livewire\Admin\Redes;

use App\Livewire\Concerns\RequiereTenant;
use App\Models\Integracion;
use App\Models\RedCuenta;
use App\Services\Redes\MetaGraphClient;
use App\Services\Redes\RedesSincronizador;
use App\Services\Redes\Tono\AnalizadorComentarios;
use App\Services\Redes\Tono\ClasificadorTono;
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

    // --- IA para el tono de los comentarios (global) ---
    public string $anthropic_key = '';

    public ?string $resultadoIa = null;

    // --- Cuenta del candidato ---
    public ?int $cuentaId = null;

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
        $tokenNuevo = trim($this->access_token) !== '';

        $meta->credenciales = [
            'app_id' => $this->app_id,
            // Vacío = conservar el guardado
            'app_secret' => $this->app_secret !== '' ? $this->app_secret : ($actual['app_secret'] ?? null),
            'graph_url' => rtrim($this->graph_url, '/'),
            'graph_version' => $this->graph_version,
            'ig_user_id' => $this->ig_user_id,
            'access_token' => $tokenNuevo ? trim($this->access_token) : ($actual['access_token'] ?? null),
            'token_vence' => $tokenNuevo ? null : ($actual['token_vence'] ?? null),
        ];
        $meta->activo = true;
        $meta->save();

        $mensaje = 'Credenciales de Meta guardadas.';

        // Token nuevo: se cambia solo por uno de ~60 días (el del Explorer dura horas)
        if ($tokenNuevo) {
            try {
                $largo = (new MetaGraphClient($meta))->extenderToken(trim($this->access_token));
                $meta->credenciales = array_merge($meta->credenciales, [
                    'access_token' => $largo['token'],
                    'token_vence' => $largo['vence']?->toIso8601String(),
                ]);
                $meta->save();
                $mensaje .= $largo['vence']
                    ? ' El token se extendió y vence el '.$largo['vence']->locale('es')->isoFormat('D [de] MMMM [de] YYYY').'.'
                    : ' El token se extendió y no tiene fecha de vencimiento.';
            } catch (\Throwable $e) {
                $mensaje .= ' Ojo: no se pudo extender el token ('.$e->getMessage().'). Quedó el que pegaste, que puede vencer en horas.';
            }
        }

        $this->reset(['app_secret', 'access_token']);
        session()->flash('success', $mensaje);
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

    // --- IA (Anthropic) ---

    public function guardarAnthropic(): void
    {
        $this->autorizarCredenciales();

        $this->validate(['anthropic_key' => ['required', 'string', 'starts_with:sk-ant-', 'max:500']], [
            'anthropic_key.required' => 'Pega la API key de Anthropic.',
            'anthropic_key.starts_with' => 'Las API keys de Anthropic empiezan por sk-ant-.',
        ]);

        $ia = Integracion::query()->firstOrNew(['proveedor' => Integracion::ANTHROPIC]);
        $ia->credenciales = ['api_key' => $this->anthropic_key];
        $ia->activo = true;
        $ia->save();

        $this->reset('anthropic_key');
        $this->resultadoIa = null;
        session()->flash('success', 'API key de Anthropic guardada.');
    }

    public function probarIa(ClasificadorTono $clasificador): void
    {
        $this->autorizarCredenciales();

        try {
            // Tres frases de ejemplo con postura evidente
            $r = $clasificador->clasificar('el candidato', 'prueba', [
                'Todo mi apoyo, cuente con mi voto',
                'Otro politiquero más, no le creo nada',
                '¿A qué hora es la reunión?',
            ]);

            $this->resultadoIa = $r['tonos'] === [ClasificadorTono::A_FAVOR, ClasificadorTono::EN_CONTRA, ClasificadorTono::NEUTRO]
                ? '✅ La IA responde y clasifica bien (a favor / en contra / neutro).'
                : '⚠️ La IA responde, pero clasificó distinto a lo esperado: '.implode(', ', $r['tonos']);
        } catch (\Throwable $e) {
            $this->resultadoIa = '❌ '.$e->getMessage();
        }
    }

    // --- Cuentas del candidato ---

    public function editarCuenta(int $id): void
    {
        $this->autorizar();

        $cuenta = RedCuenta::findOrFail($id);

        $this->cuentaId = $cuenta->id;
        $this->red = $cuenta->red;
        $this->tipo = $cuenta->tipo ?? RedCuenta::TIPO_PROPIA;
        $this->etiqueta = (string) $cuenta->etiqueta;
        $this->url = (string) $cuenta->url;
        $this->usuario = $cuenta->usuario;
        $this->external_id = (string) $cuenta->external_id;
        $this->cuenta_token = '';
        $this->resetValidation();
    }

    public function cancelarCuenta(): void
    {
        $this->reset(['cuentaId', 'url', 'usuario', 'external_id', 'cuenta_token', 'etiqueta']);
        $this->red = RedCuenta::INSTAGRAM;
        $this->tipo = RedCuenta::TIPO_PROPIA;
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
                    ->ignore($this->cuentaId),
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

    public function analizarComentarios(int $id, AnalizadorComentarios $analizador): void
    {
        $this->autorizar();

        $cuenta = RedCuenta::findOrFail($id);

        try {
            $r = $analizador->analizar($cuenta);
            session()->flash('success', $r['nuevos'] === 0
                ? "{$cuenta->nombreVisible()}: no hay comentarios nuevos de los últimos 7 días."
                : "{$cuenta->nombreVisible()}: {$r['nuevos']} comentarios analizados ({$r['a_favor']} a favor, {$r['en_contra']} en contra, {$r['neutro']} neutros).");
        } catch (\Throwable $e) {
            session()->flash('error', "{$cuenta->nombreVisible()}: {$e->getMessage()}");
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
            'tokenVence' => $meta->credencial('token_vence') ? \Illuminate\Support\Carbon::parse($meta->credencial('token_vence')) : null,
            'iaGuardada' => $this->enmascarar(
                Integracion::query()->where('proveedor', Integracion::ANTHROPIC)->first()?->credencial('api_key')
            ),
            'tenant' => $contexto->tenant(),
            'cuentas' => $contexto->esTodos() ? collect() : RedCuenta::orderBy('red')->orderBy('usuario')->get(),
            'redes' => RedCuenta::REDES,
        ]);
    }
}
