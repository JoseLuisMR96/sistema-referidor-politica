<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos de demostración para presentarle la app a un candidato (por defecto
 * Henry Palma): referidores con sus registros en los municipios del Meta y
 * referidores pregoneros con sus referidos.
 *
 *   php artisan db:seed --class=DemoCandidatoSeeder
 *   php artisan demo:borrar henry-palma        (cuando empiece en serio)
 *
 * - Todo es ficticio y queda marcado: registros con origen = 'demo',
 *   referidores con código DEMO…, cédulas que empiezan por 99 (rango que no
 *   usan las cédulas reales).
 * - Sin teléfonos ni celulares: así un WhatsApp masivo nunca le llega a un
 *   número real por accidente.
 * - Volver a correrlo reemplaza la demo anterior (no duplica).
 * - No usa Faker: en producción se instala sin dependencias de desarrollo.
 */
class DemoCandidatoSeeder extends Seeder
{
    public const SLUG = 'henry-palma';

    public const PREFIJO_CODIGO = 'DEMO';

    public const PREFIJO_CEDULA = '99';

    private const NOMBRES_M = ['Carlos', 'Andrés', 'Juan', 'Luis', 'Jorge', 'Diego', 'Camilo', 'Óscar', 'Fabián', 'Héctor', 'Julián', 'Sebastián', 'Mauricio', 'Wilson', 'Edwin', 'Fredy', 'Alexander', 'Jhon', 'Nelson', 'Ricardo'];

    private const NOMBRES_F = ['María', 'Luz', 'Ana', 'Diana', 'Paola', 'Claudia', 'Sandra', 'Yolanda', 'Marcela', 'Liliana', 'Carolina', 'Natalia', 'Gloria', 'Patricia', 'Daniela', 'Leidy', 'Yuli', 'Angélica', 'Martha', 'Johana'];

    private const APELLIDOS = ['Rodríguez', 'Gómez', 'Martínez', 'Hernández', 'Rojas', 'Díaz', 'Moreno', 'Vargas', 'Castro', 'Pardo', 'Baquero', 'Rey', 'Torres', 'Ramírez', 'Romero', 'Parrado', 'Cruz', 'Acosta', 'Reyes', 'Bernal', 'Rincón', 'Gutiérrez', 'Peña', 'Velásquez', 'Ortiz', 'Mora', 'Cárdenas', 'Salazar', 'Cuéllar', 'Garzón'];

    // Peso de cada municipio del Meta (código DANE => cuántos "boletos" en el sorteo)
    private const PESOS_META = [
        '50001' => 40, // Villavicencio
        '50006' => 10, // Acacías
        '50313' => 7,  // Granada
        '50573' => 6,  // Puerto López
        '50226' => 5,  // Cumaral
        '50689' => 5,  // San Martín
        '50606' => 4,  // Restrepo
        '50568' => 4,  // Puerto Gaitán
        '50150' => 3,  // Castilla la Nueva
        '50590' => 3,  // Puerto Rico
        '50450' => 3,  // Puerto Concordia
        '50124' => 2,  // Cabuyaro
        '50711' => 2,  // Vistahermosa
        '50318' => 2,  // Guamal
        '50270' => 2,  // El Dorado
    ];

    private const PUESTOS = ['Colegio Departamental La Esperanza', 'I.E. Francisco José de Caldas', 'Coliseo Álamos', 'I.E. Guillermo Niño Medina', 'Colegio Marco Fidel Suárez', 'I.E. Jorge Eliécer Gaitán', 'Escuela Santa Inés', 'I.E. Juan Pablo II', 'Unillanos sede Barcelona', 'I.E. Alberto Lleras Camargo'];

