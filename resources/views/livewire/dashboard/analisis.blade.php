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

        <label class="text-xs font-semibold text-slate-700">Cargo
            <select wire:model.live="cargo" class="{{ $select }}">
                @foreach ($cargos as $c)
                    <option value="{{ $c->value }}">{{ $c->label() }}</option>
                @endforeach
            </select>
        </label>

        @if ($seccion === 'fortines')
            <label class="text-xs font-semibold text-slate-700 lg:col-span-2">Partido o coalición
                <select wire:model.live="partido" class="{{ $select }}">
                    @foreach ($partidos as $clave => $p)
                        <option value="{{ $clave }}">
                            {{ $p['nombre'] }}{{ $clave === $partidoCandidato ? ' · tu partido' : '' }} — {{ $num($p['votos']) }} votos
                        </option>
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

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach ($fortines['anios'] as $a)
                @php($d = $fortines['departamento'][$a])
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">{{ $a }} · todo {{ $departamento->nombre }}</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $d['porcentaje'] ?? '—' }}%</div>
                    <div class="text-sm text-slate-600">{{ $num($d['votos']) }} votos · ganó en {{ $d['ganados'] }} municipio(s)</div>
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
                    @foreach (collect($fortines['municipios'])->sortByDesc(fn ($r) => $r['por_anio'][$u]['porcentaje'] ?? -1)->take(8) as $r)
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
                                        @if ($x['hubo'])
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
