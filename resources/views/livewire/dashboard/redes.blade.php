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
    @if ($avisoToken)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span class="font-semibold">{{ $avisoToken }}</span>
            Genera un token en el Graph API Explorer de Meta y pégalo en
            @can('redes.configurar')
                <a href="{{ route('redes.index') }}" class="underline font-semibold">Configuración → Cuentas de redes</a>:
            @else
                Configuración → Redes sociales (pídeselo a un administrador):
            @endcan
            el sistema lo extiende solo a 60 días.
        </div>
    @endif
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
                        <a href="{{ route('redes.index') }}" class="underline font-semibold text-indigo-700">Cuentas de redes</a>.
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
                Agrégalas en <a href="{{ route('redes.index') }}" class="underline font-semibold text-indigo-700">Cuentas de redes</a>
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
        {{-- Rivales (solo Instagram): se suman a la gráfica y a la tabla si hay alguno --}}
        @php
            $soloRivales = collect($rivales)->where('es_rival', true)->values()->all();
        @endphp
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                <div>
                    <div class="text-lg font-extrabold text-slate-900">Seguidores por semana</div>
                    <div class="text-xs text-slate-500">
                        Valor al cierre de cada semana (lunes a domingo).
                        @if ($soloRivales)
                            Las líneas punteadas son los rivales (solo Instagram). Toca un nombre en la leyenda para ocultarlo.
                        @endif
                    </div>
                </div>
                @if ($soloRivales)
                    <div class="inline-flex rounded-xl border border-slate-200 p-0.5 text-xs font-semibold" wire:ignore>
                        <button type="button" data-modo="seguidores" class="modo-redes rounded-lg px-3 py-1.5 bg-indigo-600 text-white">Seguidores</button>
                        <button type="button" data-modo="crecimiento" class="modo-redes rounded-lg px-3 py-1.5 text-slate-600"
                            title="Cambio desde la primera semana con datos: compara cuentas de distinto tamaño">Crecimiento %</button>
                    </div>
                @endif
            </div>
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
                        @foreach ([...$cuentas, ...$soloRivales] as $r)
                            <tr class="{{ $r['es_rival'] ? 'bg-slate-50' : '' }}">
                                <td class="py-2 pr-4">
                                    <div class="font-semibold text-slate-900">
                                        {{ ucfirst($r['red']) }} ·
                                        @if ($r['url'])
                                            <a href="{{ $r['url'] }}" target="_blank" rel="noopener" class="underline">{{ $r['usuario'] }}</a>
                                        @else
                                            {{ $r['usuario'] }}
                                        @endif
                                        @if ($r['es_rival'])
                                            <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Rival</span>
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
                        Agrégalos en <a href="{{ route('redes.index') }}" class="underline font-semibold text-indigo-700">Cuentas de redes</a>
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

        {{-- ===== Qué contenido funciona (Instagram) ===== --}}
        @php
            $co = $contenido;
            $etiquetas = [
                'por_tipo' => \App\Models\RedPublicacion::TIPOS + ['otro' => 'Otro'],
                'por_dia' => \App\Services\Redes\RedesStats::DIAS,
                'por_franja' => array_map(fn ($f) => $f[0], \App\Services\Redes\RedesStats::FRANJAS),
                'por_tema' => \App\Models\RedPublicacion::TEMAS + ['sin_clasificar' => 'Sin clasificar aún', 'sin_texto' => 'Sin texto'],
            ];
            $titulos = ['por_tipo' => 'Por formato', 'por_dia' => 'Por día', 'por_franja' => 'Por hora (Colombia)', 'por_tema' => 'Por tema'];
            $clavesMejor = ['por_tipo' => 'tipo', 'por_dia' => 'dia', 'por_franja' => 'franja', 'por_tema' => 'tema'];
            $fmtEng = fn ($v) => $v === null ? '—' : number_format($v, 2, ',', '.');
        @endphp
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div>
                <div class="text-lg font-extrabold text-slate-900">Qué contenido funciona en Instagram</div>
                <div class="text-xs text-slate-500">
                    Publicaciones de los últimos {{ $co['dias'] ?? 30 }} días{{ ($co['hay_rivales'] ?? false) ? ', del candidato y de sus rivales' : '' }}.
                    <strong>Rendimiento</strong> = interacciones (me gusta + comentarios) por cada 100 seguidores: así se comparan cuentas de distinto tamaño.
                </div>
            </div>

            @if (! ($co['hay'] ?? false))
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                    Todavía no hay publicaciones guardadas. Se llenan con la sincronización diaria de las 6:00
                    (necesita una cuenta de Instagram registrada en Cuentas de redes).
                </div>
            @else
                {{-- Lo que mejor le funciona al candidato --}}
                @php
                    $mejores = array_filter([
                        'Formato' => $co['mejor']['tipo'] ? ($etiquetas['por_tipo'][$co['mejor']['tipo']] ?? null) : null,
                        'Día' => $co['mejor']['dia'] ? ($etiquetas['por_dia'][$co['mejor']['dia']] ?? null) : null,
                        'Hora' => $co['mejor']['franja'] ? ($etiquetas['por_franja'][$co['mejor']['franja']] ?? null) : null,
                        'Tema' => $co['mejor']['tema'] ? ($etiquetas['por_tema'][$co['mejor']['tema']] ?? null) : null,
                    ]);
                @endphp
                @if ($mejores)
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4">
                        <div class="text-sm font-semibold text-indigo-900 mb-2">Lo que mejor le funciona al candidato</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($mejores as $que => $valor)
                                <span class="rounded-full bg-white border border-indigo-200 px-3 py-1 text-sm">
                                    <span class="text-slate-500">{{ $que }}:</span> <strong class="text-slate-900">{{ $valor }}</strong>
                                </span>
                            @endforeach
                        </div>
                        <div class="text-xs text-indigo-800 mt-2">Con al menos 2 publicaciones en cada caso. Es una pista, no una regla: pocas publicaciones pueden engañar.</div>
                    </div>
                @endif

                {{-- Ritmo y rendimiento por cuenta --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="py-2 pr-4">Cuenta</th>
                                <th class="py-2 px-3 text-right">Seguidores</th>
                                <th class="py-2 px-3 text-right">Publica por semana</th>
                                <th class="py-2 px-3 text-right">Rendimiento</th>
                                <th class="py-2 px-3 text-right">Interacciones por publicación</th>
                                <th class="py-2 px-3 text-right">Reproducciones por reel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($co['cuentas'] as $r)
                                <tr class="{{ $r['es_rival'] ? 'bg-slate-50' : '' }}">
                                    <td class="py-2 pr-4 font-semibold text-slate-900">
                                        {{ $r['cuenta'] }}
                                        @if ($r['es_rival'])
                                            <span class="text-xs font-normal text-slate-500">{{ '@'.ltrim($r['usuario'], '@') }}</span>
                                            <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Rival</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $r['seguidores'] !== null ? number_format($r['seguidores']) : '—' }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ number_format($r['por_semana'], 1, ',', '.') }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums font-bold">{{ $fmtEng($r['engagement']) }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ number_format($r['interacciones']) }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $r['reproducciones'] !== null ? number_format($r['reproducciones']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Formato, día, hora y tema --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    @foreach ($titulos as $dim => $titulo)
                        @php
                            $filasDim = $co[$dim];
                            $maxEng = max(0.01, ...array_values(array_map(fn ($g) => max($g['candidato']['engagement'] ?? 0, $g['rivales']['engagement'] ?? 0), $filasDim ?: [[]])));
                        @endphp
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="text-sm font-semibold text-slate-700 mb-2">{{ $titulo }}</div>
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-[11px] uppercase tracking-wider text-slate-400">
                                    <tr>
                                        <th class="py-1 pr-2"></th>
                                        <th class="py-1 px-2">Candidato</th>
                                        @if ($co['hay_rivales'])
                                            <th class="py-1 px-2">Rivales</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach ($filasDim as $clave => $g)
                                        <tr class="{{ $co['mejor'][$clavesMejor[$dim]] === $clave ? 'font-semibold' : '' }}">
                                            <td class="py-1.5 pr-2 whitespace-nowrap">{{ $etiquetas[$dim][$clave] ?? $clave }}</td>
                                            @foreach ($co['hay_rivales'] ? ['candidato', 'rivales'] : ['candidato'] as $quien)
                                                @php
                                                    $x = $g[$quien] ?? null;
                                                @endphp
                                                <td class="py-1.5 px-2 w-2/5">
                                                    @if ($x)
                                                        <div class="flex items-center gap-2">
                                                            <div class="h-2 flex-1 rounded-full bg-slate-100">
                                                                <div class="h-2 rounded-full {{ $quien === 'candidato' ? 'bg-indigo-500' : 'bg-amber-400' }}"
                                                                    style="width: {{ round(($x['engagement'] ?? 0) * 100 / $maxEng) }}%"></div>
                                                            </div>
                                                            <span class="tabular-nums text-xs w-10 text-right">{{ $fmtEng($x['engagement']) }}</span>
                                                        </div>
                                                        <div class="text-[11px] text-slate-400">{{ $x['n'] }} {{ $x['n'] === 1 ? 'publicación' : 'publicaciones' }}</div>
                                                    @else
                                                        <span class="text-xs text-slate-300">—</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
                @if ($co['sin_clasificar'])
                    <div class="text-xs text-slate-500">
                        {{ number_format($co['sin_clasificar']) }} publicaciones aún sin tema: la IA las clasifica en la sincronización diaria
                        (hace falta la API key de Anthropic en Integraciones).
                    </div>
                @endif

                {{-- Mejores publicaciones de la semana --}}
                <div>
                    <div class="text-sm font-semibold text-slate-700 mb-2">Mejores publicaciones de los últimos 7 días</div>
                    @if (empty($co['top']))
                        <div class="text-sm text-slate-500">Nadie publicó en los últimos 7 días.</div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                            @foreach ($co['top'] as $t)
                                <div class="rounded-xl border p-4 space-y-2 {{ $t['es_rival'] ? 'border-amber-200 bg-amber-50/40' : 'border-slate-200' }}">
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="font-semibold text-slate-900">
                                            {{ $t['cuenta'] }}
                                            @if ($t['es_rival'])
                                                <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Rival</span>
                                            @endif
                                        </span>
                                        <span class="text-slate-500">{{ \Illuminate\Support\Carbon::parse($t['fecha'])->locale('es')->isoFormat('ddd D MMM, h:mm a') }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-1 text-[11px]">
                                        @if ($t['tipo'])
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700">{{ $t['tipo'] }}</span>
                                        @endif
                                        @if ($t['tema'])
                                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-700">{{ $t['tema'] }}</span>
                                        @endif
                                    </div>
                                    @if ($t['texto'])
                                        <div class="text-sm text-slate-700">{{ $t['texto'] }}</div>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-600 tabular-nums">
                                        <span title="Rendimiento: interacciones por cada 100 seguidores"><strong class="text-slate-900">{{ $fmtEng($t['engagement']) }}</strong> rend.</span>
                                        <span>❤ {{ $t['me_gusta'] !== null ? number_format($t['me_gusta']) : 'oculto' }}</span>
                                        <span>💬 {{ number_format((int) $t['comentarios']) }}</span>
                                        @if ($t['reproducciones'] !== null)
                                            <span>▶ {{ number_format($t['reproducciones']) }}</span>
                                        @endif
                                        @if ($t['enlace'])
                                            <a href="{{ $t['enlace'] }}" target="_blank" rel="noopener" class="ml-auto font-semibold text-indigo-700 hover:underline">Ver en Instagram</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
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
                // Rivales: solo si hay alguno registrado; si no, la gráfica queda solo con el candidato
                const rivales = {{ Js::from(collect($rivales)->where('es_rival', true)->values()) }};
                const el = document.getElementById('graficaRedes');
                if (!el || typeof Chart === 'undefined' || !cuentas.length) return;

                const etiquetas = cuentas[0].semanas.map(s => {
                    const [a, m, d] = s.semana.split('-');
                    return `${d}/${m}`;
                });

                // Colores fijos por red para el candidato; los rivales van punteados
                const colores = { facebook: '#1877F2', instagram: '#C13584' };
                const coloresRival = ['#f59e0b', '#64748b', '#0d9488', '#7c3aed', '#dc2626', '#65a30d'];

                const series = [
                    ...cuentas.map(c => ({ c, color: colores[c.red] || '#334155', rival: false })),
                    ...rivales.map((c, i) => ({ c, color: coloresRival[i % coloresRival.length], rival: true })),
                ];

                // Crecimiento: % frente a la primera semana con datos de cada cuenta
                const valores = (c, modo) => {
                    const datos = c.semanas.map(s => s.seguidores);
                    if (modo !== 'crecimiento') return datos;
                    const base = datos.find(v => v !== null && v > 0);
                    return datos.map(v => (v === null || !base) ? null : Math.round((v - base) / base * 1000) / 10);
                };

                const grafica = new Chart(el, {
                    type: 'line',
                    data: {
                        labels: etiquetas,
                        datasets: series.map(({ c, color, rival }) => ({
                            label: rival ? `Rival · ${c.nombre || c.usuario}` : `${c.red} · ${c.usuario}`,
                            data: valores(c, 'seguidores'),
                            borderColor: color,
                            backgroundColor: color,
                            borderDash: rival ? [6, 4] : [],
                            borderWidth: rival ? 2 : 3,
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

                document.querySelectorAll('.modo-redes').forEach(boton => boton.addEventListener('click', () => {
                    const modo = boton.dataset.modo;
                    grafica.data.datasets.forEach((d, i) => { d.data = valores(series[i].c, modo); });
                    grafica.options.scales.y.ticks.callback = modo === 'crecimiento'
                        ? v => `${v > 0 ? '+' : ''}${v} %`
                        : v => Number(v).toLocaleString('es-CO');
                    grafica.update();
                    document.querySelectorAll('.modo-redes').forEach(b => {
                        const activo = b === boton;
                        b.classList.toggle('bg-indigo-600', activo);
                        b.classList.toggle('text-white', activo);
                        b.classList.toggle('text-slate-600', !activo);
                    });
                }));
            })();
        </script>
    @endpush
@endif