    public function run(): void
    {
        mt_srand(2027); // siempre los mismos datos

        $tenant = Tenant::where('slug', self::SLUG)->first();
        if (! $tenant) {
            $this->command?->error('No existe el candidato '.self::SLUG.': corre antes HenryPalmaSeeder.');

            return;
        }

        $municipios = DB::table('municipios as m')
            ->join('departamentos as d', 'd.id', '=', 'm.departamento_id')
            ->whereIn('m.codigo_dane', array_keys(self::PESOS_META))
            ->get(['m.id', 'm.nombre', 'm.codigo_dane', 'm.departamento_id']);

        if ($municipios->isEmpty()) {
            $this->command?->error('No están los municipios del Meta con código DANE (corre las migraciones).');

            return;
        }

        $fuera = DB::table('municipios')->whereIn('codigo_dane', ['11001', '25307', '85001', '73001'])->get(['id', 'nombre', 'departamento_id']);

        DB::transaction(function () use ($tenant, $municipios, $fuera) {
            self::borrar($tenant);

            $referidores = $this->referidores($tenant);
            $total = $this->registros($tenant, $referidores, $municipios, $fuera);
            [$pregoneros, $referidos] = $this->pregoneros($tenant);

            $this->command?->info("Demo de {$tenant->nombre}: ".count($referidores)." referidores, {$total} registros, {$pregoneros} pregoneros con {$referidos} referidos.");
        });
    }

    /** Borra la demo de un candidato (lo que lleva las marcas de demo). */
    public static function borrar(Tenant $tenant): array
    {
        $refIds = DB::table('referrers')->where('tenant_id', $tenant->id)
            ->where('code', 'like', self::PREFIJO_CODIGO.'%')->pluck('id');
        $pregIds = DB::table('referidores_pregoneros')->where('tenant_id', $tenant->id)
            ->where('cedula', 'like', self::PREFIJO_CEDULA.'%')->pluck('id');

        return [
            'registros' => DB::table('public_registrations')->where('tenant_id', $tenant->id)->where('origen', 'demo')->delete(),
            'referidores' => DB::table('referrers')->whereIn('id', $refIds)->delete(),
            'referidos_pregoneros' => DB::table('referidos_pregoneros')->whereIn('referidor_pregonero_id', $pregIds)->delete(),
            'pregoneros' => DB::table('referidores_pregoneros')->whereIn('id', $pregIds)->delete(),
        ];
    }

    private function referidores(Tenant $tenant): array
    {
        $ids = [];
        $ahora = now();

        foreach (range(1, 12) as $i) {
            $ids[] = DB::table('referrers')->insertGetId([
                'tenant_id' => $tenant->id,
                'name' => $this->nombre(),
                'code' => self::PREFIJO_CODIGO.'HP'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'is_active' => $i !== 12, // uno inactivo, para mostrar el estado
                'phone' => null,
                'email' => null,
                'created_at' => $ahora->copy()->subDays(95 - $i),
                'updated_at' => $ahora,
            ]);
        }

        return $ids;
    }

    private function registros(Tenant $tenant, array $referidores, $municipios, $fuera): int
    {
        $boletos = [];
        foreach ($municipios as $m) {
            $boletos = array_merge($boletos, array_fill(0, self::PESOS_META[$m->codigo_dane], $m));
        }

        // Unos referidores rinden más que otros (para que el ranking se vea real)
        $pesoRef = [];
        foreach ($referidores as $i => $id) {
            $pesoRef = array_merge($pesoRef, array_fill(0, max(1, 14 - $i), $id));
        }

        $filas = [];
        $n = 640;

        foreach (range(1, $n) as $i) {
            $genero = mt_rand(0, 100) < 52 ? 'F' : 'M';
            $azar = mt_rand(1, 100);
            $voto = $azar <= 4 ? null : ($azar <= 9 && $fuera->isNotEmpty() ? $fuera[mt_rand(0, $fuera->count() - 1)] : $boletos[mt_rand(0, count($boletos) - 1)]);
            $residencia = $voto && mt_rand(1, 100) <= 85 ? $voto : $boletos[mt_rand(0, count($boletos) - 1)];

            // Más registros en las últimas semanas (la campaña va creciendo) y
            // un puñado hoy, para que "Registros hoy" tenga movimiento
            if ($i % 70 === 0) {
                $fecha = now()->subMinutes(mt_rand(10, 360));
            } else {
                $diasAtras = 1 + (int) floor(89 * (mt_rand(0, 1000) / 1000) ** 1.8);
                $fecha = now()->subDays($diasAtras)->setTime(mt_rand(7, 20), mt_rand(0, 59));
            }

            $refId = $pesoRef[mt_rand(0, count($pesoRef) - 1)];

            $filas[] = [
                'tenant_id' => $tenant->id,
                'full_name' => $this->nombre($genero),
                'document_type' => 'CC',
                'document_number' => self::PREFIJO_CEDULA.str_pad((string) (10000000 + $i * 7919 % 89999999), 8, '0', STR_PAD_LEFT),
                'age' => $this->edad(),
                'gender' => $genero,
                'residence_municipality_id' => $residencia->id,
                'residence_municipality' => $residencia->nombre,
                'voting_municipality_id' => $voto?->id,
                'voting_municipality' => $voto?->nombre,
                'phone' => null,
                'referrer_id' => $refId,
                'ref_code_used' => null,
                'status' => 'pendiente',
                'origen' => 'demo',
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ];
        }

        foreach (array_chunk($filas, 200) as $lote) {
            DB::table('public_registrations')->insert($lote);
        }

        return $n;
    }

