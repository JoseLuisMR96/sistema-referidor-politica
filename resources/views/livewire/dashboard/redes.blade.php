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
