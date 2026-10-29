<?php

use App\Support\CatalogoDane;
use Illuminate\Database\Migrations\Migration;

/**
 * Llena municipios.codigo_dane (y arregla nombres con la Ñ dañada).
 * En una base sin catálogo no hace nada; el seeder lo aplica después.
 */
return new class extends Migration
{
    public function up(): void
    {
        CatalogoDane::aplicar();
    }

    public function down(): void
    {
        // Solo completa datos; no hay nada que revertir.
    }
};
