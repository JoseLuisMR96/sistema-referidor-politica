<?php

namespace App\Livewire\Admin\Referrers;

use Livewire\Component;
use App\Models\Referrer;
use Illuminate\Support\Str;

class Create extends Component
{
    public string $name = '';
    public ?string $phone = null;
    public ?string $email = null;
    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('referidores.crear'), 403);
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

    private function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Referrer::where('code', $code)->exists());

        return $code;
    }

    public function save()
    {
        $data = $this->validate();

        $ref = Referrer::create([
            ...$data,
            'code' => $this->generateUniqueCode(),
        ]);

        return redirect()->route('referrers.edit', $ref->id)
            ->with('success', 'Referidor creado correctamente.');
    }

    public function render()
    {
        return view('livewire.admin.referrers.create');
    }
}
