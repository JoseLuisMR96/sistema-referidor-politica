<?php

namespace Database\Seeders;

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

        // 3) Roles (idempotente)
        $rolAdmin = Role::firstOrCreate(['name' => 'Administrador']);
        $rolOperador = Role::firstOrCreate(['name' => 'Operador']);
        $rolAnalista = Role::firstOrCreate(['name' => 'Analista']);
        $rolReferidor = Role::firstOrCreate(['name' => 'Referidor']);

        // 4) Asignación (sync = “update”)
        $rolAdmin->syncPermissions($permisos);

        $rolOperador->syncPermissions([
            'dashboard.ver',
            'referidores.ver',
            'pregoneros_referidos.ver',
            'registros.ver_todos',
            'registros.editar',
            'registros.cambiar_estado',
            'registros.exportar',
            'reportes.ver',
            'dashboard.exportar',
            'redes.ver',
            'historico.ver',
            'eventos.ver',
            'eventos.crear',
            'eventos.editar',
            'equipo.gestionar',
            'presupuesto.ver',
            'presupuesto.gestionar',
            'diae.ver',
            'diae.gestionar',
        ]);

        $rolAnalista->syncPermissions([
            'dashboard.ver',
            'referidores.ver',
            'registros.ver_todos',
            'importados.ver',
            'importados.exportar',
            'reportes.ver',
            'dashboard.exportar',
            'redes.ver',
            'historico.ver',
            'eventos.ver',
            'diae.ver',
        ]);

        // Referidor: crea registros y ve los suyos
        $rolReferidor->syncPermissions([
            'dashboard.ver',
            'registros.ver_propios',
            'registros.crear',
        ]);
    }
}
