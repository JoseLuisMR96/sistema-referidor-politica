<?php

namespace App\Livewire\ImportedPeople;

use App\Models\ImportedPerson;
use Livewire\Component;

class Show extends Component
{
    public int $id;

    public ImportedPerson $row;

    public function mount(int $id): void
    {
        abort_unless(auth()->user()->can('importados.ver'), 403);
        $this->id = $id;

        $this->row = ImportedPerson::with('creator')->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.imported-people.show', [
            'row' => $this->row,
        ]);
    }
}
