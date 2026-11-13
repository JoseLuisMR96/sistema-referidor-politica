<?php

namespace Tests\Feature;

use App\Console\Commands\ExportarPotencialCommand;
use App\Models\Departamento;
use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El potencial por municipio viaja a producción en un CSV del repo (el DIVIPOL
 * no está en el servidor) y el despliegue lo carga.
 */
class CargarPotencialTest extends TestCase
{
    use RefreshDatabase;

    private string $registro;

    private ?string $registroPrevio = null;

    protected function setUp(): void
    {
        parent::setUp();
        // No pisar la huella de la base local de desarrollo
        $this->registro = storage_path('app/potencial-cargado.json');
        $this->registroPrevio = is_file($this->registro) ? file_get_contents($this->registro) : null;
        @unlink($this->registro);
    }

    protected function tearDown(): void
    {
        $this->registroPrevio !== null ? file_put_contents($this->registro, $this->registroPrevio) : @unlink($this->registro);
        parent::tearDown();
    }

    public function test_carga_el_potencial_del_repo_por_codigo_dane_y_solo_si_cambio(): void
    {
        // El valor esperado sale del mismo archivo del repo
        $fila = collect(file(base_path(ExportarPotencialCommand::ARCHIVO), FILE_IGNORE_NEW_LINES))
            ->first(fn ($l) => str_starts_with($l, '50001;'));
        $this->assertNotNull($fila, 'El CSV del repo debe traer Villavicencio (50001)');
        $x = array_combine(ExportarPotencialCommand::COLUMNAS, explode(';', $fila));

        $meta = Departamento::create(['nombre' => 'META', 'slug' => 'meta']);
        $villavo = Municipio::forceCreate(['departamento_id' => $meta->id, 'nombre' => 'VILLAVICENCIO', 'slug' => 'villavicencio', 'codigo_dane' => '50001']);

        $this->artisan('municipios:cargar-potencial', ['--si-cambio' => true])->assertSuccessful();

        $villavo->refresh();
        $this->assertSame((int) $x['potencial_electoral'], (int) $villavo->potencial_electoral);
        $this->assertSame((int) $x['mesas'], (int) $villavo->mesas);
        $this->assertSame((int) $x['potencial_anio'], (int) $villavo->potencial_anio);

        // Segundo despliegue sin cambios en el archivo: no recarga
        $villavo->forceFill(['potencial_electoral' => null])->save();
        $this->artisan('municipios:cargar-potencial', ['--si-cambio' => true])
            ->expectsOutput('Sin cambios en el potencial.')
            ->assertSuccessful();
        $this->assertNull($villavo->fresh()->potencial_electoral);
    }
}
