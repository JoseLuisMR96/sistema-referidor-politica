<?php

namespace App\Livewire\Admin\Referrers;

use Livewire\Component;
use App\Models\Referrer;

class Edit extends Component
{
    public Referrer $referrer;

    public string $name = '';
    public ?string $phone = null;
    public ?string $email = null;
    public bool $is_active = true;

    public function mount(Referrer $referrer): void
    {
        abort_unless(auth()->user()->can('referidores.editar'), 403);

        $this->referrer = $referrer;

        $this->name = $referrer->name;
        $this->phone = $referrer->phone;
        $this->email = $referrer->email;
        $this->is_active = (bool) $referrer->is_active;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'is_active' => ['boolean'],
        ];
    }

    public function update()
    {
        $data = $this->validate();

        $this->referrer->update($data);

        return redirect()->route('referrers.edit', $this->referrer->id)
            ->with('success', 'Referidor actualizado.');
    }

    public function render()
    {
        return view('livewire.admin.referrers.edit', [
            'link' => url('/registro?ref=' . $this->referrer->code),
        ]);
    }
}
