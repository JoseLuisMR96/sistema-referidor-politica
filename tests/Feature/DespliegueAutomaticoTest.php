<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DespliegueAutomaticoTest extends TestCase
{
    private string $head;

    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->head = tempnam(sys_get_temp_dir(), 'head');
        $this->carpeta = storage_path('app/despliegue');
        File::deleteDirectory($this->carpeta);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        @unlink($this->head);
        parent::tearDown();
    }

    public function test_fuera_de_produccion_no_hace_nada(): void
    {
        file_put_contents($this->head, str_repeat('a', 40));

        $this->artisan('deploy:automatico', ['--head' => $this->head])
            ->expectsOutput('Solo corre en producción.')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->carpeta);
    }

    public function test_un_commit_nuevo_espera_un_minuto_y_uno_ya_desplegado_no_se_repite(): void
    {
        $this->app['env'] = 'production';
        $commit = str_repeat('b', 40);
        file_put_contents($this->head, $commit);

        // Primera vez que lo ve: solo lo anota (Hostinger puede seguir copiando)
        $this->artisan('deploy:automatico', ['--head' => $this->head])->assertSuccessful();
        $this->assertSame($commit, file_get_contents("{$this->carpeta}/visto"));
        $this->assertFileDoesNotExist("{$this->carpeta}/ultimo");

        // Ya desplegado: no vuelve a correr los pasos
        file_put_contents("{$this->carpeta}/ultimo", $commit);
        $this->artisan('deploy:automatico', ['--head' => $this->head])
            ->doesntExpectOutputToContain('terminando despliegue')
            ->assertSuccessful();
    }

    public function test_lee_el_commit_aunque_head_apunte_a_una_rama(): void
    {
        $this->app['env'] = 'production';
        $dir = dirname($this->head).'/git_'.uniqid();
        mkdir("{$dir}/refs/heads", 0777, true);
        file_put_contents("{$dir}/HEAD", "ref: refs/heads/main\n");
        file_put_contents("{$dir}/refs/heads/main", str_repeat('c', 40)."\n");

        $this->artisan('deploy:automatico', ['--head' => "{$dir}/HEAD"])->assertSuccessful();
        $this->assertSame(str_repeat('c', 40), file_get_contents("{$this->carpeta}/visto"));

        File::deleteDirectory($dir);
    }
}
