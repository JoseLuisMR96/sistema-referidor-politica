<?php

namespace Tests\Feature;

use App\Models\ReferidorPregonero;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rutas que antes quedaban abiertas a cualquier usuario logueado (o a
 * cualquiera, en el caso de la API de WPP) y exponían PII o envíos masivos.
 */
class PermisosCriticosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
    }

    private function usuarioConRol(string $rol): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    private function referidor(): ReferidorPregonero
    {
        return ReferidorPregonero::create([
            'nombre' => 'Referidor Prueba',
            // Distinta al placeholder del formulario ("Ej: 1234567890")
            'cedula' => '8765432109',
            'puesto_votacion' => 'Colegio Central',
        ]);
    }

    public function test_api_wpp_rechaza_sin_token_configurado(): void
    {
        config(['services.wppconnect.api_token' => null]);

        $this->postJson('/api/wpp/campaigns/send', [])->assertUnauthorized();
    }

    public function test_api_wpp_rechaza_token_invalido(): void
    {
        config(['services.wppconnect.api_token' => 'secreto']);

        $this->withToken('otro')
            ->postJson('/api/wpp/campaigns/send', [])
            ->assertUnauthorized();
    }

    public function test_api_wpp_acepta_token_valido_y_pasa_a_validacion(): void
    {
        config(['services.wppconnect.api_token' => 'secreto']);

        $this->withToken('secreto')
            ->postJson('/api/wpp/campaigns/send', [])
            ->assertUnprocessable();
    }

    public function test_outbox_exige_permiso_de_whatsapp(): void
    {
        $this->actingAs($this->usuarioConRol('Referidor'))
            ->get('/whatsapp/whatsapp-outbox')
            ->assertForbidden();
    }

    public function test_outbox_permite_al_administrador(): void
    {
        $this->actingAs($this->usuarioConRol('Administrador'))
            ->get('/whatsapp/whatsapp-outbox')
            ->assertOk();
    }

    public function test_crear_pregonero_exige_permiso_de_crear(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('pregoneros_referidores.ver');

        $this->actingAs($user)
            ->get('/admin/referidores-pregoneros/crear')
            ->assertForbidden();
    }

    public function test_exportar_referidos_exige_permiso_de_exportar(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('pregoneros_referidores.ver');

        $this->actingAs($user)
            ->get("/admin/referidores-pregoneros/{$this->referidor()->id}/export-referidos")
            ->assertForbidden();
    }

    public function test_exportar_referidos_permite_al_administrador(): void
    {
        $this->actingAs($this->usuarioConRol('Administrador'))
            ->get("/admin/referidores-pregoneros/{$this->referidor()->id}/export-referidos")
            ->assertOk();
    }

    public function test_pagina_publica_no_muestra_la_cedula_del_referidor(): void
    {
        $referidor = $this->referidor();

        $this->get("/pregoneros/{$referidor->id_unico}/referir")
            ->assertOk()
            ->assertSee($referidor->nombre)
            ->assertDontSee($referidor->cedula);
    }
}