    private function pregoneros(Tenant $tenant): array
    {
        $totalReferidos = 0;
        $cantidad = 15;

        foreach (range(1, $cantidad) as $i) {
            $pagado = $i <= 6;
            $creado = now()->subDays(mt_rand(10, 60));

            $id = DB::table('referidores_pregoneros')->insertGetId([
                'tenant_id' => $tenant->id,
                'id_unico' => (string) Str::uuid(),
                'nombre' => $this->nombre(),
                'cedula' => self::PREFIJO_CEDULA.str_pad((string) (50000000 + $i * 104729 % 49999999), 8, '0', STR_PAD_LEFT),
                'celular' => null,
                'puesto_votacion' => self::PUESTOS[$i % count(self::PUESTOS)],
                'monto_pagar' => [150000, 200000, 250000, 300000][mt_rand(0, 3)],
                'pago_realizado' => $pagado,
                'hora_pago' => $pagado ? $creado->copy()->addDays(mt_rand(3, 9)) : null,
                'imagen_pago' => null,
                'created_at' => $creado,
                'updated_at' => $creado,
            ]);

            $filas = [];
            foreach (range(1, mt_rand(3, 25)) as $j) {
                $fecha = $creado->copy()->addDays(mt_rand(0, 9))->setTime(mt_rand(8, 19), mt_rand(0, 59));
                $filas[] = [
                    'tenant_id' => $tenant->id,
                    'referidor_pregonero_id' => $id,
                    'nombre' => $this->nombre(),
                    'cedula' => self::PREFIJO_CEDULA.str_pad((string) (20000000 + ($i * 1000 + $j) * 6007 % 29999999), 8, '0', STR_PAD_LEFT),
                    'puesto_votacion' => self::PUESTOS[mt_rand(0, count(self::PUESTOS) - 1)],
                    'created_at' => $fecha->isFuture() ? now() : $fecha,
                    'updated_at' => $fecha->isFuture() ? now() : $fecha,
                ];
            }

            DB::table('referidos_pregoneros')->insert($filas);
            $totalReferidos += count($filas);
        }

        return [$cantidad, $totalReferidos];
    }

    private function nombre(?string $genero = null): string
    {
        $genero ??= mt_rand(0, 1) ? 'F' : 'M';
        $nombres = $genero === 'F' ? self::NOMBRES_F : self::NOMBRES_M;
        $a = self::APELLIDOS;

        return $nombres[mt_rand(0, count($nombres) - 1)].' '.$a[mt_rand(0, count($a) - 1)].' '.$a[mt_rand(0, count($a) - 1)];
    }

    private function edad(): int
    {
        // Más jóvenes y adultos medios, pocos mayores
        $r = mt_rand(1, 100);

        return match (true) {
            $r <= 22 => mt_rand(18, 25),
            $r <= 50 => mt_rand(26, 35),
            $r <= 72 => mt_rand(36, 45),
            $r <= 88 => mt_rand(46, 59),
            default => mt_rand(60, 78),
        };
    }
}
