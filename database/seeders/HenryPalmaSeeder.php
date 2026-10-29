<?php

namespace Database\Seeders;

use App\Enums\CargoEnum;
use App\Models\Departamento;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Candidato Henry Palma — Gobernación del Meta 2027, partido Defensores de
 * la Patria. Lo conocen como "La Patria Milagro" y saluda con "¡FIRME POR LA
 * PATRIA!".
 *
 * Es idempotente: se puede correr varias veces y solo actualiza.
 *   php artisan db:seed --class=HenryPalmaSeeder
 *
 * Imágenes (opcionales): si existen database/seeders/marca/henry-palma/logo.*
 * (el tigre), foto.* o fondo.* (la bandera o el tigre con la bandera; png,
 * jpg o webp), se copian al disco public. Si no, se suben después desde
 * Candidatos > Editar.
 *
 * Colores: tema completo en azul #00062A con letras blancas (#FFFFFF, salen
 * solas por contraste) y amarillo #FFD500 como acento.
 */
class HenryPalmaSeeder extends Seeder
{
    private const SLUG = 'henry-palma';

    public function run(): void
    {
        $meta = Departamento::where('nombre', 'META')->first();

        if (! $meta) {
            $this->command?->error('No existe el departamento META: corre antes DepartamentosSeeder.');

            return;
        }

        $tenant = Tenant::firstOrNew(['slug' => self::SLUG]);
        $nuevo = ! $tenant->exists;

        $tenant->fill([
            'nombre' => 'Henry Palma',
            'descripcion' => 'Gobernación del Meta 2027 · "La Patria Milagro"',
            'partido' => 'Defensores de la Patria',
            'slogan' => '¡FIRME POR LA PATRIA!',
            'color_primario' => '#00062A',
            'color_secundario' => '#FFD500', // amarillo de algunas letras: botones, menú activo, enlaces
            'tema_completo' => true,          // toda la app en su azul, con letras blancas
            'cargo' => CargoEnum::GOBERNACION,
            'departamento_id' => $meta->id,
            'municipio_id' => null,
            'activo' => true,
        ]);
        $tenant->slug = self::SLUG;
        $tenant->save();

        foreach (['logo', 'foto', 'fondo'] as $tipo) {
            $this->copiarImagen($tenant, $tipo);
        }

        $this->command?->info(($nuevo ? 'Creado' : 'Actualizado').": {$tenant->nombre} ({$tenant->circunscripcionTexto()})");

        if (! $tenant->logo_path) {
            $this->command?->warn('Sin logo: pon el tigre en database/seeders/marca/henry-palma/logo.png y vuelve a correr el seeder, o súbelo en Candidatos.');
        }
    }

    private function copiarImagen(Tenant $tenant, string $tipo): void
    {
        $origen = collect(['png', 'jpg', 'jpeg', 'webp'])
            ->map(fn ($ext) => database_path("seeders/marca/henry-palma/{$tipo}.{$ext}"))
            ->first(fn ($ruta) => is_file($ruta));

        if (! $origen) {
            return;
        }

        $destino = "tenants/{$tenant->id}/{$tipo}.".pathinfo($origen, PATHINFO_EXTENSION);
        Storage::disk('public')->put($destino, file_get_contents($origen));

        $anterior = $tenant->{"{$tipo}_path"};
        $tenant->forceFill(["{$tipo}_path" => $destino])->touch();

        if ($anterior && $anterior !== $destino) {
            Storage::disk('public')->delete($anterior);
        }
    }
}
