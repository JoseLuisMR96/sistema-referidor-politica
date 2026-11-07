<?php

namespace App\Livewire\Admin;

use App\Models\Integracion;
use App\Services\Redes\MetaGraphClient;
use App\Services\Redes\Tono\ClasificadorTono;
use Livewire\Component;

/**
 * Credenciales GLOBALES de servicios externos: la app de Meta (redes) y la
 * API de Anthropic (tono de los comentarios). Solo usuarios globales con
 * redes.configurar.
 *
 * Los secretos nunca vuelven a la vista: se muestran enmascarados y un campo
 * vacío al guardar significa "conservar el actual".
 */
class Integraciones extends Component
{
    // --- Meta ---
    public string $app_id = '';

    public string $app_secret = '';

    public string $graph_url = '';

    public string $graph_version = '';

    public string $ig_user_id = '';

    public string $access_token = '';

    public ?string $resultadoPrueba = null;

    // --- IA para el tono de los comentarios ---
    public string $anthropic_key = '';

    public ?string $resultadoIa = null;

    private function autorizar(): void
    {
        $user = auth()->user();
        abort_unless($user->can('redes.configurar') && $user->esGlobal(), 403);
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
        $this->autorizar();

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
        $this->autorizar();

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

    public function guardarAnthropic(): void
    {
        $this->autorizar();

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
        $this->autorizar();

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

    private function enmascarar(?string $valor): ?string
    {
        return blank($valor) ? null : '••••'.mb_substr($valor, -4);
    }

    public function render()
    {
        $meta = Integracion::meta();

        return view('livewire.admin.integraciones', [
            'secretoGuardado' => $this->enmascarar($meta->credencial('app_secret')),
            'tokenGuardado' => $this->enmascarar($meta->credencial('access_token')),
            'tokenVence' => $meta->credencial('token_vence') ? \Illuminate\Support\Carbon::parse($meta->credencial('token_vence')) : null,
            'iaGuardada' => $this->enmascarar(
                Integracion::query()->where('proveedor', Integracion::ANTHROPIC)->first()?->credencial('api_key')
            ),
        ])->title('Integraciones');
    }
}
