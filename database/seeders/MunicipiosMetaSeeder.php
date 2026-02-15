<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MunicipiosMetaSeeder extends Seeder
{
    public function run(): void
    {
        $municipios = [
            'Villavicencio',
            'Acacías',
            'Barranca de Upía',
            'Cabuyaro',
            'Castilla la Nueva',
            'Cubarral',
            'Cumaral',
            'El Calvario',
            'El Castillo',
            'El Dorado',
            'Fuente de Oro',
            'Granada',
            'Guamal',
            'Mapiripán',
            'Mesetas',
            'La Macarena',
            'Uribe',
            'Lejanías',
            'Puerto Concordia',
            'Puerto Gaitán',
            'Puerto López',
            'Puerto Lleras',
            'Puerto Rico',
            'Restrepo',
            'San Carlos de Guaroa',
            'San Juan de Arama',
            'San Juanito',
            'San Martín',
            'Vista Hermosa',
        ];

        foreach ($municipios as $nombre) {
            $slug = Str::slug(mb_strtolower($nombre));

            DB::table('municipios')->updateOrInsert(
                ['slug' => $slug],
                [
                    'departamento' => 'META',
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
