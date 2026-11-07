<?php

namespace App\Livewire\Dashboard;

use App\Enums\CargoEnum;
use App\Models\Departamento;
use App\Models\PartidoIdeologia;
use App\Models\PublicRegistration;
use App\Services\Historico\AnalisisElectoral;
use App\Services\Historico\HistoricoElectoralStats;
use App\Services\Historico\PlanCampania;
use App\Support\Partidos;
use App\Support\TenantContext;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Análisis electoral: lecturas sobre el histórico para planear campaña.
 *
 * 1. Fortines: dónde saca más votos cada partido y cómo cambió entre elecciones.
 * 2. Municipios: dónde está la gente (potencial electoral) y cuánta vota.
 * 3. Tendencia: izquierda, centro o derecha por municipio y año (tabla editable).
 * 4. Alcaldes y gobernación: el alcalde en funciones frente a cada candidato a
 *    gobernación en su municipio (coincidencia estadística, no prueba).
 * 5–8. Plan de campaña (PlanCampania): meta de votos y avance de los registros,
 *    votos por conquistar, municipios en disputa y orden de prioridades.
 */
class Analisis extends Component
{
    public const SECCIONES = [
        'fortines' => 'Fortines de cada partido',
        'municipios' => 'Tamaño de los municipios',
        'tendencia' => 'Tendencia política',
        'alcaldes' => 'Alcaldes y gobernación',
        'meta' => 'Meta y avance',
        'conquistar' => 'Votos por conquistar',
        'disputa' => 'Municipios en disputa',
        'prioridades' => 'Prioridades',
    ];

    /** Secciones que todavía no están hechas */
    public const PROXIMAMENTE = [];

    #[Url]
    public string $seccion = 'fortines';

    #[Url(as: 'depto')]
    public ?int $departamentoId = null;

    #[Url]
    public ?string $cargo = null;

    #[Url]
    public ?string $partido = null;

    #[Url]
    public string $orden = 'porcentaje';

    /** Tendencia: año del mapa. Alcaldes: año de la gobernación. Por defecto, el último. */
    #[Url]
    public ?int $anio = null;

    /** Alcaldes: candidato a gobernación que pinta el mapa ("clave|nombre") */
    #[Url(as: 'gob')]
    public ?string $gobernador = null;

    /** Meta: votos que el usuario fija a mano (si no, la calculada) */
    #[Url(as: 'meta')]
    public ?int $metaManual = null;

    /** Meta: curules de la corporación fijadas a mano */
    #[Url(as: 'curules')]
    public ?int $curulesManual = null;

    /** Prioridades: enfoque (PlanCampania::ENFOQUES) */
    #[Url]
    public string $enfoque = 'equilibrado';

    /** Prioridades: bloque del candidato para medir afinidad ('' = ninguno) */
    #[Url]
    public ?string $afinidad = null;

    /** Filtro de la tabla de clasificación de partidos */
    public string $buscarPartido = '';

    public function mount(AnalisisElectoral $analisis): void
    {
        abort_unless(auth()->user()->can('historico.ver'), 403);

        $tenant = app(TenantContext::class)->tenant();

        $this->departamentoId ??= $tenant?->departamento_id
            ?? Departamento::where('nombre', 'META')->value('id')
            ?? Departamento::orderBy('nombre')->value('id');

        if (! array_key_exists($this->seccion, self::SECCIONES)) {
            $this->seccion = 'fortines';
        }

        $this->ajustarCargo($analisis, $tenant?->cargo?->value);
        $this->ajustarPartido($analisis, $tenant?->partido ? Partidos::clave($tenant->partido) : null);
    }

    private function ajustarCargo(AnalisisElectoral $analisis, ?string $preferido = null): void
    {
        $cargos = $analisis->cargos($this->departamentoId);
        if (! in_array($this->cargo, $cargos, true)) {
            $this->cargo = collect([$preferido, 'gobernacion', 'alcaldia'])->first(fn ($c) => in_array($c, $cargos, true))
                ?? ($cargos[0] ?? null);
        }
    }

