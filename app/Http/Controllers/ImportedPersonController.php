<?php

namespace App\Http\Controllers;

use App\Models\ImportedPerson;
use App\Http\Controllers\Controller;

class ImportedPersonController extends Controller
{
    public function show($id)
    {
        $row = ImportedPerson::findOrFail($id);
        return view('livewire.imported-people.show', compact('row'));
    }
}
