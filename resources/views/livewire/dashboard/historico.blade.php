@php
    use App\Enums\CargoEnum;
    use App\Services\Historico\HistoricoElectoralStats;

    $esTuPartido = fn (?string $partido) => $partidoCandidato && $partido
        && str_contains(HistoricoElectoralStats::normalizar($partido), $partidoCandidato);
    $esUninominal = $cargoEnum && ! HistoricoElectoralStats::esCorporacion($cargoEnum->value);
    $lugarContienda = $municipio?->nombre ?? $departamento?->nombre ?? 'Colombia';
    $select = 'mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm bg-white';
@endphp

<x-slot name="header">
    <div>
        <h2 class="font-extrabold text-xl text-slate-900 leading-tight">Histórico electoral</h2>
        <div class="text-sm text-slate-500">Quién ganó en cada departamento y municipio, por año y cargo</div>
    </div>
</x-slot>

<div class="space-y-6">
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
        Resultados oficiales públicos de la Registraduría. Las elecciones territoriales (gobernación, asamblea,
        alcaldía, concejo y JAL) son cada 4 años: 2011, 2015, 2019, 2023… En asamblea, concejo y JAL se vota por
        listas, así que el "ganador" de un municipio es el partido más votado.
    </div>

    @if (empty($anios))
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center">
            <div class="text-lg font-extrabold text-slate-900">Todavía no hay resultados cargados</div>
            <div class="mt-1 text-sm text-slate-500">
                @if ($this->puedeImportar())
                    Importa el primer archivo abajo, en "Importar resultados".
                @else
                    Pídele a un administrador que importe los resultados de la Registraduría.
                @endif
            </div>
        </div>
    @else
        {{-- ===== Filtros ===== --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 grid grid-cols-2 lg:grid-cols-5 gap-3">
            <label class="text-xs font-semibold text-slate-700">Año
                <select wire:model.live="anio" class="{{ $select }}">
                    @foreach ($anios as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
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

            <label class="text-xs font-semibold text-slate-700">Departamento
                <select wire:model.live="departamentoId" class="{{ $select }}">
                    <option value="">Todo el país</option>
                    @foreach ($departamentos as $d)
                        <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-xs font-semibold text-slate-700">Municipio
                <select wire:model.live="municipioId" class="{{ $select }}" @disabled(! $departamento)>
                    <option value="">{{ $departamento ? 'Todos' : 'Elige un departamento' }}</option>
                    @foreach ($municipios as $m)
                        <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                    @endforeach
                </select>
            </label>

            @if (count($vueltas) > 1)
                <label class="text-xs font-semibold text-slate-700">Vuelta
                    <select wire:model.live="vuelta" class="{{ $select }}">
                        @foreach ($vueltas as $v)
                            <option value="{{ $v }}">{{ $v === 1 ? 'Primera vuelta' : 'Segunda vuelta' }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if ($comunas)
                <label class="text-xs font-semibold text-slate-700">Comuna
                    <select wire:model.live="comuna" class="{{ $select }}">
                        <option value="">Todas</option>
                        @foreach ($comunas as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>

        <div wire:loading.delay class="text-xs text-slate-500">Cargando…</div>

        {{-- ===== La contienda (departamento, municipio o todo el país) ===== --}}
        @if ($contienda || $departamento)
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                @if ($contienda)
                    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                        <div class="text-lg font-extrabold text-slate-900">
                            {{ $cargoEnum->label() }} · {{ $lugarContienda }}{{ $comuna ? " · {$comuna}" : '' }} · {{ $anio }}
                        </div>
                        @if ($municipio && $cargoEnum->ambito() !== CargoEnum::AMBITO_MUNICIPAL)
                            <div class="text-xs text-slate-500">
                                Votos en {{ $municipio->nombre }}.
                                <button type="button" wire:click="$set('municipioId', null)" class="underline font-semibold text-indigo-700">Ver todo el departamento</button>
                            </div>
                        @endif
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                                <tr>
                                    <th class="py-2 pr-3">#</th>
                                    <th class="py-2 pr-4">{{ $esUninominal ? 'Candidato' : 'Partido o lista' }}</th>
                                    <th class="py-2 pr-4">{{ $esUninominal ? 'Partido' : 'Elegidos' }}</th>
                                    <th class="py-2 px-3 text-right">Votos</th>
                                    <th class="py-2 px-3 w-48">%</th>
                                    @unless ($esUninominal)
                                        <th class="py-2 px-3 text-right">{{ $cargoEnum === CargoEnum::SENADO ? 'Curules (país)' : 'Curules' }}</th>
                                    @endunless
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($contienda as $c)
                                    <tr class="{{ $c['posicion'] === 1 ? 'bg-emerald-50/50' : '' }}">
                                        <td class="py-2 pr-3 text-slate-400 tabular-nums">{{ $c['posicion'] }}</td>
                                        @if ($esUninominal)
                                            <td class="py-2 pr-4 font-semibold text-slate-900">
                                                {{ $c['candidato'] ?? $c['partido'] }}
                                                @if ($c['elegido'])
                                                    <span class="ml-1 rounded bg-emerald-100 px-1.5 text-[10px] font-bold text-emerald-700">Elegido</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="py-2 pr-4 {{ $esUninominal ? 'text-slate-700' : 'font-semibold text-slate-900' }}">
                                            <span class="inline-block h-2.5 w-2.5 rounded-sm mr-1" style="background: {{ $c['color'] }}"></span>
                                            {{ $c['partido'] }}
                                            @if ($esTuPartido($c['partido']))
                                                <span class="ml-1 rounded bg-indigo-100 px-1.5 text-[10px] font-bold text-indigo-700">Tu partido</span>
                                            @endif
                                        </td>
                                        @unless ($esUninominal)
                                            <td class="py-2 pr-4 text-xs text-slate-600">
                                                {{ implode(', ', $c['elegidos'] ?? []) ?: '—' }}
                                            </td>
                                        @endunless
                                        <td class="py-2 px-3 text-right font-bold tabular-nums">{{ number_format($c['votos'], 0, ',', '.') }}</td>
                                        <td class="py-2 px-3">
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 flex-1 rounded bg-slate-100">
                                                    <div class="h-2 rounded" style="width: {{ $c['porcentaje'] }}%; background: {{ $c['color'] }}"></div>
                                                </div>
                                                <span class="w-12 text-right tabular-nums text-slate-600">{{ $c['porcentaje'] }}%</span>
                                            </div>
                                        </td>
                                        @unless ($esUninominal)
                                            <td class="py-2 px-3 text-right tabular-nums">{{ $c['curules'] ?? '—' }}</td>
                                        @endunless
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif ($cargoEnum?->ambito() === CargoEnum::AMBITO_MUNICIPAL)
                    <div class="text-sm text-slate-500">
                        Elige un municipio en el mapa o en el filtro para ver quién ganó y sus contrincantes.
                    </div>
                @else
                    <div class="text-sm text-slate-500">Sin resultados para esta combinación.</div>
                @endif
            </div>
        @endif

        @if ($departamento)
            {{-- ===== Mapa + partidos ===== --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-lg font-extrabold text-slate-900">
                            {{ $departamento->nombre }} · {{ $cargoEnum?->label() }} {{ $anio }}
                        </div>
                        <div class="text-xs text-slate-500">Color = partido ganador. Clic en un municipio para ver su detalle.</div>
                    </div>
                    <div id="historico-mapa" class="h-[460px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
                    @if (empty($ganadores))
                        <div class="mt-2 text-xs text-amber-700">
                            No hay resultados por municipio de este cargo y año en {{ $departamento->nombre }}.
                        </div>
                    @endif
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    <div class="text-lg font-extrabold text-slate-900 mb-1">Partidos ganadores</div>
                    <div class="text-xs text-slate-500 mb-3">Municipios donde cada partido fue el más votado</div>
                    <div class="space-y-2">
                        @forelse ($partidos as $p)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="h-3 w-3 rounded-sm shrink-0" style="background: {{ $p['color'] }}"></span>
                                <span class="flex-1 text-slate-800">
                                    {{ $p['partido'] }}
                                    @if ($esTuPartido($p['partido']))
                                        <span class="ml-1 rounded bg-indigo-100 px-1.5 text-[10px] font-bold text-indigo-700">Tu partido</span>
                                    @endif
                                </span>
                                <span class="font-bold tabular-nums">{{ $p['municipios'] }}</span>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">Sin datos.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ===== Municipio por municipio ===== --}}
            @if ($ganadores)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <div class="text-lg font-extrabold text-slate-900">Municipio por municipio</div>
                    <div class="text-xs text-slate-500 mb-4">
                        Margen = ventaja del primero sobre el segundo, en puntos.
                        @if ($registros !== null)
                            "Tus registros" = personas de la campaña que votan en ese municipio.
                        @endif
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                                <tr>
                                    <th class="py-2 pr-4">Municipio</th>
                                    <th class="py-2 pr-4">Ganador</th>
                                    <th class="py-2 px-3 text-right">Votos</th>
                                    <th class="py-2 px-3 text-right">%</th>
                                    <th class="py-2 pr-4">Segundo</th>
                                    <th class="py-2 px-3 text-right">Margen</th>
                                    <th class="py-2 px-3 text-right">Total votos</th>
                                    @if ($registros !== null)
                                        <th class="py-2 px-3 text-right">Tus registros</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($ganadores as $g)
                                    <tr wire:key="g-{{ $g['municipio_id'] }}" wire:click="verMunicipio({{ $g['municipio_id'] }})"
                                        class="cursor-pointer hover:bg-slate-50 {{ $municipio?->id === $g['municipio_id'] ? 'bg-indigo-50' : '' }}">
                                        <td class="py-2 pr-4 font-semibold text-slate-900">{{ $g['municipio'] }}</td>
                                        <td class="py-2 pr-4">
                                            <div class="flex items-center gap-1.5">
                                                <span class="h-2.5 w-2.5 rounded-sm shrink-0" style="background: {{ $g['color'] }}"></span>
                                                <span class="text-slate-900">{{ $g['candidato'] ?? $g['partido'] }}</span>
                                            </div>
                                            @if ($g['candidato'])
                                                <div class="text-xs text-slate-500 pl-4">{{ $g['partido'] }}</div>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 text-right font-bold tabular-nums">{{ number_format($g['votos'], 0, ',', '.') }}</td>
                                        <td class="py-2 px-3 text-right tabular-nums">{{ $g['porcentaje'] }}%</td>
                                        <td class="py-2 pr-4 text-slate-600">{{ $g['segundo'] ?? '—' }}</td>
                                        <td class="py-2 px-3 text-right tabular-nums {{ ($g['margen'] ?? 100) < 5 ? 'text-amber-700 font-semibold' : '' }}">
                                            {{ $g['margen'] !== null ? $g['margen'].' pts' : '—' }}
                                        </td>
                                        <td class="py-2 px-3 text-right tabular-nums text-slate-600">{{ number_format($g['total'], 0, ',', '.') }}</td>
                                        @if ($registros !== null)
                                            <td class="py-2 px-3 text-right tabular-nums font-semibold">{{ number_format($registros[$g['municipio_id']] ?? 0, 0, ',', '.') }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 text-xs text-slate-500">En ámbar, municipios con margen menor a 5 puntos: plazas disputadas.</div>
                </div>
            @endif
        @else
            {{-- ===== Todo el país ===== --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    @if ($porDepartamento)
                        <div class="text-lg font-extrabold text-slate-900 mb-4">{{ $cargoEnum->label() }} {{ $anio }} por departamento</div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                                    <tr>
                                        <th class="py-2 pr-4">Departamento</th>
                                        <th class="py-2 pr-4">Ganador</th>
                                        <th class="py-2 px-3 text-right">Votos</th>
                                        <th class="py-2 px-3 text-right">%</th>
                                        <th class="py-2 pr-4">Segundo</th>
                                        <th class="py-2 px-3 text-right">Margen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach ($porDepartamento as $d)
                                        <tr wire:key="d-{{ $d['departamento_id'] }}" wire:click="$set('departamentoId', {{ $d['departamento_id'] }})" class="cursor-pointer hover:bg-slate-50">
                                            <td class="py-2 pr-4 font-semibold text-slate-900">{{ $d['departamento'] }}</td>
                                            <td class="py-2 pr-4">
                                                <span class="inline-block h-2.5 w-2.5 rounded-sm mr-1" style="background: {{ $d['color'] }}"></span>
                                                {{ $d['candidato'] ?? $d['partido'] }}
                                                @if ($d['candidato'])
                                                    <div class="text-xs text-slate-500 pl-4">{{ $d['partido'] }}</div>
                                                @endif
                                            </td>
                                            <td class="py-2 px-3 text-right font-bold tabular-nums">{{ number_format($d['votos'], 0, ',', '.') }}</td>
                                            <td class="py-2 px-3 text-right tabular-nums">{{ $d['porcentaje'] }}%</td>
                                            <td class="py-2 pr-4 text-slate-600">{{ $d['segundo'] ?? '—' }}</td>
                                            <td class="py-2 px-3 text-right tabular-nums">{{ $d['margen'] !== null ? $d['margen'].' pts' : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-lg font-extrabold text-slate-900">{{ $cargoEnum?->label() }} {{ $anio }} en todo el país</div>
                        <div class="mt-1 text-sm text-slate-500">
                            {{ count($ganadores) }} municipios con resultados. Elige un departamento para ver el mapa y el detalle.
                        </div>
                    @endif
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    <div class="text-lg font-extrabold text-slate-900 mb-1">Partidos ganadores</div>
                    <div class="text-xs text-slate-500 mb-3">{{ $porDepartamento ? 'Departamentos' : 'Municipios' }} ganados por cada partido</div>
                    <div class="space-y-2">
                        @forelse ($partidos as $p)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="h-3 w-3 rounded-sm shrink-0" style="background: {{ $p['color'] }}"></span>
                                <span class="flex-1 text-slate-800">{{ $p['partido'] }}</span>
                                <span class="font-bold tabular-nums">{{ $p['municipios'] }}</span>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">Sin datos.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- ===== Importar (solo usuarios globales) ===== --}}
    @if ($this->puedeImportar())
        <details class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6" @if (empty($anios) || $resumenImportacion) open @endif>
            <summary class="cursor-pointer text-lg font-extrabold text-slate-900">Importar resultados</summary>

            <div class="mt-3 text-sm text-slate-600 space-y-1">
                <p>
                    Sube un CSV o Excel con una fila por candidato (o lista) y lugar. Columnas:
                    <code class="text-xs">anio, cargo, departamento, municipio, comuna, partido, candidato, votos, elegido, curules</code>.
                    <a href="{{ asset('plantillas/historico-electoral.csv') }}" class="underline font-semibold text-indigo-700" download>Descargar plantilla</a>
                </p>
                <p class="text-xs text-slate-500">
                    Gobernación y asamblea: una fila por municipio (el total del departamento se suma solo).
                    Volver a subir el mismo año, cargo y lugar reemplaza lo anterior. Los votos nulos y no marcados se ignoran.
                    También acepta el "Resultados.csv" mesa a mesa de la Registraduría (suma los puestos por municipio).
                    Para archivos muy grandes: <code>php artisan historico:importar-archivo ruta --anio= --vuelta=</code>.
                </p>
            </div>

            <form wire:submit="importar" class="mt-4 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                <label class="sm:col-span-2 text-xs font-semibold text-slate-700">Archivo
                    <input type="file" wire:model="archivo" accept=".csv,.txt,.xlsx,.xls" class="mt-1 block w-full text-sm">
                    @error('archivo') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
                </label>
                <label class="text-xs font-semibold text-slate-700">Año (si el archivo no lo trae)
                    <input type="number" wire:model="anioImportar" class="{{ $select }}" placeholder="2023">
                    @error('anioImportar') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
                </label>
                <label class="text-xs font-semibold text-slate-700">Cargo (si el archivo no lo trae)
                    <select wire:model="cargoImportar" class="{{ $select }}">
                        <option value="">—</option>
                        @foreach ($todosLosCargos as $c)
                            <option value="{{ $c->value }}">{{ $c->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-700">Vuelta (presidencia)
                    <select wire:model="vueltaImportar" class="{{ $select }}">
                        <option value="">Primera / única</option>
                        <option value="2">Segunda vuelta</option>
                    </select>
                </label>
                <div class="sm:col-span-5">
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                        wire:loading.attr="disabled" wire:target="importar,archivo">
                        <span wire:loading.remove wire:target="importar">Importar</span>
                        <span wire:loading wire:target="importar">Importando…</span>
                    </button>
                </div>
            </form>

            @if ($resumenImportacion)
                <div class="mt-4 rounded-xl border px-4 py-3 text-sm {{ $resumenImportacion['insertadas'] ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
                    <div class="font-semibold">
                        {{ number_format($resumenImportacion['insertadas'], 0, ',', '.') }} filas importadas
                        @if ($resumenImportacion['reemplazadas'])
                            ({{ number_format($resumenImportacion['reemplazadas'], 0, ',', '.') }} anteriores reemplazadas)
                        @endif
                        @if ($resumenImportacion['omitidas'])
                            · {{ $resumenImportacion['omitidas'] }} omitidas (nulos / no marcados)
                        @endif
                    </div>
                    @foreach (array_slice($resumenImportacion['errores'], 0, 10) as $e)
                        <div class="text-xs text-rose-700">{{ $e }}</div>
                    @endforeach
                    @if ($resumenImportacion['sin_lugar'])
                        <div class="mt-1 text-xs">
                            Lugares que no se reconocieron (filas sin importar):
                            @foreach (array_slice($resumenImportacion['sin_lugar'], 0, 15, true) as $lugar => $n)
                                <span class="inline-block rounded bg-white/70 px-1.5 mr-1 mt-1">{{ $lugar }} ({{ $n }})</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </details>
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
            const num = (n) => new Intl.NumberFormat('es-CO').format(n || 0);

            async function pintar(datos) {
                const el = document.getElementById('historico-mapa');
                const firma = JSON.stringify(datos);
                if (!el || !datos || typeof L === 'undefined' || firma === ultimo) return;
                ultimo = firma;

                if (!mapa || !document.body.contains(mapa.getContainer())) {
                    mapa = L.map(el, { scrollWheelZoom: false }).setView([4.6, -74.1], 6);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 12, attribution: '&copy; OpenStreetMap'
                    }).addTo(mapa);
                    geoActual = null;
                }

                geoCache[datos.geo] ??= fetch(datos.geo).then((r) => r.json());
                const geo = await geoCache[datos.geo];
                if (firma !== ultimo) return; // llegó otra actualización mientras cargaba

                if (capa) capa.remove();
                const muni = (f) => datos.municipios[String(f.properties.COD_MPIO || '').padStart(5, '0')];
                const codigo = (f) => String(f.properties.COD_MPIO || '').padStart(5, '0');

                capa = L.geoJSON(geo, {
                    style: (f) => {
                        const m = muni(f), sel = codigo(f) === datos.seleccionado;
                        return {
                            fillColor: m ? m.color : '#e2e8f0',
                            fillOpacity: m ? 0.75 : 0.35,
                            color: sel ? '#0f172a' : '#ffffff',
                            weight: sel ? 3 : 1,
                        };
                    },
                    onEachFeature: (f, layer) => {
                        const m = muni(f);
                        const html = m
                            ? `<b>${esc(m.nombre)}</b><br>${esc(m.candidato || m.partido)}` +
                              (m.candidato ? `<br><span style="color:#64748b">${esc(m.partido)}</span>` : '') +
                              `<br>${num(m.votos)} votos (${m.porcentaje}%)` +
                              (m.registros !== null && m.registros !== undefined ? `<br>Tus registros: ${num(m.registros)}` : '')
                            : `<b>${esc(f.properties.NOM_MPIO)}</b><br>Sin datos`;
                        layer.bindTooltip(html, { sticky: true });
                        if (m) {
                            layer.on('click', () => {
                                const raiz = el.closest('[wire\\:id]');
                                if (raiz) Livewire.find(raiz.getAttribute('wire:id')).call('verMunicipio', m.id);
                            });
                        }
                    },
                }).addTo(mapa);

                if (geoActual !== datos.geo) {
                    mapa.fitBounds(capa.getBounds(), { padding: [10, 10] });
                    geoActual = datos.geo;
                }
                setTimeout(() => mapa.invalidateSize(), 50);
            }

            const registrar = () => Livewire.on('historico-mapa', ({ mapa }) => pintar(mapa));
            window.Livewire ? registrar() : document.addEventListener('livewire:init', registrar);
            document.addEventListener('DOMContentLoaded', () => pintar(@json($mapa)));
        })();
    </script>
@endpush
