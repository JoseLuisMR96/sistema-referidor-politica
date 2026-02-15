<?php

namespace App\Livewire\Public;

use Livewire\Component;
use App\Models\Referrer;
use App\Models\Municipio;
use App\Models\Departamento;
use Illuminate\Validation\Rule;
use App\Models\PublicRegistration;
use Livewire\Attributes\Locked;

class RegistrationForm extends Component
{
    #[Locked]
    public string $ref = '';
    public ?Referrer $referrer = null;

    public string $full_name = '';
    public string $document_type = 'CC';
    public string $document_number = '';
    public ?int $age = null;
    public string $gender = '';
    public string $phone = '';

    public ?int $residence_department_id = null;
    public ?int $voting_department_id = null;

    public ?int $residence_municipality_id = null;
    public ?int $voting_municipality_id = null;

    public array $departamentos = [];
    public bool $enviado = false;

    public string $debug = '';

    protected $queryString = [
        'ref' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->ref = $this->ref ?: (string) request()->query('ref', '');
        $this->loadReferrer();

        $this->departamentos = Departamento::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->toArray();

        $meta = Departamento::query()->whereRaw('UPPER(nombre)=?', ['META'])->first();
        if ($meta) {
            $this->residence_department_id = (int) $meta->id;
            $this->voting_department_id = (int) $meta->id;
        }
    }

    private function loadReferrer(): void
    {
        $this->referrer = null;

        $code = trim((string) $this->ref);
        if ($code === '') {
            return;
        }

        $this->referrer = Referrer::query()
            ->where('code', $code)
            ->first();
    }

    public function updated($property, $value): void
    {
        if ($property === 'residence_department_id') {
            $this->residence_municipality_id = null;
        }

        if ($property === 'voting_department_id') {
            $this->voting_municipality_id = null;
        }
    }

    // Municipios computados
    public function getMunicipiosResidenciaProperty()
    {
        if (! $this->residence_department_id) return collect();

        return Municipio::query()
            ->where('departamento_id', (int) $this->residence_department_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function getMunicipiosVotoProperty()
    {
        if (! $this->voting_department_id) return collect();

        return Municipio::query()
            ->where('departamento_id', (int) $this->voting_department_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'document_type' => ['required', Rule::in(['CC', 'TI', 'CE', 'PA', 'NIT', 'PEP'])],
            'document_number' => ['required', 'string', 'min:5', 'max:30'],
            'age' => ['nullable', 'integer', 'min:18', 'max:120'],
            'gender' => ['required', Rule::in(['M', 'F', 'O', 'NR'])],
            'phone' => ['nullable', 'string', 'min:7', 'max:20'],

            'residence_department_id' => ['required', 'integer', 'exists:departamentos,id'],
            'voting_department_id' => ['required', 'integer', 'exists:departamentos,id'],

            'residence_municipality_id' => ['required', 'integer', 'exists:municipios,id'],
            'voting_municipality_id' => ['required', 'integer', 'exists:municipios,id'],
        ];
    }

    protected function messages(): array
    {
        return [
            'full_name.required' => 'El nombre completo es obligatorio.',
            'document_number.required' => 'El número de documento es obligatorio.',
            'age.required' => 'La edad es obligatoria.',
            'age.integer' => 'La edad debe ser un número.',
            'gender.required' => 'Selecciona un género.',

            'residence_department_id.required' => 'Selecciona el departamento de residencia.',
            'voting_department_id.required' => 'Selecciona el departamento donde vota.',

            'residence_municipality_id.required' => 'Selecciona el municipio de residencia.',
            'voting_municipality_id.required' => 'Selecciona el municipio donde vota.',
        ];
    }

    public function submit(): void
    {
        // Guarda el ref antes de cualquier cosa
        $refCode = $this->ref;

        $this->loadReferrer();

        if (! $this->referrer) {
            $this->addError('ref', 'El código de referido no es válido o no existe.');
            return;
        }

        if (! $this->referrer->is_active) {
            $this->addError('ref', 'Este link/código fue desactivado. Por favor contáctate con MetaTank para solicitar uno nuevo.');
            return;
        }

        $data = $this->validate();

        $exists = PublicRegistration::query()
            ->where('document_type', $data['document_type'])
            ->where('document_number', $data['document_number'])
            ->exists();

        if ($exists) {
            $this->addError('document_number', 'Este documento ya está registrado.');
            return;
        }

        PublicRegistration::create([
            'full_name' => $data['full_name'],
            'document_type' => $data['document_type'],
            'document_number' => $data['document_number'],
            'age' => isset($data['age']) && $data['age'] !== '' ? (int) $data['age'] : null,
            'gender' => $data['gender'],
            'phone' => $data['phone'] ?? null,

            'residence_municipality_id' => (int) $data['residence_municipality_id'],
            'voting_municipality_id' => (int) $data['voting_municipality_id'],

            'referrer_id' => $this->referrer->id,
            'ref_code_used' => $refCode,
            'status' => 'pendiente',
            'created_by_user_id' => null,
        ]);

        $this->enviado = true;

        // Resetea campos del form
        $this->reset([
            'full_name',
            'document_type',
            'document_number',
            'age',
            'gender',
            'phone',
            'residence_department_id',
            'voting_department_id',
            'residence_municipality_id',
            'voting_municipality_id',
        ]);

        $this->document_type = 'CC';

        // Restaura ref y referrer SIEMPRE
        $this->ref = $refCode;
        $this->loadReferrer();

        // Si quieres volver a precargar META
        $meta = Departamento::query()->whereRaw('UPPER(nombre)=?', ['META'])->first();
        if ($meta) {
            $this->residence_department_id = (int) $meta->id;
            $this->voting_department_id = (int) $meta->id;
        }
    }


    public function render()
    {
        return view('livewire.public.registration-form')
            ->layout('components.layouts.guest');
    }
}
