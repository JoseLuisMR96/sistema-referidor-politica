<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepartamentosSeeder extends Seeder
{
    public function run(): void
    {
        $departamentos = [
            1 => 'ANTIOQUIA',
            2 => 'ATLÁNTICO',
            3 => 'BOGOTÁ',
            4 => 'BOLÍVAR',
            5 => 'BOYACÁ',
            6 => 'CALDAS',
            7 => 'CAQUETÁ',
            8 => 'CAUCA',
            9 => 'CESAR',
            10 => 'CÓRDOBA',
            11 => 'CUNDINAMARCA',
            12 => 'CHOCÓ',
            13 => 'HUILA',
            14 => 'LA GUAJIRA',
            15 => 'MAGDALENA',
            16 => 'META',
            17 => 'NARIÑO',
            18 => 'NORTE DE SANTANDER',
            19 => 'QUINDÍO',
            20 => 'RISARALDA',
            21 => 'SANTANDER',
            22 => 'SUCRE',
            23 => 'TOLIMA',
            24 => 'VALLE DEL CAUCA',
            25 => 'ARAUCA',
            26 => 'CASANARE',
            27 => 'PUTUMAYO',
            28 => 'SAN ANDRÉS',
            29 => 'AMAZONAS',
            30 => 'GUAINÍA',
            31 => 'GUAVIARE',
            32 => 'VAUPÉS',
            33 => 'VICHADA',
        ];

        foreach ($departamentos as $id => $nombre) {
            $slug = Str::slug(mb_strtolower($nombre));

            DB::table('departamentos')->updateOrInsert(
                ['id' => $id],
                [
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