    private function ajustarPartido(AnalisisElectoral $analisis, ?string $preferido = null): void
    {
        if (! $this->cargo) {
            $this->partido = null;

            return;
        }

        $partidos = $analisis->datos($this->departamentoId, $this->cargo)['partidos'];
        if ($this->partido === null || ! isset($partidos[$this->partido])) {
            $this->partido = $preferido !== null && isset($partidos[$preferido]) ? $preferido
                : collect($partidos)->keys()->first(fn ($k) => $k !== 'VOTO EN BLANCO');
        }
    }

    public function updatedDepartamentoId(): void
    {
        $analisis = app(AnalisisElectoral::class);
        $this->ajustarCargo($analisis);
        $this->ajustarPartido($analisis);
    }

    public function updatedCargo(): void
    {
        $this->ajustarPartido(app(AnalisisElectoral::class));
        // La meta y las curules a mano son de un cargo
        $this->metaManual = null;
        $this->curulesManual = null;
    }

    /**
     * Registros del candidato elegido por municipio de votación (solo conteos).
     * En "Todos" no hay candidato: null.
     *
     * @return array<int, int>|null
     */
    private function registrosPorMunicipio(): ?array
    {
        if (! app(TenantContext::class)->id()) {
            return null;
        }

        return PublicRegistration::query()
            ->whereNotNull('voting_municipality_id')
            ->selectRaw('voting_municipality_id, COUNT(*) as total')
            ->groupBy('voting_municipality_id')
            ->pluck('total', 'voting_municipality_id')
            ->map(fn ($n) => (int) $n)->all();
    }

    /** Bloque del partido del candidato, para la afinidad de las prioridades. */
    private function bloqueCandidato(array $ideologias): ?string
    {
        $partido = app(TenantContext::class)->tenant()?->partido;
        if (! $partido) {
            return null;
        }

        $ideologia = $ideologias[Partidos::clave($partido)] ?? AnalisisElectoral::inferir($partido, $ideologias);

        return PartidoIdeologia::bloque($ideologia);
    }

    public function verSeccion(string $seccion): void
    {
        if (array_key_exists($seccion, self::SECCIONES)) {
            $this->seccion = $seccion;
        }
    }

    /** Editar la ideología de los partidos: solo usuarios globales (la tabla es de todos). */
    public function puedeClasificar(): bool
    {
        $user = auth()->user();

        return $user->esGlobal() && $user->can('historico.importar');
    }

    public function clasificar(string $clave, string $ideologia): void
    {
        abort_unless($this->puedeClasificar(), 403);

        if ($ideologia === '') {
            PartidoIdeologia::where('clave', $clave)->delete();

            return;
        }

        abort_unless(array_key_exists($ideologia, PartidoIdeologia::IDEOLOGIAS) && mb_strlen($clave) <= 191, 422);
        PartidoIdeologia::updateOrCreate(['clave' => $clave], ['ideologia' => $ideologia]);
    }

    public function anotar(string $clave, string $nota): void
    {
        abort_unless($this->puedeClasificar(), 403);

        PartidoIdeologia::where('clave', $clave)->update(['nota' => mb_substr(trim($nota), 0, 500) ?: null]);
    }

