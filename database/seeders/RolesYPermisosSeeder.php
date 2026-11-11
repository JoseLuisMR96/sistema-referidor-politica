<?php

namespace Database\Seeders;

use App\Support\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            // Usuarios internos
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
            'usuarios.roles',

            // Referidores
            'referidores.ver',
            'referidores.crear',
            'referidores.editar',
            'referidores.eliminar',
            'referidores.asignar_acceso',

            // Registros públicos (personas)
            'registros.ver_todos',
            'registros.ver_propios',
            'registros.crear',
            'registros.editar',
            'registros.cambiar_estado',
            'registros.exportar',

            // Importados (Excel / CSV)
            'importados.ver',
            'importados.importar',
            'importados.exportar',
            'importados.eliminar',

            // Dashboard
            'dashboard.ver',
            'reportes.ver',

            'dashboard.exportar',
            'whatsapp.enviar',

            // Referidores pregoneros
            'pregoneros_referidos.ver',
            'pregoneros_referidos.exportar',
            'pregoneros_referidores.ver',
            'pregoneros_referidores.crear',
            'pregoneros_referidores.editar',
            'pregoneros_referidores.exportar_masivo',

            // Tenants (candidatos). Además del permiso, solo usuarios globales.
            'tenants.gestionar',

            // Redes sociales: configurar credenciales/cuentas y ver el análisis
            'redes.configurar',
            'redes.ver',

            // Histórico electoral: ver (todos los que analizan) e importar (solo globales)
            'historico.ver',
            'historico.importar',

            // Eventos de campaña (trazabilidad en territorio) y su equipo
            'eventos.ver',
            'eventos.crear',
            'eventos.editar',
            'eventos.eliminar',
            'equipo.gestionar',

            // Presupuesto y gastos de campaña (tope legal, Cuentas Claras)
            'presupuesto.ver',
            'presupuesto.gestionar',

            // Día de elecciones: testigos y conteo propio
            'diae.ver',
            'diae.gestionar',

        ];

        // 1) Crear/actualizar permisos (idempotente)
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // 2) (Opcional) limpiar permisos que ya no existen en la lista
        // OJO: solo si estás seguro de que no quieres permisos “viejos”
        // Permission::whereNotIn('name', $permisos)->delete();

        // 3) Roles y su asignación (idempotente; sync = "update"). El catálogo
        //    con la descripción de cada rol está en App\Support\Roles.
        foreach (Roles::CATALOGO as $nombre => [, $permisosRol]) {
            $desconocidos = array_diff($permisosRol, [...$permisos, '*']);
            if ($desconocidos) {
                throw new \LogicException("El rol {$nombre} usa permisos que no existen: ".implode(', ', $desconocidos));
            }
            Role::firstOrCreate(['name' => $nombre])
                ->syncPermissions($permisosRol === ['*'] ? $permisos : $permisosRol);
        }
    }
}
