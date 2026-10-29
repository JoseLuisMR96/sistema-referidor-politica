<x-slot name="header">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="font-extrabold text-xl text-slate-900 leading-tight">Dashboard de redes sociales</h2>
            <div class="text-sm text-slate-500">
                {{ $esTodos ? 'Comparativo entre candidatos' : "Facebook e Instagram de {$candidato}" }}
            </div>
        </div>
        @can('redes.configurar')
            <a href="{{ route('redes.index') }}" class="text-sm font-semibold text-indigo-700 underline">Configurar cuentas</a>
        @endcan
    </div>
</x-slot>

<div class="space-y-6">
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
        Seguidores e interacciones no son votos: úsalos como tendencia. Los datos se actualizan a diario a las 06:00
        y el cambio semanal aparece cuando hay al menos 7 días de historia.
    </div>

    @if ($esTodos)
        {{-- ===== Comparativo entre candidatos ===== --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="text-lg font-extrabold text-slate-900 mb-4">Candidatos</div>

            @if (empty($comparativo))
                <div class="text-sm text-slate-500">
                    Ningún candidato tiene cuentas de redes registradas todavía.
                    @can('redes.configurar')
                        Elige un candidato en el menú y regístralas en
                        <a href="{{ route('redes.index') }}" class="underline font-semibold text-indigo-700">Redes sociales</a>.
                    @endcan
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="py-2 pr-4">Candidato</th>
                                <th class="py-2 px-3 text-right">Cuentas</th>
                                <th class="py-2 px-3 text-right">Seguidores</th>
                                <th class="py-2 px-3 text-right">Últimos 7 días</th>
                                <th class="py-2 px-3 text-right">Interacciones 7d</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($comparativo as $fila)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <div class="font-semibold text-slate-900">{{ $fila['tenant'] }}</div>
                                        <div class="text-xs text-slate-500">{{ $fila['descripcion'] }}</div>
                                    </td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $fila['cuentas'] }}</td>
                                    <td class="py-2 px-3 text-right font-bold tabular-nums">{{ number_format($fila['seguidores']) }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">
                                        @if ($fila['cambio_7d'] !== null)
                                            <span class="{{ $fila['cambio_7d'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }} font-semibold">
                                                {{ $fila['cambio_7d'] >= 0 ? '+' : '' }}{{ number_format($fila['cambio_7d']) }}
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ number_format($fila['interacciones_7d']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif (empty($cuentas))
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-6 text-sm text-slate-600">
            {{ $candidato }} todavía no tiene cuentas de Facebook o Instagram registradas.
            @can('redes.configurar')
                Agrégalas en <a href="{{ route('redes.index') }}" class="underline font-semibold text-indigo-700">Redes sociales</a>
                y pulsa "Sincronizar" para ver aquí sus seguidores y su crecimiento.
            @else
                Pídele a un administrador que las registre.
            @endcan
        </div>
    @else
        {{-- ===== KPIs del candidato ===== --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <div class="text-xs uppercase tracking-wider text-slate-400">Seguidores (todas las cuentas)</div>
                <div class="mt-1 text-3xl font-extrabold text-slate-900 tabular-nums">{{ number_format($totales['seguidores']) }}</div>
                <div class="text-xs text-slate-500">{{ $totales['cuentas'] }} {{ $totales['cuentas'] === 1 ? 'cuenta' : 'cuentas' }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <div class="text-xs uppercase tracking-wider text-slate-400">Crecimiento últimos 7 días</div>
                @if ($totales['cambio_7d'] !== null)
                    <div class="mt-1 text-3xl font-extrabold tabular-nums {{ $totales['cambio_7d'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $totales['cambio_7d'] >= 0 ? '+' : '' }}{{ number_format($totales['cambio_7d']) }}
                    </div>
                @else
                    <div class="mt-1 text-3xl font-extrabold text-slate-300">—</div>
                    <div class="text-xs text-slate-500">Falta una semana de historia</div>
                @endif
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <div class="text-xs uppercase tracking-wider text-slate-400">Interacciones últimos 7 días</div>
                <div class="mt-1 text-3xl font-extrabold text-slate-900 tabular-nums">{{ number_format($totales['interacciones_7d']) }}</div>
                <div class="text-xs text-slate-500">Me gusta + comentarios</div>
            </div>
        </div>

        {{-- ===== Gráfica de crecimiento semanal ===== --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="text-lg font-extrabold text-slate-900">Seguidores por semana</div>
            <div class="text-xs text-slate-500 mb-4">Valor al cierre de cada semana (lunes a domingo).</div>
            <div class="h-72" wire:ignore>
                <canvas id="graficaRedes"></canvas>
            </div>
        </div>

        {{-- ===== Detalle por cuenta ===== --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="text-lg font-extrabold text-slate-900 mb-4">Cuentas</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Cuenta</th>
                            <th class="py-2 px-3 text-right">Seguidores</th>
                            <th class="py-2 px-3 text-right">Últimos 7 días</th>
                            <th class="py-2 px-3 text-right">Interacciones 7d</th>
                            @foreach ($cuentas[0]['semanas'] as $s)
                                <th class="py-2 px-2 text-right whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($s['semana'])->format('d/m') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($cuentas as $r)
                            <tr>
                                <td class="py-2 pr-4">
                                    <div class="font-semibold text-slate-900">
                                        {{ ucfirst($r['red']) }} ·
                                        @if ($r['url'])
                                            <a href="{{ $r['url'] }}" target="_blank" rel="noopener" class="underline">{{ $r['usuario'] }}</a>
                                        @else
                                            {{ $r['usuario'] }}
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ $r['ultimo_sync_at'] ? 'Actualizado '.\Illuminate\Support\Carbon::parse($r['ultimo_sync_at'])->diffForHumans() : 'Sin sincronizar' }}
                                    </div>
                                    @if ($r['ultimo_error'])
                                        <div class="text-xs text-rose-600">{{ $r['ultimo_error'] }}</div>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-right font-bold tabular-nums">{{ $r['seguidores'] !== null ? number_format($r['seguidores']) : '—' }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">
                                    @if ($r['cambio_7d'] !== null)
                                        <span class="{{ $r['cambio_7d'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }} font-semibold">
                                            {{ $r['cambio_7d'] >= 0 ? '+' : '' }}{{ number_format($r['cambio_7d']) }}
                                            @if ($r['cambio_7d_pct'] !== null)
                                                ({{ $r['cambio_7d_pct'] >= 0 ? '+' : '' }}{{ $r['cambio_7d_pct'] }}%)
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r['interacciones_7d'] !== null ? number_format($r['interacciones_7d']) : '—' }}</td>
                                @foreach ($r['semanas'] as $s)
                                    <td class="py-2 px-2 text-right tabular-nums text-slate-600">{{ $s['seguidores'] !== null ? number_format($s['seguidores']) : '·' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ===== Frente a sus rivales (Instagram) ===== --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="text-lg font-extrabold text-slate-900">Frente a sus rivales en Instagram</div>
            <div class="text-xs text-slate-500 mb-4">
                Ordenado por quién crece más rápido en 7 días. El engagement son las interacciones de la semana por cada 100 seguidores:
                sirve para comparar cuentas de distinto tamaño.
            </div>

            @if (empty($rivales))
                <div class="text-sm text-slate-600">
                    No hay rivales registrados.
                    @can('redes.configurar')
                        Agrégalos en <a href="{{ route('redes.index') }}" class="underline font-semibold text-indigo-700">Redes sociales</a>
                        como "Rival (otro candidato)" con su usuario de Instagram.
                    @endcan
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="py-2 pr-4">#</th>
                                <th class="py-2 pr-4">Cuenta</th>
                                <th class="py-2 px-3 text-right">Seguidores</th>
                                <th class="py-2 px-3 text-right">Crecimiento 7d</th>
                                <th class="py-2 px-3 text-right">Engagement</th>
                                <th class="py-2 px-3 text-right">Publicaciones 7d</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($rivales as $pos => $r)
                                <tr class="{{ $r['es_rival'] ? '' : 'bg-indigo-50' }}">
                                    <td class="py-2 pr-4 text-slate-400 tabular-nums">{{ $pos + 1 }}</td>
                                    <td class="py-2 pr-4">
                                        <div class="font-semibold text-slate-900">
                                            {{ $r['es_rival'] ? $r['nombre'] : $candidato }}
                                            @unless ($r['es_rival'])
                                                <span class="ml-1 rounded-full bg-indigo-600 text-white px-2 py-0.5 text-xs">Candidato</span>
                                            @endunless
                                        </div>
                                        <div class="text-xs text-slate-500">{{ '@'.$r['usuario'] }}</div>
                                        @if ($r['ultimo_error'])
                                            <div class="text-xs text-rose-600">{{ $r['ultimo_error'] }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right font-bold tabular-nums">{{ $r['seguidores'] !== null ? number_format($r['seguidores']) : '—' }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">
                                        @if ($r['cambio_7d_pct'] !== null)
                                            <span class="{{ $r['cambio_7d'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }} font-semibold">
                                                {{ $r['cambio_7d_pct'] >= 0 ? '+' : '' }}{{ $r['cambio_7d_pct'] }}%
                                            </span>
                                            <div class="text-xs text-slate-500">{{ $r['cambio_7d'] >= 0 ? '+' : '' }}{{ number_format($r['cambio_7d']) }}</div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $r['engagement'] !== null ? $r['engagement'].'%' : '—' }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $r['publicaciones_7d'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ===== Tono de los comentarios ===== --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
            <div>
                <div class="text-lg font-extrabold text-slate-900">Tono de los comentarios</div>
                <div class="text-xs text-slate-500">
                    Comentarios de sus publicaciones de los últimos 7 días, clasificados por IA. Solo se guardan los conteos: nunca el texto ni quién comentó.
                    Es la opinión de quienes comentan, no de toda la población.
                </div>
            </div>

            @if (! $hayTokenCandidato)
                <div class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600">
                    Para leer los comentarios hace falta el <strong>token de la cuenta oficial</strong> de {{ $candidato }}: Meta no deja leer comentarios de cuentas ajenas.
                    Cuando lo tengas, pégalo en su cuenta en Redes sociales.
                </div>
            @elseif ($tono['total'] === 0)
                <div class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600">
                    Todavía no hay comentarios analizados. Se analizan cada día con la sincronización, o con "Analizar comentarios" en Redes sociales
                    (requiere la API key de IA configurada).
                </div>
            @else
                @php
                    $pct = fn ($n) => $tono['total'] > 0 ? round($n / $tono['total'] * 100) : 0;
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4">
                        <div class="text-xs text-emerald-700">A favor</div>
                        <div class="text-2xl font-extrabold text-emerald-900 tabular-nums">{{ $pct($tono['a_favor']) }}%</div>
                        <div class="text-xs text-emerald-700 tabular-nums">{{ number_format($tono['a_favor']) }} comentarios</div>
                    </div>
                    <div class="rounded-xl bg-rose-50 border border-rose-100 p-4">
                        <div class="text-xs text-rose-700">En contra</div>
                        <div class="text-2xl font-extrabold text-rose-900 tabular-nums">{{ $pct($tono['en_contra']) }}%</div>
                        <div class="text-xs text-rose-700 tabular-nums">{{ number_format($tono['en_contra']) }} comentarios</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                        <div class="text-xs text-slate-600">Neutros</div>
                        <div class="text-2xl font-extrabold text-slate-900 tabular-nums">{{ $pct($tono['neutro']) }}%</div>
                        <div class="text-xs text-slate-600 tabular-nums">{{ number_format($tono['neutro']) }} comentarios</div>
                    </div>
                </div>

                <div>
                    <div class="text-sm font-semibold text-slate-700 mb-2">Por semana</div>
                    <div class="space-y-1">
                        @foreach ($tono['semanas'] as $s)
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-12 text-slate-500 tabular-nums">{{ \Illuminate\Support\Carbon::parse($s['semana'])->format('d/m') }}</div>
                                @if ($s['total'] > 0)
                                    <div class="flex h-4 flex-1 overflow-hidden rounded bg-slate-100"
                                        title="{{ $s['a_favor'] }} a favor · {{ $s['en_contra'] }} en contra · {{ $s['neutro'] }} neutros">
                                        <div class="bg-emerald-500" style="width: {{ $s['a_favor'] / $s['total'] * 100 }}%"></div>
                                        <div class="bg-rose-500" style="width: {{ $s['en_contra'] / $s['total'] * 100 }}%"></div>
                                        <div class="bg-slate-300" style="width: {{ $s['neutro'] / $s['total'] * 100 }}%"></div>
                                    </div>
                                    <div class="w-24 text-right tabular-nums text-slate-600">{{ round($s['a_favor'] / $s['total'] * 100) }}% a favor</div>
                                @else
                                    <div class="flex-1 text-slate-400">Sin comentarios analizados</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @if (! empty($tono['temas']))
                    <div>
                        <div class="text-sm font-semibold text-slate-700 mb-2">Temas que más se mencionan</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($tono['temas'] as $tema => $veces)
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $tema }} · {{ $veces }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>

@if (! $esTodos && ! empty($cuentas))
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const cuentas = @json($cuentas);
                const el = document.getElementById('graficaRedes');
                if (!el || typeof Chart === 'undefined' || !cuentas.length) return;

                const etiquetas = cuentas[0].semanas.map(s => {
                    const [a, m, d] = s.semana.split('-');
                    return `${d}/${m}`;
                });

                // Colores fijos por red, con variación si hay varias de la misma
                const colores = { facebook: '#1877F2', instagram: '#C13584' };

                new Chart(el, {
                    type: 'line',
                    data: {
                        labels: etiquetas,
                        datasets: cuentas.map(c => ({
                            label: `${c.red} · ${c.usuario}`,
                            data: c.semanas.map(s => s.seguidores),
                            borderColor: colores[c.red] || '#334155',
                            backgroundColor: colores[c.red] || '#334155',
                            spanGaps: true,
                            tension: 0.25,
                        })),
                    },
                    options: {
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: { y: { ticks: { callback: v => Number(v).toLocaleString('es-CO') } } },
                    },
                });
            })();
        </script>
    @endpush
@endif
