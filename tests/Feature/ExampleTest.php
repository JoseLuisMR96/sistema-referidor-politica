<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La raíz no sirve contenido: manda al login o al dashboard según la sesión.
     */
    public function test_la_raiz_redirige_a_login_cuando_no_hay_sesion(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