    public function render(AnalisisElectoral $analisis)
    {
        $departamento = Departamento::find($this->departamentoId);
        $cargos = $departamento ? $analisis->cargos($departamento->id) : [];
        $datos = $departamento && $this->cargo ? $analisis->datos($departamento->id, $this->cargo) : null;

        $fortines = null;
        $municipios = null;
        $tendencia = null;
        $clasificacion = null;
        $alcaldes = null;
        $actuales = null;
        $plan = null;
        $mapa = null;

        if ($datos && $this->seccion === 'fortines' && $this->partido) {
            $fortines = $analisis->fortines($departamento->id, $this->cargo, $this->partido);
            $fortines['municipios'] = $this->ordenar($fortines['municipios'], $fortines['ultimo']);
            $mapa = $this->mapaFortines($departamento, $fortines);
        }

        if ($datos && $this->seccion === 'municipios') {
            $municipios = $analisis->municipios($departamento->id, $this->cargo);
            $mapa = $this->mapaMunicipios($departamento, $municipios);
        }

        if ($datos && $this->seccion === 'tendencia') {
            $ideologias = PartidoIdeologia::query()->get()->keyBy('clave');
            $tendencia = $analisis->tendencia($departamento->id, $this->cargo, $ideologias->map->ideologia->all());
            if (! in_array($this->anio, $tendencia['anios'], true)) {
                $this->anio = $tendencia['ultimo'];
            }
            $mapa = $this->mapaTendencia($departamento, $tendencia);
            $clasificacion = $this->clasificacion($datos['partidos'], $ideologias, $tendencia['inferidas']);
        }

        if ($departamento && $this->seccion === 'alcaldes') {
            $anios = $analisis->aniosAlcaldes($departamento->id);
            if ($anios) {
                if (! in_array($this->anio, $anios, true)) {
                    $this->anio = end($anios);
                }
                $ideologias = PartidoIdeologia::mapa();
                $alcaldes = $analisis->alcaldes($departamento->id, $this->anio, $ideologias) + ['anios' => $anios];
                if (! isset($alcaldes['candidatos'][$this->gobernador])) {
                    $this->gobernador = array_key_first($alcaldes['candidatos']);
                }
                $mapa = $this->gobernador ? $this->mapaAlcaldes($departamento, $alcaldes) : null;
                $actuales = $analisis->alcaldesActuales($departamento->id, $ideologias);
            }
        }

        if ($datos && in_array($this->seccion, ['meta', 'conquistar', 'disputa', 'prioridades'], true)) {
            [$plan, $mapa] = $this->plan(app(PlanCampania::class), $departamento);
        }

        $this->dispatch('analisis-mapa', mapa: $mapa);

        return view('livewire.dashboard.analisis', [
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
            'departamento' => $departamento,
            'cargos' => collect($cargos)->map(fn ($c) => CargoEnum::from($c)),
            'partidos' => $datos ? array_slice($datos['partidos'], 0, 80, true) : [],
            'fortines' => $fortines,
            'municipios' => $municipios,
            'tendencia' => $tendencia,
            'clasificacion' => $clasificacion,
            'alcaldes' => $alcaldes,
            'actuales' => $actuales,
            'plan' => $plan,
            'tenantNombre' => app(TenantContext::class)->tenant()?->nombre,
            'mapa' => $mapa,
            'partidoCandidato' => app(TenantContext::class)->tenant()?->partido
                ? Partidos::clave(app(TenantContext::class)->tenant()->partido) : null,
        ])->title('Análisis electoral');
    }

    /** Secciones 5–8: datos y mapa. */
    private function plan(PlanCampania $plan, Departamento $departamento): array
    {
        $registros = $this->registrosPorMunicipio();
        $ideologias = PartidoIdeologia::mapa();
        $this->metaManual = $this->metaManual > 0 ? min($this->metaManual, 50_000_000) : null;
        $this->curulesManual = $this->curulesManual > 0 ? min($this->curulesManual, 120) : null;

        switch ($this->seccion) {
            case 'meta':
                $m = $plan->meta($departamento->id, $this->cargo, $registros, $this->metaManual, $this->curulesManual);

                return [$m, $m['aplica'] ? $this->mapaMeta($departamento, $m) : null];

            case 'conquistar':
                $c = $plan->conquistar($departamento->id, $this->cargo);

                return [$c, $this->mapaConquistar($departamento, $c)];

            case 'disputa':
                $d = $plan->disputa($departamento->id, $this->cargo, $ideologias);

                return [$d, $this->mapaDisputa($departamento, $d)];

            default:
                if ($this->afinidad === null) {
                    $this->afinidad = $this->bloqueCandidato($ideologias) ?? '';
                }
                if (! array_key_exists($this->enfoque, PlanCampania::ENFOQUES)) {
                    $this->enfoque = 'equilibrado';
                }
                $bloque = array_key_exists($this->afinidad, PartidoIdeologia::BLOQUES) ? $this->afinidad : null;
                $p = $plan->prioridades($departamento->id, $this->cargo, $bloque, $this->enfoque,
                    $registros, $this->metaManual, $this->curulesManual, $ideologias);

                return [$p, $this->mapaPrioridades($departamento, $p)];
        }
    }

