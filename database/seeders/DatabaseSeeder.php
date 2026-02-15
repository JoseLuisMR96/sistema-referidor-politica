<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\DepartamentosSeeder;
use Database\Seeders\MunicipiosMetaSeeder;
use Database\Seeders\RolesYPermisosSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Roles y permisos (siempre)
        $this->call([
            RolesYPermisosSeeder::class,
            DepartamentosSeeder::class,
            MunicipiosMetaSeeder::class, // si ya lo vas a usar en selects
        ]);

        // 2) Usuario administrador REAL (ideal para producción)
        $this->seedAdminFromEnv();

        // 3) Usuarios demo SOLO en local/testing
        if (App::environment(['local', 'testing'])) {
            $this->seedDemoUsers();
        }
    }

    private function seedAdminFromEnv(): void
    {
        $email = env('SEED_ADMIN_EMAIL');
        $name = env('SEED_ADMIN_NAME', 'Administrador');
        $password = env('SEED_ADMIN_PASSWORD');
        $role = env('SEED_ADMIN_ROLE', 'Administrador');

        // Si no están configuradas las variables, no creamos nada (evita “datos fake” en prod)
        if (!$email || !$password) {
            $this->command?->warn('Seeder Admin: No se creó admin porque faltan SEED_ADMIN_EMAIL o SEED_ADMIN_PASSWORD en .env');
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        // Si ya existía, opcionalmente actualiza el nombre (sin tocar contraseña)
        if ($admin->wasRecentlyCreated === false && $admin->name !== $name) {
            $admin->update(['name' => $name]);
        }

        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole($role);
        }

        $this->command?->info("Seeder Admin: OK -> {$admin->email} ({$role})");
    }

    private function seedDemoUsers(): void
    {
        $demoUsers = [
            [
                'email' => 'admin@demo.com',
                'name' => 'Administrador Demo',
                'password' => 'Admin12345*',
                'role' => 'Administrador',
            ],
            [
                'email' => 'operador@demo.com',
                'name' => 'Operador Demo',
                'password' => 'Operador12345*',
                'role' => 'Operador',
            ],
            [
                'email' => 'analista@demo.com',
                'name' => 'Analista Demo',
                'password' => 'Analista12345*',
                'role' => 'Analista',
            ],
        ];

        foreach ($demoUsers as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['password']),
                ]
            );

            if (method_exists($user, 'assignRole')) {
                $user->assignRole($u['role']);
            }
        }

        $this->command?->info('Seeder Demo: usuarios demo creados/actualizados (solo local/testing).');
    }
}
