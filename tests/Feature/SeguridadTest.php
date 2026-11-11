<?php

namespace Tests\Feature;

use App\Livewire\Public\RegistrationForm;
use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\Referrer;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_respuestas_llevan_cabeceras_de_seguridad(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-Powered-By');

        // HSTS solo por https
        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_el_formulario_publico_limita_las_consultas_de_cedulas_repetidas(): void
    {
        $this->usarTenant();
        Referrer::create(['name' => 'Ref', 'code' => 'SEGU0001']);
        $depto = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        $muni = Municipio::create(['departamento_id' => $depto->id, 'nombre' => 'Villavicencio', 'slug' => 'villavicencio']);
        app(TenantContext::class)->set(null);

        $enviar = fn (string $cedula) => Livewire::withQueryParams(['ref' => 'SEGU0001'])
            ->test(RegistrationForm::class)
            ->set('full_name', 'Persona Prueba')
            ->set('document_number', $cedula)
            ->set('gender', 'M')
            ->set('residence_department_id', $depto->id)
            ->set('residence_municipality_id', $muni->id)
            ->set('voting_department_id', $depto->id)
            ->set('voting_municipality_id', $muni->id)
            ->call('submit');

        $enviar('12345678')->assertSet('enviado', true);

        // Una persona que ya se registró ve el aviso normal...
        for ($i = 0; $i < 10; $i++) {
            $enviar('12345678')->assertHasErrors(['document_number']);
        }

        // ...pero quien sigue probando cédulas repetidas queda frenado
        $enviar('12345678')->assertHasErrors(['ref'])->assertHasNoErrors(['document_number']);

        // Las cédulas nuevas se siguen registrando
        $enviar('87654321')->assertSet('enviado', true);
        $this->assertSame(2, PublicRegistration::withoutGlobalScope('tenant')->count());
    }
}