    /** Verde según el avance de los registros; sin candidato, azul según la meta. */
    private function mapaMeta(Departamento $departamento, array $m): array
    {
        $conRegistros = $m['registros'] !== null;
        $maxAvance = max(1, (float) collect($m['municipios'])->max('avance'));
        $maxMeta = max(1, (int) collect($m['municipios'])->max('meta'));
        $n = fn ($v) => number_format((int) $v, 0, ',', '.');

        return [
            'geo' => $this->geo($departamento),
            'municipios' => collect($m['municipios'])->filter(fn ($r) => $r['codigo_dane'])
                ->mapWithKeys(fn ($r) => [$r['codigo_dane'] => [
                    'color' => $conRegistros ? '#16a34a' : '#2563eb',
                    'opacidad' => $conRegistros
                        ? ($r['avance'] ? round(0.12 + 0.78 * $r['avance'] / $maxAvance, 2) : 0.05)
                        : ($r['meta'] ? round(0.1 + 0.8 * sqrt($r['meta'] / $maxMeta), 2) : 0.04),
                    'lineas' => [
                        $r['municipio'],
                        $r['meta'] !== null ? 'Meta: '.$n($r['meta']).' votos' : 'Sin meta',
                        $conRegistros ? 'Registros: '.$n($r['registros']).($r['avance'] !== null ? " ({$r['avance']}% de la meta)" : '') : '',
                    ],
                ]])->all(),
        ];
    }

    /** Morado según cuántos no votaron o votaron en blanco. */
    private function mapaConquistar(Departamento $departamento, array $c): array
    {
        $valor = fn ($r) => $r['conquistables'] ?? $r['blanco'];
        $maximo = max(1, (int) collect($c['municipios'])->max($valor));
        $n = fn ($v) => number_format((int) $v, 0, ',', '.');

        return [
            'geo' => $this->geo($departamento),
            'municipios' => collect($c['municipios'])->filter(fn ($r) => $r['codigo_dane'])
                ->mapWithKeys(fn ($r) => [$r['codigo_dane'] => [
                    'color' => '#7c3aed',
                    'opacidad' => round(0.08 + 0.82 * sqrt($valor($r) / $maximo), 2),
                    'lineas' => [
                        $r['municipio'],
                        $r['no_votaron'] !== null ? 'No votaron: '.$n($r['no_votaron'])." (votó el {$r['participacion']}%)" : 'Sin potencial electoral',
                        'En blanco: '.$n($r['blanco'])." ({$r['blanco_pct']}%)",
                        $r['solo_presidencial'] ? 'Votaron por presidente y no aquí: '.$n($r['solo_presidencial']) : '',
                    ],
                ]])->all(),
        ];
    }

    /** Naranja = disputado; color del bloque ganador: claro = inclinado, fuerte = fortín. */
    private function mapaDisputa(Departamento $departamento, array $d): array
    {
        return [
            'geo' => $this->geo($departamento),
            'municipios' => collect($d['municipios'])->filter(fn ($r) => $r['codigo_dane'])
                ->mapWithKeys(fn ($r) => [$r['codigo_dane'] => [
                    'color' => $r['estado'] === 'disputado' ? '#ea580c' : (PartidoIdeologia::BLOQUES[$r['bloque']][1] ?? '#78716c'),
                    'opacidad' => match ($r['estado']) {
                        'disputado' => round(0.3 + 0.6 * $r['puntaje'] / 100, 2),
                        'fortin' => 0.8,
                        default => 0.3,
                    },
                    'lineas' => [
                        $r['municipio'],
                        PlanCampania::ESTADOS[$r['estado']]." · puntaje {$r['puntaje']}",
                        'Ganó: '.$r['ganador_nombre']." por {$r['margen']} pts",
                        $r['segundo_nombre'] ? 'Segundo: '.$r['segundo_nombre'] : '',
                    ],
                ]])->all(),
        ];
    }

