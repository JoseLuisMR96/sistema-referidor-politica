<?php

namespace App\Livewire\Admin\Tenants;

use App\Models\Tenant;
use App\Services\EliminarTenant;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Listado de candidatos (tenants) con buscador y borrado. El registro y la
 * edición están en Formulario. Solo usuarios globales con tenants.gestionar:
 * un usuario restringido no debe ver los demás.
 */
class Index extends Component
{
    public string $search = '';

    protected $queryString = ['search' => ['except' => '']];

    // Borrado: primero se muestra qué se pierde y se pide escribir el nombre
    public ?int $eliminandoId = null;

    public string $confirmacionNombre = '';

    private function autorizar(): void
    {
        $user = auth()->user();
        abort_unless($user->can('tenants.gestionar') && $user->esGlobal(), 403);
    }

    public function mount(): void
    {
        $this->autorizar();
    }

    public function pedirEliminar(int $id): void
    {
        $this->autorizar();
        Tenant::findOrFail($id);

        $this->eliminandoId = $id;
        $this->confirmacionNombre = '';
        $this->resetErrorBag();
    }

    public function cancelarEliminar(): void
    {
        $this->reset('eliminandoId', 'confirmacionNombre');
    }

    public function eliminar(EliminarTenant $servicio): void
    {
        $this->autorizar();
        $tenant = Tenant::findOrFail($this->eliminandoId);

        if (trim($this->confirmacionNombre) !== $tenant->nombre) {
            $this->addError('confirmacionNombre', 'Escribe el nombre exacto del candidato para confirmar.');

            return;
        }

        try {
            $servicio->eliminar($tenant);
        } catch (\RuntimeException $e) {
            $this->addError('confirmacionNombre', $e->getMessage());

            return;
        }

        $this->cancelarEliminar();

        session()->flash('success', "Candidato \"{$tenant->nombre}\" eliminado con todos sus datos.");
        // Recarga completa: el menú lateral también lista los candidatos
        $this->redirectRoute('tenants.index');
    }

    public function render()
    {
        $this->autorizar();

        // Conteos con DB::table a propósito: con Eloquent el scope del tenant
        // elegido en el menú dejaría en 0 a todos los demás.
        $contar = fn (string $tabla) => DB::table($tabla)
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $buscar = trim($this->search);
        $eliminando = $this->eliminandoId ? Tenant::find($this->eliminandoId) : null;

        return view('livewire.admin.tenants.index', [
            'tenantEliminando' => $eliminando,
            'resumenEliminar' => $eliminando ? app(EliminarTenant::class)->resumen($eliminando) : [],
            'usuariosEliminar' => $eliminando ? app(EliminarTenant::class)->usuarios($eliminando) : 0,
            'total' => Tenant::count(),
            'tenants' => Tenant::query()
                ->with(['departamento:id,nombre', 'municipio:id,nombre'])
                ->withCount(['users'])
                ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('partido', 'like', "%{$buscar}%")
                    ->orWhere('slug', 'like', "%{$buscar}%")))
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->get(),
            'registros' => $contar('public_registrations'),
            'pregoneros' => $contar('referidores_pregoneros'),
            'campanas' => $contar('campaigns'),
        ])->title('Candidatos');
    }
}
