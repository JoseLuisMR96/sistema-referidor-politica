<?php

namespace App\Livewire\Registrations;

use Livewire\Component;
use App\Models\PublicRegistration;

class Show extends Component
{
    public PublicRegistration $publicRegistration;

    public function mount(PublicRegistration $publicRegistration): void
    {
        abort_unless(auth()->user()->can('registros.ver_todos'), 403);
        $this->publicRegistration = $publicRegistration->load('referrer');
    }

    public function render()
    {
        return view('livewire.registrations.show');
    }
}
