<?php

namespace App\Livewire\DiaE;

use App\Models\Eleccion;
use App\Models\ReporteMesa;
use App\Models\Testigo;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Formulario público del testigo (/testigo/{token}): reporta los votos de
 * sus mesas (E-14) desde el celular, sin iniciar sesión. El token es su
 * credencial; si se filtra, la campaña genera otro.
 *
 * Sin usuario no hay contexto de tenant: el testigo se busca sin el scope y
 * el reporte lleva el tenant_id del testigo, explícito.
 */
class Reportar extends Component
{
    use WithFileUploads;

    public string $token = '';

    public ?int $mesa = null;

    /** opcion_id => votos */
    public array $votos = [];

    public ?int $blanco = null;

    public ?int $nulos = null;

    public ?int $no_marcados = null;

    public string $observaciones = '';

    public $foto = null;

    public bool $enviado = false;

    public const MAX_POR_MINUTO = 20;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->testigo(); // 404 si no existe
    }

    private function testigo(): Testigo
    {
        $t = Testigo::withoutGlobalScope('tenant')
            ->with(['eleccion' => fn ($q) => $q->withoutGlobalScope('tenant'), 'puesto.municipio:id,nombre'])
            ->where('token', $this->token)->where('activo', true)->first();
        abort_unless($t && $t->eleccion, 404);

        return $t;
    }

    /** Al elegir una mesa ya reportada, se cargan sus votos para corregir. */
    public function updatedMesa(): void
    {
        $this->enviado = false;
        $this->resetValidation();
        $t = $this->testigo();
        $r = $this->mesa ? ReporteMesa::withoutGlobalScope('tenant')
            ->where(['eleccion_id' => $t->eleccion_id, 'puesto_id' => $t->puesto_id, 'mesa' => $this->mesa])->first() : null;

        $this->votos = $r ? array_map('intval', $r->votos) : [];
        $this->blanco = $r?->blanco;
        $this->nulos = $r?->nulos;
        $this->no_marcados = $r?->no_marcados;
        $this->observaciones = (string) $r?->observaciones;
        $this->foto = null;
    }

    public function enviar(): void
    {
        $t = $this->testigo();
        abort_unless($t->eleccion->abierta, 403);

        $clave = 'testigo:'.$t->id;
        if (RateLimiter::tooManyAttempts($clave, self::MAX_POR_MINUTO)) {
            $this->addError('mesa', 'Demasiados envíos seguidos: espera un minuto.');

            return;
        }
        RateLimiter::hit($clave, 60);

        $opciones = $t->eleccion->opciones()->pluck('id')->all();
        $this->validate([
            'mesa' => ['required', 'integer', 'in:'.implode(',', $t->listaMesas() ?: [0])],
            'votos' => ['array'],
            'votos.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'blanco' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'nulos' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'no_marcados' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            // Solo fotos: el E-14 se fotografía con el celular
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'mesa.required' => 'Elige la mesa.',
            'mesa.in' => 'Esa mesa no está asignada a ti.',
            'votos.*.integer' => 'Escribe los votos solo con números.',
            'foto.image' => 'La foto del E-14 debe ser una imagen.',
        ]);

        $votos = [];
        foreach ($opciones as $id) {
            $votos[$id] = (int) ($this->votos[$id] ?? 0);
        }
        $total = array_sum($votos) + (int) $this->blanco + (int) $this->nulos + (int) $this->no_marcados;
        if ($total > 1000) {
            $this->addError('mesa', 'Los votos suman más de lo que cabe en una mesa: revisa los números.');

            return;
        }

        $reporte = ReporteMesa::withoutGlobalScope('tenant')->firstOrNew([
            'eleccion_id' => $t->eleccion_id, 'puesto_id' => $t->puesto_id, 'mesa' => $this->mesa,
        ]);
        // Otro testigo no corrige la mesa que reportó alguien más
        if ($reporte->exists && $reporte->testigo_id && $reporte->testigo_id !== $t->id) {
            $this->addError('mesa', 'Esa mesa ya la reportó otro testigo de la campaña.');

            return;
        }

        $reporte->fill([
            'tenant_id' => $t->tenant_id,
            'testigo_id' => $t->id,
            'votos' => $votos,
            'blanco' => (int) $this->blanco,
            'nulos' => (int) $this->nulos,
            'no_marcados' => (int) $this->no_marcados,
            'total' => $total,
            'observaciones' => trim($this->observaciones) ?: null,
        ]);
        if ($this->foto) {
            $anterior = $reporte->foto_ruta;
            $reporte->foto_ruta = $this->foto->store("diae/{$t->tenant_id}/{$t->eleccion_id}", Eleccion::DISCO);
            if ($anterior) {
                \Illuminate\Support\Facades\Storage::disk(Eleccion::DISCO)->delete($anterior);
            }
        }
        $reporte->save();

        $this->foto = null;
        $this->enviado = true;
    }

    public function render()
    {
        $t = $this->testigo();
        $reportadas = ReporteMesa::withoutGlobalScope('tenant')
            ->where(['eleccion_id' => $t->eleccion_id, 'puesto_id' => $t->puesto_id])
            ->whereIn('mesa', $t->listaMesas())->pluck('mesa')->all();

        $tenant = \App\Models\Tenant::find($t->tenant_id);

        return view('livewire.dia-e.reportar', [
            't' => $t,
            'opciones' => $t->eleccion->opciones()->get(),
            'reportadas' => $reportadas,
            'tenantMarca' => $tenant,
        ])->layout('components.layouts.guest', ['tenantMarca' => $tenant])->title('Reporte de mesa');
    }
}