    /** Color de la acción sugerida, más intenso cuanto más prioritario. */
    private function mapaPrioridades(Departamento $departamento, array $p): array
    {
        $maximo = max(1, (int) collect($p['municipios'])->max('puntaje'));

        return [
            'geo' => $this->geo($departamento),
            'municipios' => collect($p['municipios'])->filter(fn ($r) => $r['codigo_dane'])
                ->mapWithKeys(function ($r) use ($maximo) {
                    $accion = PlanCampania::ACCIONES[$r['accion']];

                    return [$r['codigo_dane'] => [
                        'color' => $accion[2],
                        'opacidad' => round(0.15 + 0.75 * $r['puntaje'] / $maximo, 2),
                        'lineas' => [
                            $r['municipio'],
                            "Prioridad #{$r['puesto']} · {$r['puntaje']} pts",
                            $accion[0].': '.$accion[1],
                        ],
                    ]];
                })->all(),
        ];
    }

    private function ordenar(array $filas, ?int $ultimo): array
    {
        $valor = fn ($r) => match ($this->orden) {
            'votos' => $r['por_anio'][$ultimo]['votos'] ?? 0,
            'cambio' => $r['cambio'] ?? -INF,
            'potencial' => $r['potencial'] ?? 0,
            default => $r['por_anio'][$ultimo]['porcentaje'] ?? -1,
        };

        usort($filas, fn ($a, $b) => $valor($b) <=> $valor($a));

        return $filas;
    }

    /**
     * Partidos del departamento y cargo con su ideología, para editarla.
     * Los sin clasificar van primero; luego, de más a menos votados.
     */
    private function clasificacion(array $partidos, $ideologias, array $inferidas): array
    {
        $buscar = HistoricoElectoralStats::normalizar(trim($this->buscarPartido));

        return collect($partidos)
            ->reject(fn ($p, $clave) => $clave === 'VOTO EN BLANCO')
            ->filter(fn ($p, $clave) => $buscar === '' || str_contains(HistoricoElectoralStats::normalizar($p['nombre'].' '.$clave), $buscar))
            ->map(fn ($p, $clave) => [
                'clave' => $clave,
                'nombre' => $p['nombre'],
                'votos' => $p['votos'],
                'ideologia' => $ideologias[$clave]->ideologia ?? null,
                'nota' => $ideologias[$clave]->nota ?? null,
                'inferida' => $inferidas[$clave] ?? null,
            ])
            ->sortBy(fn ($p) => [$p['ideologia'] === null ? 0 : 1, -$p['votos']])
            ->take(60)->values()->all();
    }

    /** Color de la ideología del ganador del año elegido. */
    private function mapaTendencia(Departamento $departamento, array $t): array
    {
        $a = $this->anio;

        return [
            'geo' => $this->geo($departamento),
            'municipios' => collect($t['municipios'])->filter(fn ($r) => $r['codigo_dane'])
                ->mapWithKeys(function ($r) use ($a) {
                    $x = $r['por_anio'][$a];
                    $ideo = $x['ideologia'] ? PartidoIdeologia::IDEOLOGIAS[$x['ideologia']] : null;
                    $pct = $x['porcentaje'];

                    return [$r['codigo_dane'] => [
                        'color' => $ideo[2] ?? PartidoIdeologia::SIN_CLASIFICAR_COLOR,
                        'opacidad' => $x['hubo'] ? 0.8 : 0.05,
                        'lineas' => [
                            $r['municipio'],
                            $x['hubo'] ? 'Ganó: '.$x['ganador_nombre'].' ('.($ideo[0] ?? 'sin clasificar').')' : 'Sin datos ese año',
                            $x['hubo'] ? "Izq. {$pct['izquierda']}% · Centro {$pct['centro']}% · Der. {$pct['derecha']}%" : '',
                            $r['giro'] ? 'Cambió de bloque en la última elección' : '',
                        ],
                    ]];
                })->all(),
        ];
    }

