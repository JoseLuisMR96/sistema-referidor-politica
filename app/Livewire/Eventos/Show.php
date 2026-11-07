<?php

namespace App\Livewire\Eventos;

use App\Models\EventoCampana;
use App\Models\EvidenciaEvento;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

/**
 * Ficha de un evento de campaña: dónde, quién fue, dinero y evidencias.
 * El evento llega con el scope del candidato: el de otro candidato da 404.
 */
class Show extends Component
{
    public EventoCampana $evento;

    public function mount(EventoCampana $evento): void
    {
        abort_unless(auth()->user()->can('eventos.ver'), 403);
        $this->evento = $evento;
    }

    public function quitarEvidencia(int $id): void
    {
        abort_unless(auth()->user()->can('eventos.editar'), 403);

        $evidencia = $this->evento->evidencias()->findOrFail($id);
        Storage::disk(EvidenciaEvento::DISCO)->delete($evidencia->ruta);
        $evidencia->delete();
    }

    public function eliminar()
    {
        abort_unless(auth()->user()->can('eventos.eliminar'), 403);

        Storage::disk(EvidenciaEvento::DISCO)->deleteDirectory("eventos/{$this->evento->tenant_id}/{$this->evento->id}");
        $titulo = $this->evento->titulo;
        $this->evento->delete(); // evidencias e integrantes caen por CASCADE

        session()->flash('success', "Evento \"{$titulo}\" eliminado.");

        return $this->redirectRoute('eventos.index');
    }

    public function render()
    {
        $this->evento->load(['municipio:id,nombre', 'departamento:id,nombre', 'responsable', 'integrantes', 'creador:id,name']);

        return view('livewire.eventos.show', [
            'evidencias' => $this->evento->evidencias()->latest()->get(),
        ])->title($this->evento->titulo);
    }
}
