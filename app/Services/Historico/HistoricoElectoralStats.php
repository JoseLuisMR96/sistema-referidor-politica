<?php

namespace App\Services\Historico;

use App\Enums\CargoEnum;
use App\Models\PublicRegistration;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cálculos del módulo "Histórico electoral".
 *
 * Gobernación y Alcaldía eligen una persona: "ganador" es quien más votos
 * sacó en ese lugar. Asamblea, Concejo y JAL son listas: ahí "ganador" es el
 * partido más votado (las curules vienen del archivo importado).
 */
class HistoricoElectoralStats
{
    private const TABLA = 'historico_electoral';

    /** Presidencia puede tener segunda vuelta; el resto siempre es 1. */
    public int $vuelta = 1;

    private function tabla(?string $alias = null): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::TABLA.($alias ? " as {$alias}" : ''))
            ->where(($alias ? "{$alias}." : '').'vuelta', $this->vuelta);
    }

    /** Vueltas con datos (para el filtro de presidencia). */
    protected function calcularVueltas(int $anio, string $cargo): array
    {
        return DB::table(self::TABLA)->where('anio', $anio)->where('cargo', $cargo)
            ->distinct()->orderBy('vuelta')->pluck('vuelta')->map(fn ($v) => (int) $v)->all();
    }

    /*
     * Caché: los resultados históricos no cambian, así que cada consulta se
     * calcula una vez. La clave lleva una "versión" que el importador sube al
     * terminar (ver ImportadorHistorico::guardar), y con eso todo se renueva.
     */
    public const CACHE_VERSION = 'historico:version';

    public static function invalidarCache(): void
    {
        Cache::forever(self::CACHE_VERSION, (int) Cache::get(self::CACHE_VERSION, 1) + 1);
    }

    private function recordar(string $metodo, array $args, \Closure $calcular): mixed
    {
        $version = (int) Cache::get(self::CACHE_VERSION, 1);
        $clave = "historico:{$version}:{$metodo}:{$this->vuelta}:".md5(serialize($args));

        return Cache::rememberForever($clave, $calcular);
    }

    public function vueltas(int $anio, string $cargo): array
    {
        return $this->recordar('vueltas', [$anio, $cargo], fn () => $this->calcularVueltas($anio, $cargo));
    }

    public function anios(): array
    {
        return $this->recordar('anios', [], fn () => $this->calcularAnios());
    }

    public function cargos(?int $anio): array
    {
        return $this->recordar('cargos', [$anio], fn () => $this->calcularCargos($anio));
    }

    public function comunas(int $anio, string $cargo, int $municipioId): array
    {
        return $this->recordar('comunas', [$anio, $cargo, $municipioId], fn () => $this->calcularComunas($anio, $cargo, $municipioId));
    }

    public function contienda(int $anio, string $cargo, ?int $departamentoId, ?int $municipioId = null, ?string $comuna = null): array
    {
        return $this->recordar('contienda', [$anio, $cargo, $departamentoId, $municipioId, $comuna], fn () => $this->calcularContienda($anio, $cargo, $departamentoId, $municipioId, $comuna));
    }

    public function ganadoresPorMunicipio(int $anio, string $cargo, ?int $departamentoId = null): array
    {
        return $this->recordar('ganadoresPorMunicipio', [$anio, $cargo, $departamentoId], fn () => $this->calcularGanadoresPorMunicipio($anio, $cargo, $departamentoId));
    }

    public function ganadoresPorDepartamento(int $anio, string $cargo): array
    {
        return $this->recordar('ganadoresPorDepartamento', [$anio, $cargo], fn () => $this->calcularGanadoresPorDepartamento($anio, $cargo));
    }

    /** Asamblea, concejo, JAL y Congreso se votan por listas: se agrupa por partido. */
    public static function esCorporacion(string $cargo): bool
    {
        return in_array($cargo, ['asamblea', 'concejo', 'jal', 'camara', 'senado'], true);
    }

    /** Años con datos, del más reciente al más viejo. */
    protected function calcularAnios(): array
    {
        return DB::table(self::TABLA)->distinct()->orderByDesc('anio')->pluck('anio')->map(fn ($a) => (int) $a)->all();
    }

    /** Cargos con datos en ese año, en el orden de CargoEnum. */
    protected function calcularCargos(?int $anio): array
    {
        if (! $anio) {
            return [];
        }

        $hay = DB::table(self::TABLA)->where('anio', $anio)->distinct()->pluck('cargo')->all();

        return collect(CargoEnum::cases())
            ->filter(fn (CargoEnum $c) => in_array($c->value, $hay, true))
            ->values()
            ->all();
    }

    /** Comunas con datos (JAL) en un municipio. */
    protected function calcularComunas(int $anio, string $cargo, int $municipioId): array
    {
        return $this->tabla()
            ->where(compact('anio', 'cargo'))
            ->where('municipio_id', $municipioId)
            ->whereNotNull('comuna')
            ->distinct()->orderBy('comuna')->pluck('comuna')->all();
    }

    /**
     * Resultado de una contienda: candidatos o listas con sus votos, %, si
     * quedó elegido y curules. Sin municipio suma todo el departamento, y sin
     * departamento todo el país (presidencia y Senado).
     */
    protected function calcularContienda(int $anio, string $cargo, ?int $departamentoId, ?int $municipioId = null, ?string $comuna = null): array
    {
        $base = fn () => $this->tabla()
            ->where('anio', $anio)
            ->where('cargo', $cargo)
            ->when($departamentoId, fn ($q) => $q->where('departamento_id', $departamentoId))
            ->when($municipioId, fn ($q) => $q->where('municipio_id', $municipioId))
            ->when($comuna, fn ($q) => $q->where('comuna', $comuna));

        if (self::esCorporacion($cargo)) {
            return $this->contiendaPorListas($base);
        }

        $filas = $base()
            ->groupBy('partido', 'candidato')
            ->selectRaw('partido, candidato, SUM(votos) as votos, MAX(elegido) as elegido, MAX(curules) as curules')
            ->orderByDesc('votos')
            ->get();

        $total = (int) $filas->sum('votos');

        return $filas->values()->map(fn ($f, $i) => [
            'posicion' => $i + 1,
            'partido' => $f->partido,
            'candidato' => $f->candidato,
            'votos' => (int) $f->votos,
            'porcentaje' => $total ? round($f->votos * 100 / $total, 1) : 0,
            'elegido' => $f->elegido === null ? null : (bool) $f->elegido,
            'curules' => $f->curules === null ? null : (int) $f->curules,
            'color' => self::colorPartido($f->partido),
        ])->all();
    }

    /**
     * Listas: votos del partido (lista + preferentes), curules y elegidos.
     * Curules = las del archivo o, si no vienen, cuántos elegidos tuvo.
     */
    private function contiendaPorListas(\Closure $base): array
    {
        $filas = $base()
            ->groupBy('partido')
            ->selectRaw('partido, SUM(votos) as votos, MAX(curules) as curules')
            ->orderByDesc('votos')
            ->get();

        $elegidos = $base()
            ->where('elegido', true)
            ->whereNotNull('candidato')
            ->groupBy('partido', 'candidato')
            ->selectRaw('partido, candidato, SUM(votos) as votos')
            ->orderByDesc('votos')
            ->get()
            ->groupBy('partido');

        $total = (int) $filas->sum('votos');

        return $filas->values()->map(function ($f, $i) use ($elegidos, $total) {
            $nombres = ($elegidos[$f->partido] ?? collect())->pluck('candidato')->all();

            return [
                'posicion' => $i + 1,
                'partido' => $f->partido,
                'candidato' => null,
                'elegidos' => $nombres,
                'votos' => (int) $f->votos,
                'porcentaje' => $total ? round($f->votos * 100 / $total, 1) : 0,
                'elegido' => null,
                'curules' => max((int) $f->curules, count($nombres)) ?: null,
                'color' => self::colorPartido($f->partido),
            ];
        })->all();
    }

    /**
     * Quién ganó en cada municipio (el más votado), con el segundo y el
     * margen. Sin departamento recorre todo el país.
     *
     * @return array<int, array> por municipio_id
     */
    protected function calcularGanadoresPorMunicipio(int $anio, string $cargo, ?int $departamentoId = null): array
    {
        $filas = $this->tabla('h')
            ->join('municipios as m', 'm.id', '=', 'h.municipio_id')
            ->join('departamentos as d', 'd.id', '=', 'h.departamento_id')
            ->where('h.anio', $anio)
            ->where('h.cargo', $cargo)
            ->when($departamentoId, fn ($q) => $q->where('h.departamento_id', $departamentoId))
            ->when(self::esCorporacion($cargo),
                fn ($q) => $q->groupBy('h.municipio_id', 'm.nombre', 'm.codigo_dane', 'd.nombre', 'h.partido')
                    ->selectRaw('h.municipio_id, m.nombre as municipio, m.codigo_dane, d.nombre as departamento, h.partido, NULL as candidato, SUM(h.votos) as votos'),
                fn ($q) => $q->groupBy('h.municipio_id', 'm.nombre', 'm.codigo_dane', 'd.nombre', 'h.partido', 'h.candidato')
                    ->selectRaw('h.municipio_id, m.nombre as municipio, m.codigo_dane, d.nombre as departamento, h.partido, h.candidato, SUM(h.votos) as votos'))
            ->get()
            ->groupBy('municipio_id');

        $resultado = [];

        foreach ($filas as $municipioId => $grupo) {
            $orden = $grupo->sortByDesc('votos')->values();
            $total = (int) $grupo->sum('votos');
            $primero = $orden[0];
            $segundo = $orden[1] ?? null;

            $resultado[$municipioId] = [
                'municipio_id' => (int) $municipioId,
                'municipio' => $primero->municipio,
                'departamento' => $primero->departamento,
                'codigo_dane' => $primero->codigo_dane,
                'partido' => $primero->partido,
                'candidato' => $primero->candidato,
                'votos' => (int) $primero->votos,
                'porcentaje' => $total ? round($primero->votos * 100 / $total, 1) : 0,
                'segundo' => $segundo ? ($segundo->candidato ?: $segundo->partido) : null,
                'segundo_partido' => $segundo?->partido,
                'margen' => $segundo && $total ? round(($primero->votos - $segundo->votos) * 100 / $total, 1) : null,
                'total' => $total,
                'color' => self::colorPartido($primero->partido),
            ];
        }

        uasort($resultado, fn ($a, $b) => strcmp($a['municipio'], $b['municipio']));

        return $resultado;
    }

    /** Partidos ordenados por cuántos municipios ganaron. */
    public function partidosGanadores(array $ganadores): array
    {
        return collect($ganadores)
            ->groupBy('partido')
            ->map(fn (Collection $g, $partido) => [
                'partido' => $partido,
                'municipios' => $g->count(),
                'votos' => $g->sum('votos'),
                'color' => self::colorPartido($partido),
            ])
            ->sortByDesc('municipios')
            ->values()
            ->all();
    }

    /**
     * Vista de todo el país para cargos departamentales: ganador de cada
     * departamento (suma de sus municipios).
     */
    protected function calcularGanadoresPorDepartamento(int $anio, string $cargo): array
    {
        $filas = $this->tabla('h')
            ->join('departamentos as d', 'd.id', '=', 'h.departamento_id')
            ->where('h.anio', $anio)
            ->where('h.cargo', $cargo)
            ->when(self::esCorporacion($cargo),
                fn ($q) => $q->groupBy('h.departamento_id', 'd.nombre', 'h.partido')
                    ->selectRaw('h.departamento_id, d.nombre as departamento, h.partido, NULL as candidato, SUM(h.votos) as votos'),
                fn ($q) => $q->groupBy('h.departamento_id', 'd.nombre', 'h.partido', 'h.candidato')
                    ->selectRaw('h.departamento_id, d.nombre as departamento, h.partido, h.candidato, SUM(h.votos) as votos'))
            ->get()
            ->groupBy('departamento_id');

        return $filas->map(function (Collection $g) {
            $orden = $g->sortByDesc('votos')->values();
            $total = (int) $g->sum('votos');
            $primero = $orden[0];
            $segundo = $orden[1] ?? null;

            return [
                'departamento_id' => (int) $primero->departamento_id,
                'departamento' => $primero->departamento,
                'partido' => $primero->partido,
                'candidato' => $primero->candidato,
                'votos' => (int) $primero->votos,
                'porcentaje' => $total ? round($primero->votos * 100 / $total, 1) : 0,
                'segundo' => $segundo ? ($segundo->candidato ?: $segundo->partido) : null,
                'margen' => $segundo && $total ? round(($primero->votos - $segundo->votos) * 100 / $total, 1) : null,
                'color' => self::colorPartido($primero->partido),
            ];
        })->sortBy('departamento')->values()->all();
    }

    /**
     * Registros de la campaña del candidato por municipio de votación, para
     * cruzar con el histórico. En "Todos los tenants" no aplica (null).
     */
    public function registrosCampania(): ?array
    {
        if (app(TenantContext::class)->esTodos()) {
            return null;
        }

        return PublicRegistration::query()
            ->whereNotNull('voting_municipality_id')
            ->groupBy('voting_municipality_id')
            ->selectRaw('voting_municipality_id, COUNT(*) as total')
            ->pluck('total', 'voting_municipality_id')
            ->map(fn ($t) => (int) $t)
            ->all();
    }

    /**
     * Color fijo por partido: los grandes con su color conocido, el resto
     * con un tono estable sacado del nombre.
     */
    public static function colorPartido(?string $partido): string
    {
        $n = self::normalizar((string) $partido);

        $conocidos = [
            'VOTO EN BLANCO' => '#94a3b8',
            'LIBERAL' => '#dc2626',
            'CONSERVADOR' => '#1d4ed8',
            'CENTRO DEMOCRATICO' => '#0284c7',
            'PACTO HISTORICO' => '#7c3aed',
            'CAMBIO RADICAL' => '#be185d',
            'DE LA U' => '#ea580c',
            'UNIDAD NACIONAL' => '#ea580c',
            'ALIANZA VERDE' => '#16a34a',
            'POLO DEMOCRATICO' => '#ca8a04',
            'MIRA' => '#0891b2',
            'COLOMBIA HUMANA' => '#9333ea',
            'COMUNES' => '#b91c1c',
            'NUEVO LIBERALISMO' => '#e11d48',
            'SALVACION NACIONAL' => '#0f766e',
            'DEFENSORES DE LA PATRIA' => '#1e3a8a',
        ];

        foreach ($conocidos as $clave => $color) {
            if (str_contains($n, $clave)) {
                return $color;
            }
        }

        $tono = hexdec(substr(md5($n), 0, 4)) % 360;

        return self::hslAHex($tono, 55, 45);
    }

    public static function normalizar(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        $s = strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $s = preg_replace('/[^A-Z0-9 ]+/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private static function hslAHex(int $h, int $s, int $l): string
    {
        $s /= 100;
        $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (intdiv($h, 60)) {
            0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x],
            3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x],
        };

        return sprintf('#%02x%02x%02x', round(($r + $m) * 255), round(($g + $m) * 255), round(($b + $m) * 255));
    }
}
