<?php

namespace App\Livewire\Admin\Redes;

use App\Models\RedCuenta;
use App\Services\Redes\RedesSincronizador;
use App\Services\Redes\Tono\AnalizadorComentarios;
use App\Support\TenantContext;
use Livewire\Component;

/**
 * Listado de las cuentas de Facebook / Instagram del candidato elegido, con
 * sus acciones (sincronizar, analizar comentarios, pausar, quitar token).
 * El registro está en Cuenta y las credenciales de Meta en Integraciones.
 */
class Index extends Component
{
    public string $search = '';

    protected $queryString = ['search' => ['except' => '']];

    private function autorizar(): void
    {
        abort_unless(auth()->user()->can('redes.configurar'), 403);
    }

    public function mount(): void
    {
        $this->autorizar();
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

    public function render()
    {
        $contexto = app(TenantContext::class);
        $buscar = ltrim(trim($this->search), '@');

        return view('livewire.admin.redes.index', [
            'tenant' => $contexto->tenant(),
            'esGlobal' => auth()->user()->esGlobal(),
            'cuentas' => $contexto->esTodos() ? collect() : RedCuenta::query()
                ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('usuario', 'like', "%{$buscar}%")
                    ->orWhere('etiqueta', 'like', "%{$buscar}%")))
                // Las del candidato primero; luego los rivales
                ->orderByRaw("CASE WHEN tipo = 'rival' THEN 1 ELSE 0 END")
                ->orderBy('red')->orderBy('usuario')
                ->get(),
        ])->title('Cuentas de redes');
    }
}
