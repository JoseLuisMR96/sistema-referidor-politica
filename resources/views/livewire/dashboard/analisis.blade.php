@php
    use App\Livewire\Dashboard\Analisis;
    use App\Services\Historico\HistoricoElectoralStats;

    $select = 'mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm bg-white';
    $num = fn ($n) => $n === null ? '—' : number_format($n, 0, ',', '.');
@endphp

<x-slot name="header">
    <div>
        <h2 class="font-extrabold text-xl text-slate-900 leading-tight">Análisis electoral</h2>
        <div class="text-sm text-slate-500">Lecturas del histórico para planear la campaña</div>
    </div>
</x-slot>

<div class="space-y-6">
    {{-- ===== Secciones ===== --}}
    <div class="flex flex-wrap gap-2">
        @foreach (Analisis::SECCIONES as $clave => $titulo)
            @php($proximamente = in_array($clave, Analisis::PROXIMAMENTE, true))
            <button type="button" wire:click="verSeccion('{{ $clave }}')" @disabled($proximamente)
                class="rounded-xl px-4 py-2 text-sm font-semibold border
                    {{ $seccion === $clave ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}
                    {{ $proximamente ? 'opacity-50 cursor-not-allowed' : '' }}">
                {{ $loop->iteration }}. {{ $titulo }}
                @if ($proximamente)
                    <span class="ml-1 text-[10px] uppercase">próximamente</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- ===== Filtros ===== --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <label class="text-xs font-semibold text-slate-700">Departamento
            <select wire:model.live="departamentoId" class="{{ $select }}">
                @foreach ($departamentos as $d)
                    <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                @endforeach
            </select>
        </label>

        @if ($seccion !== 'alcaldes')
            <label class="text-xs font-semibold text-slate-700">Cargo
                <select wire:model.live="cargo" class="{{ $select }}">
                    @foreach ($cargos as $c)
                        <option value="{{ $c->value }}">{{ $c->label() }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($seccion === 'alcaldes' && $alcaldes)
            <label class="text-xs font-semibold text-slate-700">Elección a gobernación
                <select wire:model.live="anio" class="{{ $select }}">
                    @foreach ($alcaldes['anios'] as $a)
                        <option value="{{ $a }}">{{ $a }} (alcaldes elegidos en {{ $a - 4 }})</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-700 lg:col-span-2">Candidato a gobernación (mapa)
                <select wire:model.live="gobernador" class="{{ $select }}">
                    @foreach ($alcaldes['candidatos'] as $id => $c)
                        <option value="{{ $id }}">{{ $c['candidato'] ?: $c['partido'] }} — {{ $c['partido'] }} ({{ $c['porcentaje'] }}%)</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($seccion === 'fortines')
            <label class="text-xs font-semibold text-slate-700 lg:col-span-2">Partido o coalición
                <select wire:model.live="partido" class="{{ $select }}">
                    @foreach ($partidos as $clave => $p)
                        <option value="{{ $clave }}">
                            {{ $p['nombre'] }}{{ $clave === $partidoCandidato ? ' · tu partido' : '' }} — {{ $num($p['votos']) }} votos ({{ implode(', ', array_keys(array_filter($p['por_anio']))) }})
                        </option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($seccion === 'tendencia' && $tendencia)
            <label class="text-xs font-semibold text-slate-700">Año del mapa
                <select wire:model.live="anio" class="{{ $select }}">
                    @foreach ($tendencia['anios'] as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    <div wire:loading.delay class="text-xs text-slate-500">Cargando…</div>

    @if (! $cargos->count())
        <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-sm text-slate-500">
            No hay resultados electorales cargados para este departamento.
        </div>
    @endif

    {{-- ===== 1. Fortines ===== --}}
    @if ($seccion === 'fortines' && $fortines && $fortines['partido'])
        @php($u = $fortines['ultimo'])

        @if ($u !== $fortines['ultimo_depto'])
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $fortines['partido']['nombre'] }} no tuvo candidato en {{ $fortines['ultimo_depto'] }}.
                El mapa y los rankings muestran {{ $u }}, su última elección en {{ $departamento->nombre }}.
            </div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach ($fortines['anios'] as $a)
                @php($d = $fortines['departamento'][$a])
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 {{ $d['participo'] ? '' : 'opacity-60' }}">
                    <div class="text-xs uppercase tracking-wider text-slate-400">{{ $a }} · todo {{ $departamento->nombre }}</div>
                    @if ($d['participo'])
                        <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $d['porcentaje'] ?? '—' }}%</div>
                        <div class="text-sm text-slate-600">{{ $num($d['votos']) }} votos · ganó en {{ $d['ganados'] }} municipio(s)</div>
                    @else
                        <div class="mt-1 text-lg font-bold text-slate-500">No se presentó</div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                    <div class="text-lg font-extrabold text-slate-900">{{ $fortines['partido']['nombre'] }}</div>
                    <div class="text-xs text-slate-500">Color más intenso = mayor porcentaje en {{ $u }}</div>
                </div>
                <div id="analisis-mapa" class="h-[440px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="text-lg font-extrabold text-slate-900 mb-1">Dónde es más fuerte</div>
                <div class="text-xs text-slate-500 mb-3">Los 8 municipios con mayor porcentaje en {{ $u }}</div>
                <div class="space-y-2">
                    @foreach (collect($fortines['municipios'])->filter(fn ($r) => $r['por_anio'][$u]['participo'])->sortByDesc(fn ($r) => $r['por_anio'][$u]['porcentaje'] ?? -1)->take(8) as $r)
                        @php($x = $r['por_anio'][$u])
                        <div wire:key="top-{{ $r['id'] }}">
                            <div class="flex justify-between text-sm">
                                <span class="font-semibold text-slate-900">{{ $r['municipio'] }}</span>
                                <span class="tabular-nums text-slate-700">{{ $x['porcentaje'] ?? '—' }}% · {{ $num($x['votos']) }}</span>
                            </div>
                            <div class="h-2 rounded bg-slate-100">
                                <div class="h-2 rounded bg-indigo-600" style="width: {{ min(100, $x['porcentaje'] ?? 0) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                <div>
                    <div class="text-lg font-extrabold text-slate-900">Municipio por municipio</div>
                    <div class="text-xs text-slate-500">
                        Porcentaje sobre los votos válidos de ese municipio. "Cambio" compara {{ $u }} con {{ $fortines['previo'] ?? '—' }}.
                        <span class="font-semibold">★</span> = ganó ahí ese año.
                    </div>
                </div>
                <label class="text-xs font-semibold text-slate-700">Ordenar por
                    <select wire:model.live="orden" class="{{ $select }}">
                        <option value="porcentaje">Porcentaje en {{ $u }}</option>
                        <option value="votos">Votos en {{ $u }}</option>
                        <option value="cambio">Crecimiento</option>
                        <option value="potencial">Tamaño del municipio</option>
                    </select>
                </label>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Municipio</th>
                            <th class="py-2 px-3 text-right">Potencial</th>
                            @foreach ($fortines['anios'] as $a)
                                <th class="py-2 px-3 text-right">{{ $a }}</th>
                            @endforeach
                            <th class="py-2 px-3 text-right">Cambio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($fortines['municipios'] as $r)
                            <tr wire:key="f-{{ $r['id'] }}">
                                <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-slate-600">{{ $num($r['potencial']) }}</td>
                                @foreach ($fortines['anios'] as $a)
                                    @php($x = $r['por_anio'][$a])
                                    <td class="py-2 px-3 text-right tabular-nums">
                                        @if (! $x['participo'])
                                            <span class="text-xs text-slate-400">no se presentó</span>
                                        @elseif ($x['hubo'])
                                            <div class="font-semibold text-slate-900">{{ $x['porcentaje'] }}%{!! $x['gano'] ? ' <span title="Ganó">★</span>' : '' !!}</div>
                                            <div class="text-xs text-slate-500">{{ $num($x['votos']) }}</div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-2 px-3 text-right tabular-nums font-semibold
                                    {{ $r['cambio'] === null ? 'text-slate-400' : ($r['cambio'] >= 0 ? 'text-emerald-600' : 'text-rose-600') }}">
                                    {{ $r['cambio'] === null ? '—' : ($r['cambio'] >= 0 ? '+' : '').$r['cambio'].' pts' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-xs text-slate-500">
                Los partidos se siguen entre años aunque cambien de nombre (p. ej. "Partido Social de Unidad Nacional" y "Partido de la U").
                Las coaliciones cuentan aparte: sus votos no se reparten entre los partidos que la forman.
            </div>
        </div>
    @endif

    {{-- ===== 2. Tamaño de los municipios ===== --}}
    @if ($seccion === 'municipios' && $municipios)
        @php($lista = collect($municipios['municipios']))
        @php($top5 = $lista->take(5))

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">Potencial electoral {{ $municipios['anio_potencial'] }}</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($municipios['total_potencial']) }}</div>
                <div class="text-sm text-slate-600">cédulas habilitadas en {{ $lista->count() }} municipios</div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">Concentración</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $top5->last()['acumulado'] ?? '—' }}%</div>
                <div class="text-sm text-slate-600">del potencial está en los 5 municipios más grandes</div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">El más grande</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $lista->first()['municipio'] ?? '—' }}</div>
                <div class="text-sm text-slate-600">{{ $lista->first()['peso'] ?? '—' }}% del departamento</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                    <div class="text-lg font-extrabold text-slate-900">Dónde está la gente</div>
                    <div class="text-xs text-slate-500">Color más intenso = más cédulas habilitadas</div>
                </div>
                <div id="analisis-mapa" class="h-[440px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
            </div>
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="text-lg font-extrabold text-slate-900 mb-1">Cómo se reparte</div>
                <div class="text-xs text-slate-500 mb-3">Peso de cada municipio en el potencial del departamento</div>
                <div class="space-y-2">
                    @foreach ($lista->take(10) as $r)
                        <div wire:key="peso-{{ $r['id'] }}">
                            <div class="flex justify-between text-sm">
                                <span class="font-semibold text-slate-900">{{ $r['municipio'] }}</span>
                                <span class="tabular-nums text-slate-700">{{ $r['peso'] }}%</span>
                            </div>
                            <div class="h-2 rounded bg-slate-100">
                                <div class="h-2 rounded bg-indigo-600" style="width: {{ min(100, $r['peso'] ?? 0) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="text-lg font-extrabold text-slate-900">Todos los municipios</div>
            <div class="text-xs text-slate-500 mb-4">
                Potencial = cédulas habilitadas ({{ $municipios['anio_potencial'] }}, Registraduría).
                Votos válidos = {{ collect($cargos)->firstWhere('value', $cargo)?->label() }} {{ $municipios['ultimo'] }}.
                La participación es aproximada: votos válidos (sin nulos ni no marcados) sobre el potencial actual.
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">#</th>
                            <th class="py-2 pr-4">Municipio</th>
                            <th class="py-2 px-3 text-right">Potencial</th>
                            <th class="py-2 px-3 text-right">% depto</th>
                            <th class="py-2 px-3 text-right">Acumulado</th>
                            <th class="py-2 px-3 text-right">Mujeres</th>
                            <th class="py-2 px-3 text-right">Mesas</th>
                            <th class="py-2 px-3 text-right">Votos válidos {{ $municipios['ultimo'] }}</th>
                            <th class="py-2 px-3 text-right">Participación aprox.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($lista as $r)
                            <tr wire:key="m-{{ $r['id'] }}">
                                <td class="py-2 pr-4 text-slate-400 tabular-nums">{{ $loop->iteration }}</td>
                                <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums font-bold">{{ $num($r['potencial']) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r['peso'] ?? '—' }}%</td>
                                <td class="py-2 px-3 text-right tabular-nums text-slate-500">{{ $r['acumulado'] ?? '—' }}%</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r['mujeres'] ?? '—' }}%</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['mesas']) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['votos']) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r['participacion'] !== null ? $r['participacion'].'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===== 3. Tendencia política ===== --}}
    @if ($seccion === 'tendencia' && $tendencia)
        @php($bloques = \App\Models\PartidoIdeologia::BLOQUES)
        @php($ideologiasDef = \App\Models\PartidoIdeologia::IDEOLOGIAS)
        @php($u = $tendencia['ultimo'])
        @php($giros = collect($tendencia['municipios'])->where('giro', true))

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach ($tendencia['anios'] as $a)
                @php($d = $tendencia['departamento'][$a])
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">{{ $a }} · todo {{ $departamento->nombre }}</div>
                    <div class="mt-2 flex h-3 rounded overflow-hidden bg-slate-100">
                        @foreach ($bloques as $b => [$nombre, $color])
                            <div style="width: {{ $d['porcentaje'][$b] ?? 0 }}%; background: {{ $color }}" title="{{ $nombre }} {{ $d['porcentaje'][$b] }}%"></div>
                        @endforeach
                        <div style="width: {{ $d['porcentaje']['sin_clasificar'] ?? 0 }}%; background: {{ \App\Models\PartidoIdeologia::SIN_CLASIFICAR_COLOR }}"></div>
                    </div>
                    <div class="mt-2 text-xs text-slate-600 space-y-0.5 tabular-nums">
                        <div><span class="font-semibold" style="color: {{ $bloques['izquierda'][1] }}">Izq.</span> {{ $d['porcentaje']['izquierda'] ?? '—' }}% · gana en {{ $d['ganados']['izquierda'] }}</div>
                        <div><span class="font-semibold" style="color: {{ $bloques['centro'][1] }}">Centro</span> {{ $d['porcentaje']['centro'] ?? '—' }}% · gana en {{ $d['ganados']['centro'] }}</div>
                        <div><span class="font-semibold" style="color: {{ $bloques['derecha'][1] }}">Der.</span> {{ $d['porcentaje']['derecha'] ?? '—' }}% · gana en {{ $d['ganados']['derecha'] }}</div>
                        @if ($d['porcentaje']['sin_clasificar'])
                            <div class="text-slate-400">Sin clasificar {{ $d['porcentaje']['sin_clasificar'] }}%</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                    <div class="text-lg font-extrabold text-slate-900">Quién ganó en {{ $anio }}</div>
                    <div class="flex flex-wrap gap-2 text-xs text-slate-600">
                        @foreach ($ideologiasDef as [$nombre, , $color])
                            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="background: {{ $color }}"></span>{{ $nombre }}</span>
                        @endforeach
                        <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="background: {{ \App\Models\PartidoIdeologia::SIN_CLASIFICAR_COLOR }}"></span>Sin clasificar</span>
                    </div>
                </div>
                <div id="analisis-mapa" class="h-[440px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="text-lg font-extrabold text-slate-900 mb-1">Cambiaron de bloque</div>
                <div class="text-xs text-slate-500 mb-3">
                    Municipios donde el ganador de {{ $u }} es de otro bloque que el de {{ $tendencia['previo'] ?? '—' }}
                </div>
                @forelse ($giros as $r)
                    @php($antes = $r['por_anio'][$tendencia['previo']])
                    @php($ahora = $r['por_anio'][$u])
                    <div class="py-2 border-b border-slate-100 last:border-0" wire:key="giro-{{ $r['id'] }}">
                        <div class="text-sm font-semibold text-slate-900">{{ $r['municipio'] }}</div>
                        <div class="text-xs text-slate-600">
                            <span class="font-semibold" style="color: {{ $bloques[$antes['bloque']][1] }}">{{ $bloques[$antes['bloque']][0] }}</span>
                            ({{ $antes['ganador_nombre'] }})
                            → <span class="font-semibold" style="color: {{ $bloques[$ahora['bloque']][1] }}">{{ $bloques[$ahora['bloque']][0] }}</span>
                            ({{ $ahora['ganador_nombre'] }})
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">Ningún municipio cambió de bloque.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="text-lg font-extrabold text-slate-900">Municipio por municipio</div>
            <div class="text-xs text-slate-500 mb-4">
                En cada año: el bloque del partido que ganó y el reparto de votos (izquierda / centro / derecha).
                "Corrimiento" compara el índice de {{ $u }} con el de {{ $tendencia['previo'] ?? '—' }}:
                de −2 (todo izquierda) a +2 (todo derecha); positivo = se movió a la derecha.
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Municipio</th>
                            @foreach ($tendencia['anios'] as $a)
                                <th class="py-2 px-3">{{ $a }}</th>
                            @endforeach
                            <th class="py-2 px-3 text-right">Corrimiento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach (collect($tendencia['municipios'])->sortByDesc('potencial') as $r)
                            <tr wire:key="t-{{ $r['id'] }}">
                                <td class="py-2 pr-4 font-semibold text-slate-900">
                                    {{ $r['municipio'] }}
                                    @if ($r['giro'])
                                        <span class="ml-1 text-[10px] uppercase text-amber-600">cambió</span>
                                    @endif
                                </td>
                                @foreach ($tendencia['anios'] as $a)
                                    @php($x = $r['por_anio'][$a])
                                    <td class="py-2 px-3 min-w-[9rem]">
                                        @if ($x['hubo'])
                                            @php($ideo = $x['ideologia'] ? $ideologiasDef[$x['ideologia']] : null)
                                            <div class="text-xs font-semibold" style="color: {{ $ideo[2] ?? '#78716c' }}">{{ $ideo[0] ?? 'Sin clasificar' }}</div>
                                            <div class="text-[11px] text-slate-500 truncate max-w-[12rem]" title="{{ $x['ganador_nombre'] }}">{{ $x['ganador_nombre'] }}</div>
                                            <div class="mt-1 flex h-1.5 rounded overflow-hidden bg-slate-100">
                                                @foreach ($bloques as $b => [, $color])
                                                    <div style="width: {{ $x['porcentaje'][$b] ?? 0 }}%; background: {{ $color }}"></div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-2 px-3 text-right tabular-nums font-semibold
                                    {{ $r['cambio_indice'] === null ? 'text-slate-400' : ($r['cambio_indice'] > 0 ? 'text-blue-600' : ($r['cambio_indice'] < 0 ? 'text-red-600' : 'text-slate-500')) }}">
                                    {{ $r['cambio_indice'] === null ? '—' : ($r['cambio_indice'] > 0 ? '+' : '').number_format($r['cambio_indice'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Clasificación editable --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                <div>
                    <div class="text-lg font-extrabold text-slate-900">Ideología de los partidos</div>
                    <div class="text-xs text-slate-500 max-w-2xl">
                        Clasificación inicial tomada de Wikipedia y la prensa; es una aproximación.
                        Aplica a todos los candidatos y años. Una coalición sin clasificar toma la ideología de sus partidos
                        cuando todos están en el mismo bloque ("Inferida"); si no, y en los movimientos locales, hay que elegirla.
                        @if ($tendencia['sin_clasificar'])
                            Aquí hay {{ count($tendencia['sin_clasificar']) }} sin clasificar; van primero.
                        @endif
                    </div>
                </div>
                <label class="text-xs font-semibold text-slate-700">Buscar partido
                    <input type="search" wire:model.live.debounce.400ms="buscarPartido" class="{{ $select }}" placeholder="Nombre…">
                </label>
            </div>

            @unless ($this->puedeClasificar())
                <div class="mb-3 text-xs text-slate-500">Solo un usuario global con permiso de importar el histórico puede cambiar la clasificación.</div>
            @endunless

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Partido o coalición</th>
                            <th class="py-2 px-3 text-right">Votos (todos los años)</th>
                            <th class="py-2 px-3">Ideología</th>
                            <th class="py-2 px-3">Nota</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($clasificacion as $p)
                            <tr wire:key="ideo-{{ md5($p['clave']) }}">
                                <td class="py-2 pr-4 font-semibold text-slate-900">{{ $p['nombre'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $num($p['votos']) }}</td>
                                <td class="py-2 px-3">
                                    @if ($this->puedeClasificar())
                                        <select class="rounded-lg border border-slate-300 px-2 py-1 text-sm bg-white"
                                            wire:change="clasificar({{ \Illuminate\Support\Js::from($p['clave']) }}, $event.target.value)">
                                            <option value="" @selected(! $p['ideologia'])>{{ $p['inferida'] ? 'Inferida: '.$ideologiasDef[$p['inferida']][0] : 'Sin clasificar' }}</option>
                                            @foreach ($ideologiasDef as $valor => [$nombre])
                                                <option value="{{ $valor }}" @selected($p['ideologia'] === $valor)>{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        {{ $p['ideologia'] ? $ideologiasDef[$p['ideologia']][0] : ($p['inferida'] ? 'Inferida: '.$ideologiasDef[$p['inferida']][0] : 'Sin clasificar') }}
                                    @endif
                                </td>
                                <td class="py-2 px-3 min-w-[16rem]">
                                    @if ($this->puedeClasificar() && $p['ideologia'])
                                        <input type="text" value="{{ $p['nota'] }}" maxlength="500" placeholder="Fuente o comentario"
                                            class="w-full rounded-lg border border-slate-300 px-2 py-1 text-sm bg-white"
                                            wire:change="anotar({{ \Illuminate\Support\Js::from($p['clave']) }}, $event.target.value)">
                                    @else
                                        <span class="text-xs text-slate-500">{{ $p['nota'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===== 4. Alcaldes y gobernación ===== --}}
    @if ($seccion === 'alcaldes')
        @php($ideologiasDef = \App\Models\PartidoIdeologia::IDEOLOGIAS)
        @php($pts = fn ($v) => $v === null ? '—' : ($v > 0 ? '+' : '').number_format($v, 1, ',', '.'))
        @php($colorPts = fn ($v) => $v === null ? 'text-slate-400' : ($v >= 0 ? 'text-emerald-600' : 'text-rose-600'))

        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <span class="font-semibold">Esto es una coincidencia estadística, no una prueba.</span>
            Que un candidato a gobernación saque más donde gobierna cierto alcalde puede deberse al alcalde, pero también
            al tamaño del municipio, a su historia política o a la campaña del propio candidato.
            "Sobre su promedio" = su % en el municipio menos su % en todo el departamento.
        </div>

        @if (! $alcaldes)
            <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-sm text-slate-500">
                Para este cruce hacen falta los resultados de gobernación y los de alcaldía de 4 años antes en este departamento.
            </div>
        @else
            @php($top = array_slice($alcaldes['candidatos'], 0, 5, true))

            {{-- Resumen por candidato --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <div class="text-lg font-extrabold text-slate-900">Gobernación {{ $alcaldes['anio_gobernacion'] }} frente a los alcaldes elegidos en {{ $alcaldes['anio_alcaldia'] }}</div>
                <div class="text-xs text-slate-500 mb-4">
                    "Sobresalió en" = municipios donde fue el candidato que más superó su propio promedio.
                    "Alcalde de su partido" = el alcalde ganó con el mismo partido o comparten uno dentro de una coalición
                    (no se detecta con movimientos locales de nombre propio).
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="py-2 pr-4">Candidato</th>
                                <th class="py-2 px-3 text-right">% depto</th>
                                <th class="py-2 px-3 text-right">Ganó en</th>
                                <th class="py-2 px-3 text-right">Sobresalió en</th>
                                <th class="py-2 px-3 text-right">Alcalde de su partido</th>
                                <th class="py-2 px-3 text-right">Sobre su promedio, con ese alcalde</th>
                                <th class="py-2 px-3 text-right">En los demás</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($alcaldes['candidatos'] as $id => $c)
                                <tr wire:key="gc-{{ md5($id) }}">
                                    <td class="py-2 pr-4">
                                        <div class="font-semibold text-slate-900">{{ $c['candidato'] ?: $c['partido'] }}</div>
                                        <div class="text-xs text-slate-500">{{ $c['partido'] }}</div>
                                    </td>
                                    <td class="py-2 px-3 text-right tabular-nums font-bold">{{ $c['porcentaje'] }}%</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $c['ganados'] }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $c['favorito_en'] }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $c['con_aliado'] ?: '—' }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums font-semibold {{ $colorPts($c['sobre_con_aliado']) }}">{{ $pts($c['sobre_con_aliado']) }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums {{ $colorPts($c['sobre_sin_aliado']) }}">{{ $pts($c['sobre_sin_aliado']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    @php($g = $alcaldes['candidatos'][$gobernador] ?? null)
                    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                        <div class="text-lg font-extrabold text-slate-900">{{ $g ? ($g['candidato'] ?: $g['partido']) : '' }}</div>
                        <div class="text-xs text-slate-500">Verde = sacó más que su promedio ({{ $g['porcentaje'] ?? '—' }}%) · rojo = menos</div>
                    </div>
                    <div id="analisis-mapa" class="h-[440px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
                </div>

                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    <div class="text-lg font-extrabold text-slate-900 mb-1">Por partido del alcalde</div>
                    <div class="text-xs text-slate-500 mb-3">Partidos con 2 o más alcaldes: el candidato que más superó su promedio en esos municipios</div>
                    @forelse ($alcaldes['por_partido'] as $p)
                        @php($m = $alcaldes['candidatos'][$p['mejor']] ?? null)
                        <div class="py-2 border-b border-slate-100 last:border-0" wire:key="pp-{{ md5($p['clave']) }}">
                            <div class="text-sm font-semibold text-slate-900">{{ $p['partido'] }} <span class="text-xs font-normal text-slate-500">· {{ $p['municipios'] }} alcaldes</span></div>
                            @if ($m)
                                <div class="text-xs text-slate-600">
                                    {{ $m['candidato'] ?: $m['partido'] }}
                                    <span class="font-semibold {{ $colorPts($p['mejor_sobre']) }}">{{ $pts($p['mejor_sobre']) }} pts</span>
                                    · sobresalió en {{ $p['favorito_veces'] }} de {{ $p['municipios'] }}
                                    @if ($p['aliado'])
                                        · <span class="font-semibold text-indigo-700">mismo partido</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">Ningún partido tuvo 2 o más alcaldes (casi todos fueron por movimientos locales).</div>
                    @endforelse
                </div>
            </div>

            {{-- Por bloque del alcalde --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <div class="text-lg font-extrabold text-slate-900">Según la ideología del alcalde</div>
                <div class="text-xs text-slate-500 mb-4">
                    Promedio de puntos sobre su propio promedio de cada candidato, en los municipios con alcalde de cada bloque
                    (según la tabla de ideologías de la sección 3).
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="py-2 pr-4">Alcalde de</th>
                                @foreach ($top as $c)
                                    <th class="py-2 px-3 text-right max-w-[10rem]">{{ $c['candidato'] ?: $c['partido'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach (['izquierda' => 'Izquierda', 'centro' => 'Centro', 'derecha' => 'Derecha', 'sin_clasificar' => 'Sin clasificar'] as $b => $nombre)
                                @php($x = $alcaldes['por_bloque'][$b])
                                @continue(! $x['municipios'])
                                <tr>
                                    <td class="py-2 pr-4 font-semibold text-slate-900">{{ $nombre }} <span class="text-xs font-normal text-slate-500">({{ $x['municipios'] }})</span></td>
                                    @foreach ($top as $id => $c)
                                        <td class="py-2 px-3 text-right tabular-nums font-semibold {{ $colorPts($x['sobre'][$id]) }}">{{ $pts($x['sobre'][$id]) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Municipio por municipio --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <div class="text-lg font-extrabold text-slate-900">Municipio por municipio</div>
                <div class="text-xs text-slate-500 mb-4">Ordenado por tamaño del municipio. Los nombres son resultados públicos de la Registraduría.</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="py-2 pr-4">Municipio</th>
                                <th class="py-2 px-3">Alcalde en funciones (elegido en {{ $alcaldes['anio_alcaldia'] }})</th>
                                <th class="py-2 px-3">Ganó la gobernación ahí</th>
                                <th class="py-2 px-3">Más sobre su promedio</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach (collect($alcaldes['municipios'])->sortByDesc('potencial') as $r)
                                @php($ga = $r['ganador'] ? $alcaldes['candidatos'][$r['ganador']] : null)
                                @php($fa = $r['favorito'] ? $alcaldes['candidatos'][$r['favorito']] : null)
                                <tr wire:key="am-{{ $r['id'] }}">
                                    <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                                    <td class="py-2 px-3">
                                        @if ($r['alcalde'])
                                            <div class="text-slate-900">{{ $r['alcalde']['candidato'] }}</div>
                                            <div class="text-xs text-slate-500">
                                                {{ $r['alcalde']['partido'] }} · {{ $r['alcalde']['porcentaje'] }}%
                                                @if ($r['alcalde']['ideologia'])
                                                    · <span style="color: {{ $ideologiasDef[$r['alcalde']['ideologia']][2] }}">{{ $ideologiasDef[$r['alcalde']['ideologia']][0] }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">
                                        @if ($ga)
                                            <div class="text-slate-900">{{ $ga['candidato'] ?: $ga['partido'] }}</div>
                                            <div class="text-xs text-slate-500">{{ $r['candidatos'][$r['ganador']]['porcentaje'] }}%
                                                @if ($r['candidatos'][$r['ganador']]['aliado'])
                                                    · <span class="font-semibold text-indigo-700">mismo partido del alcalde</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">
                                        @if ($fa)
                                            @php($fx = $r['candidatos'][$r['favorito']])
                                            <div class="text-slate-900">{{ $fa['candidato'] ?: $fa['partido'] }}</div>
                                            <div class="text-xs">
                                                <span class="font-semibold {{ $colorPts($fx['sobre']) }}">{{ $pts($fx['sobre']) }} pts</span>
                                                <span class="text-slate-500">({{ $fx['porcentaje'] }}% aquí, {{ $fa['porcentaje'] }}% en el depto)</span>
                                                @if ($fx['aliado'])
                                                    · <span class="font-semibold text-indigo-700">mismo partido del alcalde</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Alcaldes actuales --}}
        @if ($actuales && $actuales['municipios'])
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <div class="text-lg font-extrabold text-slate-900">Alcaldes en funciones para {{ $actuales['anio'] + 4 }}</div>
                <div class="text-xs text-slate-500 mb-4">
                    Elegidos en {{ $actuales['anio'] }}: son los que estarán en el cargo durante la próxima elección a gobernación.
                    Ordenados por tamaño del municipio.
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="py-2 pr-4">Municipio</th>
                                <th class="py-2 px-3 text-right">Potencial</th>
                                <th class="py-2 px-3">Alcalde</th>
                                <th class="py-2 px-3">Partido o movimiento</th>
                                <th class="py-2 px-3">Ideología</th>
                                <th class="py-2 px-3 text-right">Votos</th>
                                <th class="py-2 px-3 text-right">Margen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($actuales['municipios'] as $r)
                                <tr wire:key="aa-{{ $r['id'] }}">
                                    <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['potencial']) }}</td>
                                    <td class="py-2 px-3 text-slate-900">{{ $r['candidato'] }}</td>
                                    <td class="py-2 px-3 text-slate-600">{{ $r['partido'] }}</td>
                                    <td class="py-2 px-3">
                                        @if ($r['ideologia'])
                                            <span class="text-xs font-semibold" style="color: {{ $ideologiasDef[$r['ideologia']][2] }}">{{ $ideologiasDef[$r['ideologia']][0] }}</span>
                                        @else
                                            <span class="text-xs text-slate-400">Sin clasificar</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $r['porcentaje'] }}%</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $r['margen'] }} pts</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif

    {{-- ===== 5–8. Plan de campaña ===== --}}
    @include('livewire.dashboard.analisis.plan')
</div>

@push('scripts')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function () {
            let mapa = null, capa = null, geoActual = null, ultimo = null;
            const geoCache = {};
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

            async function pintar(datos) {
                const el = document.getElementById('analisis-mapa');
                const firma = JSON.stringify(datos);
                if (!el || !datos || typeof L === 'undefined' || firma === ultimo) return;
                ultimo = firma;

                if (!mapa || !document.body.contains(mapa.getContainer())) {
                    mapa = L.map(el, { scrollWheelZoom: false }).setView([4.6, -74.1], 6);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 12, attribution: '&copy; OpenStreetMap' }).addTo(mapa);
                    geoActual = null;
                }

                geoCache[datos.geo] ??= fetch(datos.geo).then((r) => r.json());
                const geo = await geoCache[datos.geo];
                if (firma !== ultimo) return;

                if (capa) capa.remove();
                const muni = (f) => datos.municipios[String(f.properties.COD_MPIO || '').padStart(5, '0')];

                capa = L.geoJSON(geo, {
                    style: (f) => {
                        const m = muni(f);
                        return { fillColor: m ? m.color : '#e2e8f0', fillOpacity: m ? m.opacidad : 0.3, color: '#ffffff', weight: 1 };
                    },
                    onEachFeature: (f, layer) => {
                        const m = muni(f);
                        const lineas = m ? m.lineas.filter(Boolean) : [f.properties.NOM_MPIO, 'Sin datos'];
                        layer.bindTooltip(lineas.map((l, i) => i === 0 ? `<b>${esc(l)}</b>` : esc(l)).join('<br>'), { sticky: true });
                    },
                }).addTo(mapa);

                if (geoActual !== datos.geo) {
                    mapa.fitBounds(capa.getBounds(), { padding: [10, 10] });
                    geoActual = datos.geo;
                }
                setTimeout(() => mapa.invalidateSize(), 50);
            }

            const registrar = () => Livewire.on('analisis-mapa', ({ mapa }) => pintar(mapa));
            window.Livewire ? registrar() : document.addEventListener('livewire:init', registrar);
            document.addEventListener('DOMContentLoaded', () => pintar(@json($mapa)));
        })();
    </script>
@endpush
