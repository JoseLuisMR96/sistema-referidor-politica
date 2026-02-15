@php($header = 'Dashboard')

<x-slot name="header">
    <div class="sticky top-0 z-30 -mx-4 sm:mx-0 px-4 sm:px-6 lg:px-8 py-4 bg-white/80 backdrop-blur border-b">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <h2 class="font-extrabold text-xl text-slate-900 leading-tight truncate">Dashboard</h2>
                <div class="text-sm text-slate-500">Vista ejecutiva del flujo de registros</div>
            </div>

            @can('dashboard.exportar')
                <div class="shrink-0">
                    <a href="{{ route('dashboard.export.excel') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600/40">
                        {{-- icono opcional (excel-ish) --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <path d="M14 2v6h6" />
                            <path d="M8 13l2 2 2-2" />
                            <path d="M12 17l-2-2-2 2" />
                        </svg>
                        Exportar Excel
                    </a>
                </div>
            @endcan
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-screen-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        {{-- KPIs --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- KPI: Total --}}
            <div class="bg-white shadow-sm rounded-2xl border p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-500">Total registros</div>
                        <div class="mt-2 text-4xl font-extrabold text-slate-900">{{ $total }}</div>
                        <div class="mt-2 inline-flex items-center gap-2 text-xs text-slate-500">
                            <span class="px-2 py-1 rounded-lg bg-slate-50 border">Base acumulada</span>
                        </div>
                    </div>
                    <div
                        class="h-11 w-11 rounded-2xl bg-slate-900 text-white flex items-center justify-center shadow-sm">
                        📦
                    </div>
                </div>
            </div>

            {{-- KPI: Hoy --}}
            <div class="bg-white shadow-sm rounded-2xl border p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-500">Registros hoy</div>
                        <div class="mt-2 text-4xl font-extrabold text-slate-900">{{ $hoy }}</div>
                        <div class="mt-2 inline-flex items-center gap-2 text-xs text-slate-500">
                            <span class="px-2 py-1 rounded-lg bg-slate-50 border">Actividad del día</span>
                        </div>
                    </div>
                    <div
                        class="h-11 w-11 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-sm">
                        📅
                    </div>
                </div>
            </div>

            {{-- KPI: 7 días --}}
            <div class="bg-white shadow-sm rounded-2xl border p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-500">Últimos 7 días</div>
                        <div class="mt-2 text-4xl font-extrabold text-slate-900">{{ $ultimos7 }}</div>
                        <div class="mt-2 inline-flex items-center gap-2 text-xs text-slate-500">
                            <span class="px-2 py-1 rounded-lg bg-slate-50 border">Tendencia semanal</span>
                        </div>
                    </div>
                    <div
                        class="h-11 w-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-sm">
                        📈
                    </div>
                </div>
            </div>
        </div>

        {{-- Bloque: Top referidores + Municipios donde votan --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

            {{-- Card: Top referidores --}}
            <div class="bg-white shadow-sm rounded-2xl border overflow-hidden">
                <div class="p-5 border-b bg-slate-50">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-base font-semibold text-slate-900">Top referidores</div>
                            <div class="text-sm text-slate-500">Top 10 por volumen</div>
                        </div>
                        <div class="text-xs text-slate-500">Ranking</div>
                    </div>
                </div>

                <div class="p-5">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                        {{-- Gráfico --}}
                        <div class="h-[360px] w-full" wire:ignore>
                            <canvas id="barTopReferrers"></canvas>
                        </div>

                        {{-- Tabla (scroll, no crece infinito) --}}
                        <div class="rounded-xl border overflow-hidden">
                            <div class="max-h-[360px] overflow-y-auto">
                                <table class="min-w-full text-sm">
                                    <thead
                                        class="sticky top-0 z-10 text-xs uppercase tracking-wide text-slate-500 bg-slate-50">
                                        <tr class="border-b">
                                            <th class="py-3 px-3 text-left">Referidor</th>
                                            <th class="py-3 px-3 text-left">Código</th>
                                            <th class="py-3 px-3 text-right">Registros</th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y">
                                        @forelse($topReferrers as $r)
                                            <tr class="hover:bg-slate-50">
                                                <td class="py-3 px-3 font-semibold text-slate-900">
                                                    {{ $r['name'] }}
                                                </td>
                                                <td class="py-3 px-3 font-mono text-slate-700">
                                                    {{ $r['code'] }}
                                                </td>
                                                <td class="py-3 px-3 text-right font-semibold text-slate-900">
                                                    {{ $r['total'] }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="py-8 text-slate-500 px-3" colspan="3">Sin datos aún.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="px-3 py-2 border-t bg-white text-xs text-slate-500">
                                Mostrando Top {{ count($topReferrers) }} (la tabla tiene scroll).
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card: Municipios donde votan --}}
            <div class="bg-white shadow-sm rounded-2xl border overflow-hidden">
                <div class="p-5 border-b bg-slate-50">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-base font-semibold text-slate-900">Municipio donde votan</div>
                            <div class="text-sm text-slate-500">Top 10 + distribución</div>
                        </div>
                        <div class="text-xs text-slate-500">Gráfico Dona</div>
                    </div>
                </div>

                <div class="p-5">
                    @if (empty($municipiosVotan))
                        <div class="text-sm text-slate-500">Aún no hay datos suficientes para graficar.</div>
                    @else
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                            {{-- Gráfico --}}
                            <div class="h-[360px] w-full" wire:ignore>
                                <canvas id="donutMunicipios"></canvas>
                            </div>

                            {{-- Tabla con scroll --}}
                            <div class="rounded-xl border overflow-hidden">
                                <div class="max-h-[360px] overflow-y-auto">
                                    <table class="min-w-full text-sm">
                                        <thead
                                            class="sticky top-0 z-10 text-xs uppercase tracking-wide text-slate-500 bg-slate-50">
                                            <tr class="border-b">
                                                <th class="py-3 px-3 text-left">Departamento</th>
                                                <th class="py-3 px-3 text-left">Municipio</th>
                                                <th class="py-3 px-3 text-right">Registros</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            @foreach ($municipiosVotan as $m)
                                                <tr class="hover:bg-slate-50">
                                                    <td class="py-3 px-3 text-slate-600">
                                                        {{ $m['departamento'] ?? '—' }}
                                                    </td>
                                                    <td class="py-3 px-3 text-slate-800">{{ $m['label'] }}</td>
                                                    <td class="py-3 px-3 text-right font-semibold text-slate-900">
                                                        {{ $m['value'] }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="lg:col-span-2 text-xs text-slate-500">
                                Se muestra Top 10 para evitar un gráfico saturado.
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card: Mapa (full width) --}}
        <div class="bg-white shadow-sm rounded-2xl border overflow-hidden">
            <div class="p-5 border-b bg-slate-50">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="text-base font-semibold text-slate-900">Mapa de calor: Colombia</div>
                        <div class="text-sm text-slate-500">Registros por municipio</div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="text-xs text-slate-500">Departamento</div>
                        <select id="selectDepto" class="rounded-xl border-slate-200 bg-white py-2 px-3 text-sm">
                            <option value="AMAZONAS">AMAZONAS</option>
                            <option value="ANTIOQUIA">ANTIOQUIA</option>
                            <option value="ARAUCA">ARAUCA</option>
                            <option value="ATLÁNTICO">ATLÁNTICO</option>
                            <option value="BOGOTÁ">BOGOTÁ</option>
                            <option value="BOLÍVAR">BOLÍVAR</option>
                            <option value="BOYACÁ">BOYACÁ</option>
                            <option value="CALDAS">CALDAS</option>
                            <option value="CAQUETÁ">CAQUETÁ</option>
                            <option value="CASANARE">CASANARE</option>
                            <option value="CAUCA">CAUCA</option>
                            <option value="CESAR">CESAR</option>
                            <option value="CHOCÓ">CHOCÓ</option>
                            <option value="CÓRDOBA">CÓRDOBA</option>
                            <option value="CUNDINAMARCA">CUNDINAMARCA</option>
                            <option value="GUAINÍA">GUAINÍA</option>
                            <option value="GUAVIARE">GUAVIARE</option>
                            <option value="HUILA">HUILA</option>
                            <option value="LA GUAJIRA">LA GUAJIRA</option>
                            <option value="MAGDALENA">MAGDALENA</option>
                            <option value="META" selected>META</option>
                            <option value="NARIÑO">NARIÑO</option>
                            <option value="NORTE DE SANTANDER">NORTE DE SANTANDER</option>
                            <option value="PUTUMAYO">PUTUMAYO</option>
                            <option value="QUINDÍO">QUINDÍO</option>
                            <option value="RISARALDA">RISARALDA</option>
                            <option value="SAN ANDRÉS">SAN ANDRÉS</option>
                            <option value="SANTANDER">SANTANDER</option>
                            <option value="SUCRE">SUCRE</option>
                            <option value="TOLIMA">TOLIMA</option>
                            <option value="VALLE DEL CAUCA">VALLE DEL CAUCA</option>
                            <option value="VAUPÉS">VAUPÉS</option>
                            <option value="VICHADA">VICHADA</option>
                        </select>
                    </div>
                </div>
            </div>
            <div wire:ignore>
                <div id="metaMap" class="h-[760px] w-full rounded-2xl border"></div>
            </div>
        </div>

        {{-- Bloque: Género + Edades --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

            {{-- Card: Género --}}
            <div class="bg-white shadow-sm rounded-2xl border overflow-hidden">
                <div class="p-5 border-b bg-slate-50">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-base font-semibold text-slate-900">Género</div>
                            <div class="text-sm text-slate-500">Distribución</div>
                        </div>
                        <div class="text-xs text-slate-500">Gráfico Dona</div>
                    </div>
                </div>

                <div class="p-5">
                    @if (empty($generos))
                        <div class="text-sm text-slate-500">Aún no hay datos suficientes.</div>
                    @else
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                            {{-- Gráfico --}}
                            <div class="h-[340px] w-full" wire:ignore>
                                <canvas id="donutGenero"></canvas>
                            </div>

                            {{-- Tabla con scroll --}}
                            <div class="rounded-xl border overflow-hidden">
                                <div class="max-h-[340px] overflow-y-auto">
                                    <table class="min-w-full text-sm">
                                        <thead
                                            class="sticky top-0 z-10 text-xs uppercase tracking-wide text-slate-500 bg-slate-50">
                                            <tr class="border-b">
                                                <th class="py-3 px-3 text-left">Género</th>
                                                <th class="py-3 px-3 text-right">Registros</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            @foreach ($generos as $g)
                                                <tr class="hover:bg-slate-50">
                                                    <td class="py-3 px-3 text-slate-800">{{ $g['label'] }}</td>
                                                    <td class="py-3 px-3 text-right font-semibold text-slate-900">
                                                        {{ $g['value'] }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="lg:col-span-2 text-xs text-slate-500">
                                Distribución total por género (incluye “Prefiero no responder” si aplica).
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Card: Edades --}}
            <div class="bg-white shadow-sm rounded-2xl border overflow-hidden">
                <div class="p-5 border-b bg-slate-50">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-base font-semibold text-slate-900">Edades</div>
                            <div class="text-sm text-slate-500">Rangos</div>
                        </div>
                        <div class="text-xs text-slate-500">Gráfico Dona</div>
                    </div>
                </div>

                <div class="p-5">
                    @if (empty($rangosEdad))
                        <div class="text-sm text-slate-500">Aún no hay datos suficientes.</div>
                    @else
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                            {{-- Gráfico --}}
                            <div class="h-[340px] w-full" wire:ignore>
                                <canvas id="donutEdades"></canvas>
                            </div>

                            {{-- Tabla con scroll --}}
                            <div class="rounded-xl border overflow-hidden">
                                <div class="max-h-[340px] overflow-y-auto">
                                    <table class="min-w-full text-sm">
                                        <thead
                                            class="sticky top-0 z-10 text-xs uppercase tracking-wide text-slate-500 bg-slate-50">
                                            <tr class="border-b">
                                                <th class="py-3 px-3 text-left">Rango</th>
                                                <th class="py-3 px-3 text-right">Registros</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            @foreach ($rangosEdad as $e)
                                                <tr class="hover:bg-slate-50">
                                                    <td class="py-3 px-3 text-slate-800">{{ $e['label'] }}</td>
                                                    <td class="py-3 px-3 text-right font-semibold text-slate-900">
                                                        {{ $e['value'] }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="lg:col-span-2 text-xs text-slate-500">
                                Rangos de edad para lectura rápida (ideal para comparativos).
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // --- Helpers ---
        const __charts = window.__charts || (window.__charts = {});
        const __dash = window.__dash || (window.__dash = {
            inited: false,
            data: {},
            filters: {
                analisis_politico: false
            }
        });
        window.__dash = __dash;

        function destroyChart(key) {
            if (__charts[key]) {
                try {
                    __charts[key].destroy();
                } catch (e) {}
                delete __charts[key];
            }
        }

        function normalizeDeptKey(s) {
            return (s || '')
                .toString()
                .trim()
                .toUpperCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^\w\s]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/\s/g, '_');
        }

        function getDeptSelected() {
            const sel = document.getElementById('selectDepto');
            return sel ? String(sel.value || '').trim() : 'META';
        }

        function deptGeoUrl(deptName) {
            return `{{ asset('data/deptos') }}/${encodeURIComponent(deptName)}.geojson`;
        }

        function normalizeName(s) {
            return (s || '')
                .toString()
                .trim()
                .toUpperCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function getFeatureMunicipioName(props) {
            return props?.NOM_MPIO || '';
        }

        function getFeatureDepartamentoName(props) {
            return props?.NOM_DPTO || '';
        }

        function colorScale(v) {
            if (v >= 500) return '#7f1d1d';
            if (v >= 200) return '#dc2626';
            if (v >= 100) return '#f97316';
            if (v >= 50) return '#f59e0b';
            if (v >= 10) return '#fde047';
            if (v > 0) return '#86efac';
            return '#f1f5f9';
        }

        function formatNumber(n) {
            try {
                return new Intl.NumberFormat('es-CO').format(n);
            } catch {
                return String(n);
            }
        }

        function safeLabel(label, max = 20) {
            const s = String(label || '—');
            return s.length > max ? s.slice(0, max - 1) + '…' : s;
        }

        // ---------------------------
        //  FILTRO: "Análisis político"
        // ---------------------------
        // Regla práctica: si el label/referrer trae keywords políticas, lo excluimos cuando el filtro esté activado.
        // Ajusta keywords a tu realidad (esto suele vivir feliz en un config).
        const __POLITICAL_KWS = [
            'POLITIC', 'PARTIDO', 'CANDIDAT', 'CAMPAÑ', 'ELECC',
            'ALCALD', 'GOBERN', 'CONCEJ', 'ASAMBLEA', 'SENAD', 'CAMARA',
            'DIPUT', 'MINIST', 'PRESID', 'VICEPRES', 'REGID', 'EDIL',
            'VOTAC', 'URNA', 'PLEBISC', 'REFEREN', 'MOVIMIENTO'
        ].map(normalizeName);

        function isPoliticalLabel(s) {
            const x = normalizeName(s);
            if (!x) return false;
            return __POLITICAL_KWS.some(kw => x.includes(kw));
        }

        function applyPoliticalFilterToTopReferrers(items, enabled) {
            if (!enabled) return items;
            return (items || []).filter(x => !isPoliticalLabel(x?.name));
        }

        // Si algún donut lo usas para “fuentes / categorías”, puedes filtrar igual.
        function applyPoliticalFilterToDonut(items, enabled) {
            if (!enabled) return items;
            return (items || []).filter(x => !isPoliticalLabel(x?.label));
        }

        // --- Center text plugin for doughnut ---
        const CenterTextPlugin = {
            id: 'centerText',
            afterDraw(chart, args, pluginOptions) {
                const opts = pluginOptions || {};
                const text1 = opts.text1 || '';
                const text2 = opts.text2 || '';
                if (!text1 && !text2) return;

                const {
                    ctx
                } = chart;
                const meta = chart.getDatasetMeta(0);
                if (!meta?.data?.length) return;

                const x = meta.data[0].x;
                const y = meta.data[0].y;

                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                ctx.font = '800 18px system-ui, -apple-system, Segoe UI, Roboto, Arial';
                ctx.fillStyle = '#0f172a';
                ctx.fillText(text1, x, y - 6);

                ctx.font = '600 11px system-ui, -apple-system, Segoe UI, Roboto, Arial';
                ctx.fillStyle = '#64748b';
                ctx.fillText(text2, x, y + 14);
                ctx.restore();
            }
        };

        function totalOf(values) {
            return values.reduce((acc, v) => acc + (Number(v) || 0), 0);
        }

        function mountDonut({
            canvasId,
            data,
            key
        }) {
            const el = document.getElementById(canvasId);
            if (!el) return;

            if (!Array.isArray(data) || data.length === 0) {
                destroyChart(key);
                return;
            }

            destroyChart(key);

            const labels = data.map(x => safeLabel(x.label));
            const values = data.map(x => Number(x.value) || 0);
            const total = totalOf(values);

            __charts[key] = new Chart(el, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        borderWidth: 2
                    }]
                },
                plugins: [CenterTextPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const label = ctx.label || '';
                                    const value = Number(ctx.raw) || 0;
                                    const pct = total ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${formatNumber(value)} (${pct}%)`;
                                }
                            }
                        },
                        centerText: {
                            text1: formatNumber(total),
                            text2: 'Total'
                        }
                    }
                }
            });
        }

        function mountBarTop({
            canvasId,
            data,
            key
        }) {
            const el = document.getElementById(canvasId);
            if (!el) return;

            if (!Array.isArray(data) || data.length === 0) {
                destroyChart(key);
                return;
            }

            destroyChart(key);

            const labels = data.map(x => safeLabel(x.name, 18));
            const values = data.map(x => Number(x.total) || 0);

            __charts[key] = new Chart(el, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        borderWidth: 0,
                        borderRadius: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                callback: (v) => formatNumber(v)
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        const isMobile = window.matchMedia('(max-width: 768px)').matches;

        function loadDeptLayer(deptKey) {
            if (!window.__metaMap) return;

            // token/abort para que no se pisen fetch viejos
            __dash.__mapToken = (__dash.__mapToken || 0) + 1;
            const token = __dash.__mapToken;

            if (__dash.__geoAbort) {
                try {
                    __dash.__geoAbort.abort();
                } catch (e) {}
            }
            const controller = new AbortController();
            __dash.__geoAbort = controller;

            // remove capa anterior
            if (window.__deptLayer) {
                try {
                    window.__metaMap.removeLayer(window.__deptLayer);
                } catch (e) {}
                window.__deptLayer = null;
            }

            const countsNormalized = window.__countsNormalized || {};

            fetch(deptGeoUrl(deptKey), {
                    cache: 'no-store',
                    signal: controller.signal
                })
                .then(async (r) => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    return r.json();
                })
                .then((geo) => {
                    if (token !== __dash.__mapToken) return;
                    if (!window.__metaMap) return;

                    window.__deptLayer = L.geoJSON(geo, {
                        style: (feature) => {
                            const muniName = normalizeName(getFeatureMunicipioName(feature.properties));
                            const v = countsNormalized[muniName] || 0;
                            return {
                                color: '#0f172a',
                                weight: 1,
                                fillColor: colorScale(v),
                                fillOpacity: 0.85
                            };
                        },
                        onEachFeature: (feature, lyr) => {
                            const muniRaw = getFeatureMunicipioName(feature.properties);
                            const v = countsNormalized[normalizeName(muniRaw)] || 0;

                            lyr.on('mouseover', function() {
                                this.setStyle({
                                    weight: 2,
                                    color: '#111827'
                                });
                            });
                            lyr.on('mouseout', function() {
                                this.setStyle({
                                    weight: 1,
                                    color: '#0f172a'
                                });
                            });

                            lyr.bindTooltip(
                                `<div style="font-weight:800">${muniRaw}</div><div>Registros: ${formatNumber(v)}</div>`, {
                                    sticky: true
                                }
                            );
                        }
                    }).addTo(window.__metaMap);

                    // zoom al depto
                    const b = window.__deptLayer.getBounds();
                    window.__metaMap.fitBounds(b, {
                        padding: isMobile ? [2, 2] : [10, 10]
                    });

                    window.__metaMap.whenReady(() => window.__metaMap.invalidateSize());
                })
                .catch((err) => {
                    if (err?.name === 'AbortError') return;
                    console.warn('No se pudo cargar depto:', deptKey, err);
                });
        }

        function bindDeptSelectOnce() {
            if (__dash.__deptBound) return;
            __dash.__deptBound = true;

            const sel = document.getElementById('selectDepto');
            if (!sel) return;

            sel.addEventListener('change', () => {
                loadDeptLayer(getDeptSelected());
            });
        }

        // --- Leaflet map ---
        function initMetaMap() {
            const el = document.getElementById('metaMap');
            if (!el || typeof L === 'undefined') return;

            const h = el.getBoundingClientRect().height;
            if (!h || h < 50) return;

            // Si ya existe, NO lo destruyas: solo cambia capa
            if (window.__metaMap) {
                loadDeptLayer(getDeptSelected());
                try {
                    window.__metaMap.invalidateSize();
                } catch (e) {}
                return;
            }

            // prepara conteos 1 sola vez
            const counts = __dash.data?.conteoPorMunicipio || {};
            const countsNormalized = {};
            Object.keys(counts || {}).forEach(k => countsNormalized[normalizeName(k)] = Number(counts[k] || 0));
            window.__countsNormalized = countsNormalized;

            // Limpia id interno si Livewire reusó el nodo
            if (el._leaflet_id) {
                try {
                    delete el._leaflet_id;
                } catch (e) {}
            }
            el.innerHTML = '';

            const map = L.map(el, {
                zoomControl: !isMobile,
                scrollWheelZoom: !isMobile,
                doubleClickZoom: true,
                touchZoom: true,
                boxZoom: false,
                keyboard: false,
                dragging: true,
                tap: false,
                preferCanvas: true,
                bounceAtZoomLimits: false,
            });

            window.__metaMap = map;

            // leyenda (igual que la tuya)
            const legend = L.control({
                position: 'bottomright'
            });
            legend.onAdd = function() {
                const div = L.DomUtil.create('div', 'info legend');
                const grades = [0, 10, 50, 100, 200, 500];
                const labels = [];

                div.style.background = 'rgba(255,255,255,0.92)';
                div.style.padding = '10px 12px';
                div.style.borderRadius = '12px';
                div.style.border = '1px solid #e2e8f0';
                div.style.fontSize = '12px';
                div.style.color = '#0f172a';
                div.style.boxShadow = '0 8px 20px rgba(2,6,23,.12)';
                div.innerHTML += `<div style="font-weight:800;margin-bottom:6px">Registros</div>`;

                for (let i = 0; i < grades.length; i++) {
                    const from = grades[i];
                    const to = grades[i + 1];
                    labels.push(
                        `<div style="display:flex;align-items:center;gap:8px;margin:4px 0">
                    <span style="width:14px;height:14px;border-radius:4px;background:${colorScale(from + 1)};border:1px solid rgba(15,23,42,.25)"></span>
                    <span>${from}${to ? `–${to - 1}` : '+'}</span>
                </div>`
                    );
                }

                div.innerHTML += labels.join('');
                return div;
            };
            legend.addTo(map);

            // base map
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            // vista inicial Colombia
            map.setView([4.5709, -74.2973], isMobile ? 5 : 6);

            // carga depto actual
            loadDeptLayer(getDeptSelected());

            // por si el mapa se renderiza en un contenedor recién mostrado
            map.whenReady(() => map.invalidateSize());
        }


        function readUIFilters() {
            // Debes tener un toggle/checkbox con id="toggleAnalisisPolitico" (true => filtra lo político)
            const el = document.getElementById('toggleAnalisisPolitico');
            if (el) __dash.filters.analisis_politico = !!el.checked;
        }

        function bindUIFiltersOnce() {
            if (__dash.__filtersBound) return;
            __dash.__filtersBound = true;

            const el = document.getElementById('toggleAnalisisPolitico');
            if (!el) return;

            el.addEventListener('change', () => {
                readUIFilters();
                renderDashboard();
            });
        }

        function renderDashboard() {
            readUIFilters();
            bindUIFiltersOnce();
            bindDeptSelectOnce(); // 👈 NUEVO

            const fPolitico = __dash.filters.analisis_politico === true;

            const topRef = applyPoliticalFilterToTopReferrers(__dash.data?.topReferrers || [], fPolitico);
            mountBarTop({
                canvasId: 'barTopReferrers',
                data: topRef,
                key: '__barTopReferrers'
            });

            const municipiosVotan = applyPoliticalFilterToDonut(__dash.data?.municipiosVotan || [], false);
            const generos = applyPoliticalFilterToDonut(__dash.data?.generos || [], false);
            const rangosEdad = applyPoliticalFilterToDonut(__dash.data?.rangosEdad || [], false);

            mountDonut({
                canvasId: 'donutMunicipios',
                data: municipiosVotan,
                key: '__donutMunicipios'
            });
            mountDonut({
                canvasId: 'donutGenero',
                data: generos,
                key: '__donutGenero'
            });
            mountDonut({
                canvasId: 'donutEdades',
                data: rangosEdad,
                key: '__donutEdades'
            });

            scheduleMetaMap();
        }

        function scheduleMetaMap() {
            if (__dash.__mapScheduled) return;
            __dash.__mapScheduled = true;

            requestAnimationFrame(() => {
                __dash.__mapScheduled = false;

                if (window.__metaMap) {
                    try {
                        window.__metaMap.invalidateSize();
                    } catch (e) {}
                    return;
                }

                initMetaMap();
            });
        }

        function initDashboard() {
            __dash.data = {
                conteoPorMunicipio: @json($conteoPorMunicipio),
                topReferrers: @json($topReferrers),
                municipiosVotan: @json($municipiosVotan),
                generos: @json($generos),
                rangosEdad: @json($rangosEdad),
            };

            renderDashboard(); // aquí adentro ya se llamará scheduleMetaMap()
        }
        window.addEventListener('dashboard:data', (e) => {
            if (e?.detail) {
                __dash.data = {
                    ...__dash.data,
                    ...e.detail
                };
                renderDashboard(); // agenda el mapa, no lo destruye/recrea
            }
        });

        document.addEventListener('DOMContentLoaded', initDashboard);
        document.addEventListener('livewire:navigated', initDashboard);
    </script>
@endpush
