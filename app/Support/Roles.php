<?php

namespace App\Support;

use App\Models\User;

/**
 * Catálogo de roles: qué hace cada uno y qué permisos lleva. Lo usan el
 * seeder (RolesYPermisosSeeder, que corre en cada despliegue) y los
 * formularios de usuario, que muestran la descripción.
 *
 * El Administrador lleva todos los permisos. Un rol nuevo se agrega aquí y
 * queda creado en el próximo despliegue.
 */
class Roles
{
    public const ADMINISTRADOR = 'Administrador';

    /** nombre => [descripción, permisos] */
    public const CATALOGO = [
        'Administrador' => [
            'Todo: módulos y usuarios. Con candidato asignado es el administrador de esa campaña (la cuenta del candidato) y solo ve lo suyo.',
            ['*'],
        ],
        'Operador' => [
            'Opera la campaña: registros, eventos, equipo, presupuesto y día de elecciones. No configura ni maneja usuarios.',
            [
                'dashboard.ver', 'dashboard.exportar', 'reportes.ver',
                'referidores.ver', 'pregoneros_referidores.ver', 'pregoneros_referidos.ver',
                'registros.ver_todos', 'registros.editar', 'registros.cambiar_estado', 'registros.exportar',
                'redes.ver', 'historico.ver',
                'eventos.ver', 'eventos.crear', 'eventos.editar', 'equipo.gestionar',
                'presupuesto.ver', 'presupuesto.gestionar',
                'diae.ver', 'diae.gestionar',
            ],
        ],
        'Analista' => [
            'Consulta y exporta: dashboard, registros, padrón importado, redes, histórico, eventos y día E. No modifica nada.',
            [
                'dashboard.ver', 'dashboard.exportar', 'reportes.ver',
                'referidores.ver', 'registros.ver_todos',
                'importados.ver', 'importados.exportar',
                'redes.ver', 'historico.ver', 'eventos.ver', 'diae.ver',
            ],
        ],
        // La cuenta del candidato es Administrador de su campaña (restringido a
        // su tenant). Observador es para quien solo debe mirar: asesores, socios.
        'Observador' => [
            'Ve toda la campaña (registros, referidores, redes, análisis, eventos, presupuesto y día E) sin modificar ni exportar. Para asesores o aliados.',
            [
                'dashboard.ver', 'referidores.ver', 'registros.ver_todos',
                'pregoneros_referidores.ver', 'pregoneros_referidos.ver',
                'redes.ver', 'historico.ver', 'eventos.ver', 'presupuesto.ver', 'diae.ver',
            ],
        ],
        'Estratega' => [
            'Planea con cifras: dashboard, redes, histórico y análisis electoral, eventos, retorno y plan de giras. No ve listados de personas.',
            ['dashboard.ver', 'redes.ver', 'historico.ver', 'eventos.ver', 'presupuesto.ver', 'diae.ver'],
        ],
        'Coordinador territorial' => [
            'Maneja el territorio: registra y edita eventos, su equipo y evidencias, y sigue el plan de giras y el retorno.',
            ['dashboard.ver', 'historico.ver', 'eventos.ver', 'eventos.crear', 'eventos.editar', 'equipo.gestionar'],
        ],
        'Coordinador de referidos' => [
            'Maneja a los referidores y pregoneros y revisa los registros que traen. No exporta.',
            [
                'dashboard.ver',
                'referidores.ver', 'referidores.crear', 'referidores.editar',
                'registros.ver_todos', 'registros.editar',
                'pregoneros_referidores.ver', 'pregoneros_referidores.crear', 'pregoneros_referidores.editar',
                'pregoneros_referidos.ver',
            ],
        ],
        'Tesorero' => [
            'Maneja el dinero: presupuesto, gastos y soportes, y legaliza lo gastado en los eventos.',
            ['presupuesto.ver', 'presupuesto.gestionar', 'eventos.ver', 'eventos.editar'],
        ],
        'Coordinador de testigos' => [
            'Día de elecciones: configura la elección, asigna testigos a sus mesas y sigue el conteo.',
            ['diae.ver', 'diae.gestionar'],
        ],
        'Comunicaciones' => [
            'Redes sociales y WhatsApp masivo: ve el dashboard de redes y envía campañas.',
            ['dashboard.ver', 'redes.ver', 'whatsapp.enviar'],
        ],
        'Referidor' => [
            'Referidor: ve el dashboard. Registra personas con su enlace público, sin entrar al sistema.',
            ['dashboard.ver', 'registros.ver_propios', 'registros.crear'],
        ],
    ];

    public static function descripcion(string $rol): ?string
    {
        return self::CATALOGO[$rol][0] ?? null;
    }

    /**
     * Páginas de inicio en orden de preferencia: [ruta, permiso]. Un rol sin
     * dashboard.ver (como el tesorero) entra a la primera que pueda ver.
     */
    public const INICIO = [
        ['dashboard', 'dashboard.ver'],
        ['dashboard.redes', 'redes.ver'],
        ['presupuesto.index', 'presupuesto.ver'],
        ['diae.index', 'diae.ver'],
        ['eventos.index', 'eventos.ver'],
        ['dashboard.historico', 'historico.ver'],
        ['registrations.index', 'registros.ver_todos'],
        ['referrers.index', 'referidores.ver'],
        ['whatsapp.campaigns.create', 'whatsapp.enviar'],
        ['users.index', 'usuarios.ver'],
    ];

    /** Ruta de la primera página que el usuario puede ver; sin ninguna, su perfil. */
    public static function inicio(User $user): string
    {
        foreach (self::INICIO as [$ruta, $permiso]) {
            if ($user->can($permiso)) {
                return route($ruta, absolute: false);
            }
        }

        return route('profile.edit', absolute: false);
    }
}
