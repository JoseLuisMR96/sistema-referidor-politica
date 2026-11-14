@php
    use App\Models\EventoCampana;

    $pesos = fn ($v) => '$ '.number_format((float) $v, 0, ',', '.');
    $num = fn ($v) => number_format((int) $v, 0, ',', '.');
    $tarjeta = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $filtro = 'mt-1 w-full rounded-xl border-slate-200 text-sm';
@endphp

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Eventos de campaña</h2>
            <div class="text-sm text-gray-500">
                {{ $tenant ? 'Dónde ha estado '.$tenant->nombre.' o su equipo · '.$tenant->circunscripcionTexto() : 'Trazabilidad de la campaña en territorio' }}
            </div>
        </div>
        @if ($tenant)
            <div class="flex items-center gap-2">
                <a href="{{ route('equipo.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">Equipo de campaña</a>
                @can('eventos.crear')
                    <a href="{{ route('eventos.create') }}"
                        class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        ➕ Nuevo evento
                    </a>
                @endcan
            </div>
        @endif
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">{{ session('success') }}</div>
        @endif

        @if (! $tenant)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Elige un candidato en el menú lateral para ver y registrar sus eventos de campaña.
            </div>
        @else
            {{-- ===== Totales ===== --}}
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="{{ $tarjeta }} p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">Eventos</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($totales->eventos) }}</div>
                    <div class="text-xs text-slate-500">{{ $num($totales->asistentes) }} asistentes aprox.</div>
                </div>
                <div class="{{ $tarjeta }} p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">Municipios visitados</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">
                        {{ $num($totales->municipios) }}@if ($totalMunicipios)<span class="text-base font-semibold text-slate-400"> de {{ $totalMunicipios }}</span>@endif
                    </div>
                    @if ($totalMunicipios)
                        <div class="mt-1 h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-indigo-600" style="width: {{ min(100, round($totales->municipios * 100 / $totalMunicipios)) }}%"></div></div>
                    @endif
                </div>
                <div class="{{ $tarjeta }} p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">Con el candidato</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($totales->con_candidato) }}</div>
                    <div class="text-xs text-slate-500">{{ $num($totales->eventos - $totales->con_candidato) }} los hizo el equipo</div>
                </div>
                <div class="{{ $tarjeta }} p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">Asignado</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $pesos($totales->asignado) }}</div>
                </div>
                <div class="{{ $tarjeta }} p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">Gastado (legalizado)</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $pesos($totales->gastado) }}</div>
                </div>
            </div>

            {{-- ===== Filtros ===== --}}
            <div class="{{ $tarjeta }} p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <label class="text-[11px] font-semibold text-slate-500 sm:col-span-2">Buscar
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Qué se hizo, sitio o dirección" class="{{ $filtro }}">
                </label>
                <label class="text-[11px] font-semibold text-slate-500">Municipio
                    <select wire:model.live="municipioId" class="{{ $filtro }}">
                        <option value="">Todos</option>
                        @foreach ($opcionesMunicipio as $m)
                            <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-[11px] font-semibold text-slate-500">Quién fue
                    <select wire:model.live="quien" class="{{ $filtro }}">
                        <option value="">Todos</option>
                        <option value="candidato">El candidato</option>
                        <option value="equipo">El equipo (emisarios)</option>
                    </select>
                </label>
                <label class="text-[11px] font-semibold text-slate-500">Persona del equipo
                    <select wire:model.live="persona" class="{{ $filtro }}">
                        <option value="">Todas</option>
                        @foreach ($equipo as $i)
                            <option value="{{ $i->id }}">{{ $i->nombre }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-[11px] font-semibold text-slate-500">Tipo
                    <select wire:model.live="tipo" class="{{ $filtro }}">
                        <option value="">Todos</option>
                        @foreach (EventoCampana::TIPOS as $valor => $texto)
                            <option value="{{ $valor }}">{{ $texto }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-[11px] font-semibold text-slate-500">Estado
                    <select wire:model.live="estado" class="{{ $filtro }}">
                        <option value="">Todos</option>
                        @foreach (EventoCampana::ESTADOS as $valor => [$texto])
                            <option value="{{ $valor }}">{{ $texto }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-[11px] font-semibold text-slate-500">Desde
                        <input type="date" wire:model.live="desde" class="{{ $filtro }}">
                    </label>
                    <label class="text-[11px] font-semibold text-slate-500">Hasta
                        <input type="date" wire:model.live="hasta" class="{{ $filtro }}">
                    </label>
                </div>
                <div class="flex items-end">
                    <button type="button" wire:click="limpiar" class="rounded-xl border px-3 py-2 text-sm font-semibold hover:bg-slate-50">Limpiar filtros</button>
                </div>
            </div>

            {{-- ===== Mapa y resúmenes ===== --}}
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                @if ($mapa)
                    <div class="lg:col-span-3 {{ $tarjeta }} p-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                            <div class="text-lg font-extrabold text-slate-900">Dónde se ha estado</div>
                            <div class="flex flex-wrap gap-3 text-xs text-slate-500">
                                <span>Más intenso = más eventos</span>
                                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full" style="background:#4f46e5"></span>Con el candidato</span>
                                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full" style="background:#f59e0b"></span>Equipo</span>
                            </div>
                        </div>
                        <div id="eventos-mapa" class="h-[420px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
                    </div>
                @endif

                <div class="{{ $mapa ? 'lg:col-span-2' : 'lg:col-span-5' }} {{ $tarjeta }} p-4">
                    <div class="text-lg font-extrabold text-slate-900 mb-1">Por municipio</div>
                    <div class="text-xs text-slate-500 mb-3">Sin contar los cancelados</div>
                    <div class="max-h-[420px] overflow-y-auto divide-y">
                        @forelse ($porMunicipio as $r)
                            <button type="button" wire:click="$set('municipioId', {{ $r->municipio_id ?? 'null' }})" wire:key="pm-{{ $r->municipio_id }}"
                                class="flex w-full items-center justify-between gap-3 py-2 text-left hover:bg-slate-50">
                                <span class="min-w-0">
                                    <span class="block font-semibold text-slate-900 truncate">{{ $municipios[$r->municipio_id]->nombre ?? 'Sin municipio' }}</span>
                                    <span class="block text-xs text-slate-500">
                                        Último: {{ \Illuminate\Support\Carbon::parse($r->ultima)->format('d/m/Y') }}
                                        · {{ $r->con_candidato ? "candidato {$r->con_candidato}" : 'solo el equipo' }}
                                    </span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <span class="block font-bold tabular-nums text-slate-900">{{ $r->eventos }}</span>
                                    <span class="block text-xs tabular-nums text-slate-500">{{ $pesos($r->asignado) }}</span>
                                </span>
                            </button>
                        @empty
                            <div class="py-6 text-center text-sm text-slate-500">Todavía no hay eventos con este filtro.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ===== Por persona ===== --}}
            @if ($porPersona->isNotEmpty() || $delCandidato)
                <div class="{{ $tarjeta }} p-4">
                    <div class="text-lg font-extrabold text-slate-900">Dinero por persona</div>
                    <div class="text-xs text-slate-500 mb-3">A quién se le ha asignado dinero como responsable de eventos (sin cancelados)</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                                <tr>
                                    <th class="py-2 pr-4">Persona</th>
                                    <th class="py-2 px-3 text-right">Eventos</th>
                                    <th class="py-2 px-3 text-right">Asignado</th>
                                    <th class="py-2 px-3 text-right">Gastado</th>
                                    <th class="py-2 px-3 text-right">Por legalizar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @if ($delCandidato)
                                    <tr wire:key="pp-candidato">
                                        <td class="py-2 pr-4">
                                            <span class="font-semibold text-slate-900">El candidato</span>
                                            <span class="text-xs text-slate-400">fue en persona, sin responsable del equipo</span>
                                        </td>
                                        <td class="py-2 px-3 text-right tabular-nums">{{ $delCandidato->eventos }}</td>
                                        <td class="py-2 px-3 text-right tabular-nums font-bold">{{ $pesos($delCandidato->asignado) }}</td>
                                        <td class="py-2 px-3 text-right tabular-nums">{{ $pesos($delCandidato->gastado) }}</td>
                                        <td class="py-2 px-3 text-right tabular-nums {{ $delCandidato->asignado - $delCandidato->gastado > 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ $pesos(max(0, $delCandidato->asignado - $delCandidato->gastado)) }}</td>
                                    </tr>
                                @endif
                                @foreach ($porPersona as $p)
                                    @php($persona = $equipo[$p->responsable_id] ?? null)
                                    <tr wire:key="pp-{{ $p->responsable_id }}">
                                        <td class="py-2 pr-4">
                                            <button type="button" wire:click="$set('persona', {{ $p->responsable_id }})" class="font-semibold text-slate-900 hover:underline">{{ $persona?->nombre ?? '—' }}</button>
                                            <span class="text-xs text-slate-400">{{ $persona?->rolTexto() }}</span>
                                        </td>
                                        <td class="py-2 px-3 text-right tabular-nums">{{ $p->eventos }}</td>
                                        <td class="py-2 px-3 text-right tabular-nums font-bold">{{ $pesos($p->asignado) }}</td>
                                        <td class="py-2 px-3 text-right tabular-nums">{{ $pesos($p->gastado) }}</td>
                                        <td class="py-2 px-3 text-right tabular-nums {{ $p->asignado - $p->gastado > 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ $pesos(max(0, $p->asignado - $p->gastado)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ===== Listado ===== --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="py-3 px-4">Fecha</th>
                            <th class="py-3 px-4">Evento</th>
                            <th class="py-3 px-4">Lugar</th>
                            <th class="py-3 px-4">Quién fue</th>
                            <th class="py-3 px-4 text-right">Asignado</th>
                            <th class="py-3 px-4 text-right">Evidencias</th>
                            <th class="py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($eventos as $e)
                            @php($est = EventoCampana::ESTADOS[$e->estado] ?? [$e->estado, 'bg-gray-100 text-gray-700'])
                            <tr wire:key="evento-{{ $e->id }}" class="{{ $e->estado === 'cancelado' ? 'opacity-60' : '' }}">
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="font-semibold text-gray-900">{{ $e->fecha->format('d/m/Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $e->fecha->format('h:i a') }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="{{ route('eventos.show', $e) }}" class="font-semibold text-gray-900 hover:underline">{{ $e->titulo }}</a>
                                    <div class="mt-0.5 flex flex-wrap gap-1 text-xs">
                                        <span class="text-gray-500">{{ $e->tipoTexto() }}</span>
                                        <span class="rounded-full px-2 py-0.5 font-semibold {{ $est[1] }}">{{ $est[0] }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold">{{ $e->municipio?->nombre }}</div>
                                    <div class="text-xs text-gray-500">{{ $e->lugar ?: $e->direccion }}</div>
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    @if ($e->asistio_candidato)
                                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 font-semibold text-indigo-800">Candidato</span>
                                    @endif
                                    @if ($e->responsable)
                                        <div class="mt-0.5 text-gray-700">{{ $e->responsable->nombre }} <span class="text-gray-400">({{ $e->responsable->rolTexto() }})</span></div>
                                    @elseif (! $e->asistio_candidato)
                                        <span class="text-gray-400">Equipo</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums whitespace-nowrap">{{ $e->monto_asignado > 0 ? $pesos($e->monto_asignado) : '—' }}</td>
                                <td class="py-3 px-4 text-right tabular-nums">{{ $e->evidencias_count ?: '—' }}</td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('eventos.show', $e) }}" class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-8 px-4 text-center text-gray-500">No hay eventos con este filtro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $eventos->links() }}</div>
        @endif
    </div>
</div>

@if ($tenant)
    @push('scripts')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (function () {
                let mapa = null, capa = null, puntos = null, geoActual = null, ultimo = null;
                const geoCache = {};
                const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

                async function pintar(datos) {
                    const el = document.getElementById('eventos-mapa');
                    const firma = JSON.stringify(datos);
                    if (!el || !datos || typeof L === 'undefined' || firma === ultimo) return;
                    ultimo = firma;

                    if (!mapa || !document.body.contains(mapa.getContainer())) {
                        mapa = L.map(el, { scrollWheelZoom: false }).setView([4.6, -74.1], 6);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 17, attribution: '&copy; OpenStreetMap' }).addTo(mapa);
                        geoActual = null;
                    }

                    geoCache[datos.geo] ??= fetch(datos.geo).then((r) => r.json());
                    const geo = await geoCache[datos.geo];
                    if (firma !== ultimo) return;

                    if (capa) capa.remove();
                    if (puntos) puntos.remove();
                    const muni = (f) => datos.municipios[String(f.properties.COD_MPIO || '').padStart(5, '0')];

                    capa = L.geoJSON(geo, {
                        style: (f) => {
                            const m = muni(f);
                            return { fillColor: '#4f46e5', fillOpacity: m ? m.opacidad : 0.04, color: '#ffffff', weight: 1 };
                        },
                        onEachFeature: (f, layer) => {
                            const m = muni(f);
                            const lineas = m ? m.lineas : [f.properties.NOM_MPIO, 'Sin eventos'];
                            layer.bindTooltip(lineas.map((l, i) => i === 0 ? `<b>${esc(l)}</b>` : esc(l)).join('<br>'), { sticky: true });
                        },
                    }).addTo(mapa);

                    puntos = L.layerGroup(datos.puntos.map((p) => L.circleMarker([p.lat, p.lng], {
                        radius: 6, weight: 2, color: '#ffffff', fillOpacity: 0.95, fillColor: p.candidato ? '#4f46e5' : '#f59e0b',
                    }).bindPopup(`<a href="${esc(p.url)}" style="font-weight:600">${esc(p.texto)}</a>`))).addTo(mapa);

                    if (geoActual !== datos.geo) {
                        mapa.fitBounds(capa.getBounds(), { padding: [10, 10] });
                        geoActual = datos.geo;
                    }
                    setTimeout(() => mapa.invalidateSize(), 50);
                }

                const registrar = () => Livewire.on('eventos-mapa', ({ mapa }) => pintar(mapa));
                window.Livewire ? registrar() : document.addEventListener('livewire:init', registrar);
                document.addEventListener('DOMContentLoaded', () => pintar(@json($mapa)));
            })();
        </script>
    @endpush
@endif
