<?php

namespace App\Services;

use App\Enums\CargoEnum;
use App\Models\Municipio;
use App\Models\PublicRegistration;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Cálculos del dashboard de registros.
 *
 * Fuente única para la pantalla (Livewire\Dashboard\Stats) y el export a
 * Excel (DashboardExportController): antes cada uno tenía sus propias
 * consultas y el export ignoraba los municipios legacy y metía a los
 * menores de 18 en el rango "61+".
 */
class DashboardStats
{
    /**
     * Conteo por municipio de votación, uniendo FK y texto legacy.
     *
     * El texto legacy solo cuenta cuando la FK es nula. Si el mismo
     * municipio aparece por las dos vías, se suman y gana el nombre del
     * catálogo.
     *
     * @return array<string, array{label: string, departamento: string, total: int}> indexado por nombre en mayúsculas
     */
    public function municipios(): array
    {
        $byFkRows = PublicRegistration::query()
            ->join('municipios as m', 'm.id', '=', 'public_registrations.voting_municipality_id')
            ->leftJoin('departamentos as d', 'd.id', '=', 'm.departamento_id')
            ->whereNotNull('public_registrations.voting_municipality_id')
            ->selectRaw("
                TRIM(m.nombre) as nombre,
                UPPER(TRIM(m.nombre)) as muni_key,
                TRIM(d.nombre) as departamento,
                COUNT(*) as total
            ")
            // Por expresión y no por alias: municipios y departamentos tienen
            // ambos `nombre` y en sqlite el alias resulta ambiguo.
            ->groupByRaw('TRIM(m.nombre), UPPER(TRIM(m.nombre)), TRIM(d.nombre)')
            ->get();

        $merged = [];
        foreach ($byFkRows as $row) {
            $merged[$row->muni_key] = [
                'label' => $row->nombre,
                'departamento' => $row->departamento ?: '—',
                'total' => (int) $row->total,
            ];
        }

        $byLegacyRows = PublicRegistration::query()
            ->whereNull('voting_municipality_id')
            ->whereNotNull('voting_municipality')
            ->where('voting_municipality', '!=', '')
            ->selectRaw('TRIM(voting_municipality) as nombre, UPPER(TRIM(voting_municipality)) as muni_key, COUNT(*) as total')
            ->groupBy('nombre', 'muni_key')
            ->get();

        foreach ($byLegacyRows as $row) {
            if (isset($merged[$row->muni_key])) {
                $merged[$row->muni_key]['total'] += (int) $row->total;
                continue;
            }

            $merged[$row->muni_key] = [
                'label' => $row->nombre,
                'departamento' => '—', // no hay FK, no sabemos el depto
                'total' => (int) $row->total,
            ];
        }

        return $merged;
    }

    /**
     * Top N de municipios de votación, en el formato de las gráficas y hojas.
     *
     * @param  array<string, array{label: string, departamento: string, total: int}>|null  $municipios
     * @return list<array{label: string, departamento: string, value: int}>
     */
    public function topMunicipios(int $limit = 10, ?array $municipios = null): array
    {
        return collect($municipios ?? $this->municipios())
            ->sortByDesc(fn ($row) => (int) $row['total'])
            ->take($limit)
            ->map(fn ($row) => [
                'label' => $row['label'],
                'departamento' => $row['departamento'] ?? '—',
                'value' => (int) $row['total'],
            ])
            ->values()
            ->all();
    }

    /**
     * Apoyo dentro de la circunscripción del candidato (dónde lo pueden votar).
     *
     * Usa el municipio de VOTACIÓN por FK. Un registro sin FK (texto legacy o
     * vacío) cuenta como "sin dato": no se puede saber si suma o no.
     * Las consultas pasan por el scope del tenant del contexto, así que hay
     * que llamarlo con ese mismo tenant elegido.
     *
     * @return array{
     *     ambito: string, titulo: string, total: int, dentro: int, fuera: int, sin_dato: int,
     *     cobertura: array{con_registros: int, total: int}|null,
     *     filas: list<array{label: string, value: int}>
     * }|null  null si al tenant le falta cargo o lugar
     */
    public function circunscripcion(Tenant $tenant): ?array
    {
        $ambito = $tenant->ambito();

        if (! $ambito) {
            return null;
        }

        $total = PublicRegistration::count();
        $sinDato = PublicRegistration::whereNull('voting_municipality_id')->count();

        $porMunicipio = PublicRegistration::query()
            ->whereNotNull('voting_municipality_id')
            ->selectRaw('voting_municipality_id, COUNT(*) as total')
            ->groupBy('voting_municipality_id')
            ->pluck('total', 'voting_municipality_id')
            ->map(fn ($n) => (int) $n);

        $cobertura = null;
        $filas = [];

        if ($ambito === CargoEnum::AMBITO_MUNICIPAL) {
            $dentro = (int) ($porMunicipio[$tenant->municipio_id] ?? 0);
            $filas = [['label' => $tenant->municipio->nombre, 'value' => $dentro]];
        } elseif ($ambito === CargoEnum::AMBITO_DEPARTAMENTAL) {
            // Todos los municipios del departamento, también los que van en 0:
            // ahí es donde falta trabajo.
            $municipios = Municipio::where('departamento_id', $tenant->departamento_id)
                ->orderBy('nombre')
                ->get(['id', 'nombre']);

            $filas = $municipios
                ->map(fn ($m) => ['label' => $m->nombre, 'value' => (int) ($porMunicipio[$m->id] ?? 0)])
                ->sortByDesc('value')
                ->values()
                ->all();

            $dentro = array_sum(array_column($filas, 'value'));
            $cobertura = [
                'con_registros' => count(array_filter($filas, fn ($f) => $f['value'] > 0)),
                'total' => $municipios->count(),
            ];
        } else {
            // Nacional: todo registro con municipio cuenta; se agrupa por departamento
            $filas = PublicRegistration::query()
                ->join('municipios as m', 'm.id', '=', 'public_registrations.voting_municipality_id')
                ->join('departamentos as d', 'd.id', '=', 'm.departamento_id')
                ->selectRaw('TRIM(d.nombre) as departamento, COUNT(*) as total')
                ->groupByRaw('TRIM(d.nombre)')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => ['label' => $r->departamento, 'value' => (int) $r->total])
                ->all();

            $dentro = $total - $sinDato;
        }

        return [
            'ambito' => $ambito,
            'titulo' => $tenant->circunscripcionTexto(),
            'total' => $total,
            'dentro' => $dentro,
            'fuera' => max(0, $total - $dentro - $sinDato),
            'sin_dato' => $sinDato,
            'cobertura' => $cobertura,
            'filas' => $filas,
        ];
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    public function generos(): array
    {
        return PublicRegistration::query()
            ->select('gender', DB::raw('COUNT(*) as total'))
            ->whereNotNull('gender')
            ->where('gender', '!=', '')
            ->groupBy('gender')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => match ($row->gender) {
                    'M' => 'Masculino',
                    'F' => 'Femenino',
                    'O' => 'Otro',
                    'NR' => 'Prefiero no responder',
                    default => (string) $row->gender,
                },
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    public function rangosEdad(): array
    {
        return PublicRegistration::query()
            ->selectRaw("
                CASE
                    WHEN age IS NULL THEN 'Sin dato'
                    WHEN age < 18 THEN '0-17'
                    WHEN age BETWEEN 18 AND 25 THEN '18-25'
                    WHEN age BETWEEN 26 AND 35 THEN '26-35'
                    WHEN age BETWEEN 36 AND 45 THEN '36-45'
                    WHEN age BETWEEN 46 AND 60 THEN '46-60'
                    ELSE '61+'
                END as rango, COUNT(*) as total
            ")
            ->groupBy('rango')
            ->orderByRaw("
                CASE rango
                    WHEN '0-17' THEN 1
                    WHEN '18-25' THEN 2
                    WHEN '26-35' THEN 3
                    WHEN '36-45' THEN 4
                    WHEN '46-60' THEN 5
                    WHEN '61+' THEN 6
                    ELSE 7
                END
            ")
            ->get()
            ->map(fn ($row) => [
                'label' => $row->rango,
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();
    }
}
