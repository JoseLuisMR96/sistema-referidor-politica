<?php

namespace App\Livewire\Eventos;

use App\Livewire\Concerns\RequiereTenant;
use App\Models\IntegranteEquipo;
use App\Support\PhoneNumber;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Equipo de campaña del candidato elegido (emisarios, coordinadores...):
 * listado con lo que cada uno lleva en eventos y dinero, y un modal para
 * registrarlos o editarlos.
 */
class Equipo extends Component
{
    use RequiereTenant;

    public string $search = '';

    public bool $modal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $rol = 'emisario';

    public string $telefono = '';

    public string $notas = '';

    public bool $activo = true;

    private function autorizar(): void
    {
        abort_unless(auth()->user()->can('equipo.gestionar'), 403);
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->canAny(['equipo.gestionar', 'eventos.ver']), 403);
    }

    // El botón del encabezado queda fuera del componente (lo dibuja el layout),
    // así que no puede usar wire:click: dispara este evento con Livewire.dispatch().
    #[On('equipo-abrir')]
    public function abrir(?int $id = null): void
    {
        $this->autorizar();
        $this->resetValidation();
        $this->reset(['nombre', 'telefono', 'notas']);
        $this->rol = 'emisario';
        $this->activo = true;
        $this->editandoId = null;

        if ($id) {
            $i = IntegranteEquipo::findOrFail($id);
            $this->editandoId = $i->id;
            $this->nombre = $i->nombre;
            $this->rol = $i->rol;
            $this->telefono = (string) $i->telefono;
            $this->notas = (string) $i->notas;
            $this->activo = $i->activo;
        }
        $this->modal = true;
    }

    public function cerrar(): void
    {
        $this->modal = false;
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $this->autorizar();
        if (! $this->exigirTenant()) {
            return;
        }

        $data = $this->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'rol' => ['required', Rule::in(array_keys(IntegranteEquipo::ROLES))],
            'telefono' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ], ['nombre.required' => 'Escribe el nombre de la persona.']);

        $valores = [
            'nombre' => trim($data['nombre']),
            'rol' => $data['rol'],
            'telefono' => $data['telefono'] !== '' ? PhoneNumber::digits($data['telefono']) : null,
            'notas' => trim($data['notas']) ?: null,
            'activo' => $data['activo'],
        ];

        if ($this->editandoId) {
            IntegranteEquipo::findOrFail($this->editandoId)->update($valores);
        } else {
            IntegranteEquipo::create($valores);
        }

        session()->flash('success', "\"{$valores['nombre']}\" guardado en el equipo.");
        $this->cerrar();
    }

    /** Solo se borra si no tiene eventos; si tiene, se desactiva (para no perder la trazabilidad). */
    public function eliminar(int $id): void
    {
        $this->autorizar();
        $i = IntegranteEquipo::findOrFail($id);

        if ($i->eventosResponsable()->exists() || $i->eventos()->exists()) {
            $i->update(['activo' => false]);
            session()->flash('success', "\"{$i->nombre}\" tiene eventos registrados: se desactivó en vez de borrarlo.");

            return;
        }

        $i->delete();
        session()->flash('success', "\"{$i->nombre}\" eliminado del equipo.");
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->id();
        $buscar = trim($this->search);

        // Eventos y dinero por responsable (lo cancelado no cuenta)
        $resumen = $tenantId ? DB::table('eventos_campana')
            ->where('tenant_id', $tenantId)->where('estado', '!=', 'cancelado')->whereNotNull('responsable_id')
            ->groupBy('responsable_id')
            ->selectRaw('responsable_id, COUNT(*) as eventos, COALESCE(SUM(monto_asignado), 0) as asignado,
                COALESCE(SUM(monto_gastado), 0) as gastado, MAX(fecha) as ultimo')
            ->get()->keyBy('responsable_id') : collect();

        return view('livewire.eventos.equipo', [
            'tenant' => app(TenantContext::class)->tenant(),
            'puede' => auth()->user()->can('equipo.gestionar'),
            'integrantes' => $tenantId ? IntegranteEquipo::query()
                ->when($buscar !== '', fn ($q) => $q->where('nombre', 'like', "%{$buscar}%"))
                ->withCount('eventos')
                ->orderByDesc('activo')->orderBy('nombre')->get() : collect(),
            'resumen' => $resumen,
        ])->title('Equipo de campaña');
    }
}
