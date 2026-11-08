<?php

namespace App\Livewire\Presupuesto;

use App\Livewire\Concerns\RequiereTenant;
use App\Livewire\Eventos\Formulario as FormularioEvento;
use App\Models\EventoCampana;
use App\Models\GastoCampana;
use App\Models\IntegranteEquipo;
use App\Models\Presupuesto;
use App\Models\PresupuestoRubro;
use App\Services\Territorio\PresupuestoCampana;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Presupuesto y gastos de campaña del candidato elegido, contra el tope legal.
 * Registrar un gasto y editar el presupuesto van en modales.
 */
class Index extends Component
{
    use RequiereTenant;
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'cat')]
    public string $categoria = '';

    // --- Modal de gasto ---
    public bool $modalGasto = false;

    public ?int $gastoId = null;

    public array $gasto = [];

    public $soporte = null;

    // --- Modal de presupuesto ---
    public bool $modalPresupuesto = false;

    public string $tope = '';

    public string $topeFuente = '';

    /** categoria => monto (texto, admite "1.200.000") */
    public array $rubros = [];

    private function puede(): bool
    {
        return auth()->user()->can('presupuesto.gestionar');
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->can('presupuesto.ver'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoria(): void
    {
        $this->resetPage();
    }

    // ===================== Gastos =====================

    public function abrirGasto(?int $id = null): void
    {
        abort_unless($this->puede(), 403);
        $this->resetValidation();
        $this->soporte = null;
        $this->gastoId = $id;

        $g = $id ? GastoCampana::findOrFail($id) : null;
        $this->gasto = [
            'categoria' => $g?->categoria ?? ($this->categoria ?: '208'),
            'fecha' => ($g?->fecha ?? now())->format('Y-m-d'),
            'concepto' => (string) $g?->concepto,
            'proveedor' => (string) $g?->proveedor,
            'documento' => (string) $g?->documento,
            'valor' => $g ? (string) (float) $g->valor : '',
            'estado' => $g?->estado ?? 'pagado',
            'evento_id' => $g?->evento_id,
            'responsable_id' => $g?->responsable_id,
        ];
        $this->modalGasto = true;
    }

    public function guardarGasto(): void
    {
        abort_unless($this->puede(), 403);
        if (! $this->exigirTenant()) {
            return;
        }

        $tenantId = app(TenantContext::class)->id();
        $this->gasto['valor'] = (string) FormularioEvento::monto((string) ($this->gasto['valor'] ?? ''));

        $data = $this->validate([
            'gasto.categoria' => ['required', Rule::in(array_keys(GastoCampana::CATEGORIAS))],
            'gasto.fecha' => ['required', 'date'],
            'gasto.concepto' => ['required', 'string', 'min:3', 'max:190'],
            'gasto.proveedor' => ['nullable', 'string', 'max:150'],
            'gasto.documento' => ['nullable', 'string', 'max:60'],
            'gasto.valor' => ['required', 'numeric', 'min:1', 'max:99999999999'],
            'gasto.estado' => ['required', Rule::in(array_keys(GastoCampana::ESTADOS))],
            'gasto.evento_id' => ['nullable', 'integer', Rule::exists('eventos_campana', 'id')->where('tenant_id', $tenantId)],
            'gasto.responsable_id' => ['nullable', 'integer', Rule::exists('equipo_campana', 'id')->where('tenant_id', $tenantId)],
            'soporte' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'gasto.concepto.required' => 'Escribe en qué se gastó.',
            'gasto.valor.required' => 'Escribe el valor.',
            'gasto.valor.numeric' => 'Escribe el valor solo con números (p. ej. 350000).',
            'soporte.mimes' => 'El soporte debe ser PDF o foto (JPG, PNG, WEBP).',
            'soporte.max' => 'El soporte puede pesar hasta 10 MB.',
        ])['gasto'];

        $valores = [
            'categoria' => $data['categoria'],
            'fecha' => $data['fecha'],
            'concepto' => trim($data['concepto']),
            'proveedor' => trim((string) $data['proveedor']) ?: null,
            'documento' => trim((string) $data['documento']) ?: null,
            'valor' => (float) $data['valor'],
            'estado' => $data['estado'],
            'evento_id' => $data['evento_id'] ?: null,
            'responsable_id' => $data['responsable_id'] ?: null,
        ];

        $gasto = $this->gastoId ? tap(GastoCampana::findOrFail($this->gastoId))->update($valores)
            : GastoCampana::create($valores + ['creado_por' => auth()->id()]);

        if ($this->soporte) {
            $anterior = $gasto->soporte_ruta;
            $gasto->update([
                'soporte_ruta' => $this->soporte->store("presupuesto/{$gasto->tenant_id}", GastoCampana::DISCO),
                'soporte_nombre' => mb_substr($this->soporte->getClientOriginalName(), 0, 190),
            ]);
            if ($anterior) {
                Storage::disk(GastoCampana::DISCO)->delete($anterior);
            }
        }

        $this->modalGasto = false;
        $this->soporte = null;
        session()->flash('success', 'Gasto guardado.');
    }

    public function eliminarGasto(int $id): void
    {
        abort_unless($this->puede(), 403);
        $g = GastoCampana::findOrFail($id);
        if ($g->soporte_ruta) {
            Storage::disk(GastoCampana::DISCO)->delete($g->soporte_ruta);
        }
        $g->delete();
        session()->flash('success', 'Gasto eliminado.');
    }

    // ===================== Presupuesto =====================

    public function abrirPresupuesto(): void
    {
        abort_unless($this->puede(), 403);
        $this->resetValidation();
        $p = Presupuesto::first();
        $this->tope = $p?->tope_legal !== null ? (string) (float) $p->tope_legal : '';
        $this->topeFuente = (string) $p?->tope_fuente;
        $guardados = PresupuestoRubro::query()->pluck('monto', 'categoria');
        $this->rubros = collect(GastoCampana::CATEGORIAS)
            ->map(fn ($n, $c) => isset($guardados[$c]) && (float) $guardados[$c] > 0 ? (string) (float) $guardados[$c] : '')->all();
        $this->modalPresupuesto = true;
    }

    public function guardarPresupuesto(): void
    {
        abort_unless($this->puede(), 403);
        if (! $this->exigirTenant()) {
            return;
        }

        $this->tope = (string) FormularioEvento::monto($this->tope);
        $this->rubros = array_map(fn ($v) => (string) FormularioEvento::monto((string) $v), $this->rubros);
        $this->validate([
            'tope' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'topeFuente' => ['nullable', 'string', 'max:190'],
            'rubros' => ['array'],
            'rubros.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], [
            'tope.numeric' => 'Escribe el tope solo con números.',
            'rubros.*.numeric' => 'Escribe los montos solo con números.',
        ]);

        Presupuesto::updateOrCreate(['tenant_id' => app(TenantContext::class)->id()], [
            'tope_legal' => $this->tope !== '' ? (float) $this->tope : null,
            'tope_fuente' => trim($this->topeFuente) ?: null,
        ]);
        foreach (GastoCampana::CATEGORIAS as $codigo => $nombre) {
            PresupuestoRubro::updateOrCreate(['categoria' => $codigo], ['monto' => (float) ($this->rubros[$codigo] ?? 0)]);
        }

        $this->modalPresupuesto = false;
        session()->flash('success', 'Presupuesto guardado.');
    }

    public function render(PresupuestoCampana $cuentas)
    {
        $tenant = app(TenantContext::class)->tenant();
        if (! $tenant) {
            return view('livewire.presupuesto.index', ['tenant' => null])->title('Presupuesto de campaña');
        }

        $buscar = trim($this->search);

        return view('livewire.presupuesto.index', [
            'tenant' => $tenant,
            'puede' => $this->puede(),
            'r' => $cuentas->resumen(),
            'gastos' => GastoCampana::query()
                ->with(['evento:id,titulo', 'responsable:id,nombre'])
                ->when($this->categoria !== '', fn ($q) => $q->where('categoria', $this->categoria))
                ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w->where('concepto', 'like', "%{$buscar}%")
                    ->orWhere('proveedor', 'like', "%{$buscar}%")->orWhere('documento', 'like', "%{$buscar}%")))
                ->orderByDesc('fecha')->orderByDesc('id')
                ->paginate(20),
            'eventosLista' => $this->modalGasto ? EventoCampana::query()->orderByDesc('fecha')->limit(100)->get(['id', 'titulo', 'fecha']) : collect(),
            'equipo' => $this->modalGasto ? IntegranteEquipo::orderBy('nombre')->get(['id', 'nombre']) : collect(),
        ])->title('Presupuesto de campaña');
    }
}