    /**
     * Cuánto sacó el candidato elegido por encima (verde) o por debajo (rojo)
     * de su promedio departamental, con el alcalde en funciones.
     */
    private function mapaAlcaldes(Departamento $departamento, array $a): array
    {
        $id = $this->gobernador;
        $maximo = max(1, (float) collect($a['municipios'])->max(fn ($r) => abs($r['candidatos'][$id]['sobre'] ?? 0)));

        return [
            'geo' => $this->geo($departamento),
            'municipios' => collect($a['municipios'])->filter(fn ($r) => $r['codigo_dane'])
                ->mapWithKeys(function ($r) use ($id, $maximo) {
                    $x = $r['candidatos'][$id];
                    $sobre = $x['sobre'] ?? 0;
                    $alc = $r['alcalde'];

                    return [$r['codigo_dane'] => [
                        'color' => $sobre >= 0 ? '#16a34a' : '#dc2626',
                        'opacidad' => round(0.1 + 0.8 * abs($sobre) / $maximo, 2),
                        'lineas' => [
                            $r['municipio'],
                            "{$x['porcentaje']}% (".($sobre >= 0 ? '+' : '')."{$sobre} pts sobre su promedio)",
                            $alc ? "Alcalde: {$alc['candidato']} ({$alc['partido']})" : 'Sin datos del alcalde',
                            $x['aliado'] ? 'Comparten partido' : '',
                        ],
                    ]];
                })->all(),
        ];
    }

    private function geo(Departamento $departamento): string
    {
        return asset('data/deptos-web/'.rawurlencode($departamento->nombre).'.geojson');
    }

    /** Intensidad del color del partido según su porcentaje en el último año. */
    private function mapaFortines(Departamento $departamento, array $f): array
    {
        $color = HistoricoElectoralStats::colorPartido($f['partido']['nombre'] ?? $this->partido);
        $maximo = max(1, (float) collect($f['municipios'])->max(fn ($r) => $r['por_anio'][$f['ultimo']]['porcentaje'] ?? 0));

        return [
            'geo' => $this->geo($departamento),
            'municipios' => collect($f['municipios'])->filter(fn ($r) => $r['codigo_dane'])
                ->mapWithKeys(function ($r) use ($f, $color, $maximo) {
                    $u = $r['por_anio'][$f['ultimo']];

                    return [$r['codigo_dane'] => [
                        'color' => $color,
                        'opacidad' => $u['porcentaje'] ? round(0.12 + 0.78 * $u['porcentaje'] / $maximo, 2) : 0.04,
                        'lineas' => [
                            $r['municipio'],
                            ! $u['participo'] ? 'No se presentó en '.$f['ultimo'] : ($u['hubo'] ? "{$u['porcentaje']}% · ".number_format($u['votos'], 0, ',', '.').' votos'.($u['gano'] ? ' · ganó' : '') : 'Sin datos ese año'),
                            $r['cambio'] !== null ? 'Cambio: '.($r['cambio'] >= 0 ? '+' : '').$r['cambio'].' pts' : '',
                        ],
                    ]];
                })->all(),
        ];
    }

    /** Intensidad según el potencial electoral (dónde está la gente). */
    private function mapaMunicipios(Departamento $departamento, array $m): array
    {
        $maximo = max(1, (int) collect($m['municipios'])->max('potencial'));

        return [
            'geo' => $this->geo($departamento),
            'municipios' => collect($m['municipios'])->filter(fn ($r) => $r['codigo_dane'])
                ->mapWithKeys(fn ($r) => [$r['codigo_dane'] => [
                    'color' => '#2563eb',
                    // Raíz cuadrada: si no, la capital opaca a todos los demás
                    'opacidad' => $r['potencial'] ? round(0.1 + 0.8 * sqrt($r['potencial'] / $maximo), 2) : 0.04,
                    'lineas' => [
                        $r['municipio'],
                        $r['potencial'] !== null ? number_format($r['potencial'], 0, ',', '.')." cédulas ({$r['peso']}% del depto)" : 'Sin potencial',
                        $r['participacion'] !== null ? "Votó aprox. {$r['participacion']}%" : '',
                    ],
                ]])->all(),
        ];
    }
}
