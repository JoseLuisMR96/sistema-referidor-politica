<?php

namespace App\Livewire\ImportedPeople;

use Livewire\Component;
use App\Models\ImportedPerson;

class Show extends Component
{
    public int $id;
    public ImportedPerson $row;

    public function mount(int $id): void
    {
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
