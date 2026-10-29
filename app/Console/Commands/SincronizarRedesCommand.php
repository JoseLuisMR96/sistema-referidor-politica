<?php

namespace App\Console\Commands;

use App\Models\RedCuenta;
use App\Models\Tenant;
use App\Services\Redes\RedesSincronizador;
use App\Services\Redes\Tono\AnalizadorComentarios;
use Illuminate\Console\Command;

/**
 * Guarda la foto del día de cada cuenta de redes activa. Programado a diario
 * en routes/console.php: en producción hace falta el cron de schedule:run.
 */
class SincronizarRedesCommand extends Command
{
    protected $signature = 'redes:sincronizar
        {--tenant= : Slug del tenant; sin esto, todos}';

    protected $description = 'Trae de Meta seguidores, publicaciones e interacciones de las cuentas de los candidatos';

    public function handle(RedesSincronizador $sincronizador, AnalizadorComentarios $analizador): int
    {
        $tenantId = null;

        if ($slug = $this->option('tenant')) {
            $tenantId = Tenant::where('slug', $slug)->value('id');

            if (! $tenantId) {
                $this->error("No existe el tenant {$slug}.");

                return self::FAILURE;
            }
        }

        // En consola no hay tenant en contexto: el filtro va explícito
        $cuentas = RedCuenta::query()
            ->with('tenant:id,nombre,activo')
            ->where('activo', true)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get()
            ->filter(fn (RedCuenta $c) => $c->tenant?->activo);

        if ($cuentas->isEmpty()) {
            $this->warn('No hay cuentas de redes activas para sincronizar.');

            return self::SUCCESS;
        }

        $filas = [];
        $errores = 0;

        foreach ($cuentas as $cuenta) {
            try {
                $m = $sincronizador->sincronizar($cuenta);
                $filas[] = [$cuenta->tenant->nombre, $cuenta->red, $cuenta->usuario, $m->seguidores ?? '—', 'OK', $this->comentarios($analizador, $cuenta)];
            } catch (\Throwable $e) {
                $errores++;
                $filas[] = [$cuenta->tenant->nombre, $cuenta->red, $cuenta->usuario, '—', mb_strimwidth($e->getMessage(), 0, 80, '…'), '—'];
            }
        }

        $this->table(['Candidato', 'Red', 'Cuenta', 'Seguidores', 'Resultado', 'Comentarios'], $filas);

        // Que falle una cuenta no es un fallo del comando (las demás siguen),
        // pero si fallan todas sí: probablemente es la configuración de Meta.
        return $errores === count($filas) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Tono de los comentarios nuevos. Un fallo aquí (p. ej. la IA) no tumba la
     * foto de la cuenta, que ya se guardó.
     */
    private function comentarios(AnalizadorComentarios $analizador, RedCuenta $cuenta): string
    {
        if (! $analizador->puedeAnalizar($cuenta)) {
            return '—';
        }

        try {
            $r = $analizador->analizar($cuenta);

            return "{$r['nuevos']} nuevos ({$r['a_favor']}+ / {$r['en_contra']}- / {$r['neutro']}=)";
        } catch (\Throwable $e) {
            return 'Error: '.mb_strimwidth($e->getMessage(), 0, 60, '…');
        }
    }
}
