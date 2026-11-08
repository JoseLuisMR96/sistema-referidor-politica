@php
    use App\Services\Territorio\RetornoEventos;

    $pesos = fn ($v) => $v === null ? '—' : '$ '.number_format((float) $v, 0, ',', '.');
    $num = fn ($v, $d = 0) => number_format((float) $v, $d, ',', '.');
    $tarjeta = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
@endphp

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Retorno de los eventos</h2>
            <div class="text-sm text-gray-500">Registros nuevos en el municipio después de cada evento, y cuánto costó cada uno.</div>
        </div>
        <a href="{{ route('eventos.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">← Eventos</a>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (! $tenant)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Elige un candidato en el menú lateral.</div>
        @else
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es una <b>coincidencia en el tiempo</b>, no una prueba: compara los registros de los {{ $r['ventana'] }} días desde cada evento
                con el ritmo de los {{ $r['base_dias'] }} días anteriores en ese municipio. Si dos eventos cayeron en la misma ventana, se reparten.
                No cuenta los importados del padrón.
            </div>

            <div class="{{ $tarjeta }} p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="text-[11px] font-semibold text-slate-500">Ventana después del evento
                    <select wire:model.live="ventana" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        @foreach (RetornoEventos::VENTANAS as $v)
                            <option value="{{ $v }}">{{ $v }} días</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-[11px] font-semibold text-slate-500">Eventos desde
                    <input type="date" wire:model.live="desde" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                </label>
                <label class="text-[11px] font-semibold text-slate-500">Hasta
                    <input type="date" wire:model.live="hasta" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                </label>
            </div>

            @if (! $r['eventos'])
                <div class="{{ $tarjeta }} p-8 text-center text-sm text-slate-500">
                    No hay eventos realizados con municipio en este periodo. Registra los eventos en
                    <a href="{{ route('eventos.index') }}" class="underline font-semibold">Eventos de campaña</a>.
                </div>
            @else
                @php($t = $r['totales'])
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="{{ $tarjeta }} p-4">
                        <div class="text-xs uppercase tracking-wider text-slate-400">Eventos realizados</div>
                        <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($t['eventos']) }}</div>
                    </div>
                    <div class="{{ $tarjeta }} p-4">
                        <div class="text-xs uppercase tracking-wider text-slate-400">Registros en las ventanas</div>
                        <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($t['despues']) }}</div>
                    </div>
                    <div class="{{ $tarjeta }} p-4">
                        <div class="text-xs uppercase tracking-wider text-slate-400">Por encima de lo esperado</div>
                        <div class="mt-1 text-2xl font-extrabold text-emerald-700">{{ $num($t['atribuibles']) }}</div>
                        <div class="text-xs text-slate-500">registros atribuibles a los eventos</div>
                    </div>
                    <div class="{{ $tarjeta }} p-4">
                        <div class="text-xs uppercase tracking-wider text-slate-400">Costo por registro</div>
                        <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $t['atribuibles'] >= 1 && $t['costo'] > 0 ? $pesos($t['costo'] / $t['atribuibles']) : '—' }}</div>
                        <div class="text-xs text-slate-500">{{ $pesos($t['costo']) }} en eventos</div>
                    </div>
                </div>

                @php($bloques = ['por_quien' => 'Quién fue', 'por_persona' => 'Por persona del equipo', 'por_tipo' => 'Por tipo de evento', 'por_municipio' => 'Por municipio'])
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @foreach ($bloques as $clave => $titulo)
                        @continue(! $r[$clave])
                        @php($maximo = max(1, collect($r[$clave])->max('atribuibles')))
                        <div class="{{ $tarjeta }} p-4">
                            <div class="text-lg font-extrabold text-slate-900 mb-3">{{ $titulo }}</div>
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                                    <tr>
                                        <th class="py-1 pr-3"></th>
                                        <th class="py-1 px-2 text-right">Eventos</th>
                                        <th class="py-1 px-2 w-1/3">Registros atribuibles</th>
                                        <th class="py-1 px-2 text-right">Por evento</th>
                                        <th class="py-1 pl-2 text-right">Costo / registro</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach ($r[$clave] as $g)
                                        <tr wire:key="{{ $clave }}-{{ md5((string) $g['clave']) }}">
                                            <td class="py-2 pr-3 font-semibold text-slate-900">{{ $g['nombre'] }}</td>
                                            <td class="py-2 px-2 text-right tabular-nums">{{ $g['eventos'] }}</td>
                                            <td class="py-2 px-2">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-2 flex-1 rounded bg-slate-100"><div class="h-2 rounded bg-emerald-600" style="width: {{ round($g['atribuibles'] * 100 / $maximo) }}%"></div></div>
                                                    <span class="w-10 text-right tabular-nums text-xs">{{ $num($g['atribuibles'], 1) }}</span>
                                                </div>
                                            </td>
                                            <td class="py-2 px-2 text-right tabular-nums">{{ $num($g['por_evento'], 1) }}</td>
                                            <td class="py-2 pl-2 text-right tabular-nums whitespace-nowrap">{{ $pesos($g['costo_registro']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>

                <div class="{{ $tarjeta }} p-4">
                    <div class="text-lg font-extrabold text-slate-900">Evento por evento</div>
                    <div class="text-xs text-slate-500 mb-3">Antes = registros de los {{ $r['base_dias'] }} días previos. Esperado = ese ritmo llevado a la ventana.</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                                <tr>
                                    <th class="py-2 pr-3">Evento</th>
                                    <th class="py-2 px-2">Quién</th>
                                    <th class="py-2 px-2 text-right">Antes</th>
                                    <th class="py-2 px-2 text-right">Después</th>
                                    <th class="py-2 px-2 text-right">Esperado</th>
                                    <th class="py-2 px-2 text-right">Atribuibles</th>
                                    <th class="py-2 px-2 text-right">Costo</th>
                                    <th class="py-2 pl-2 text-right">Costo / registro</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($r['eventos'] as $e)
                                    <tr wire:key="re-{{ $e['id'] }}">
                                        <td class="py-2 pr-3">
                                            <a href="{{ route('eventos.show', $e['id']) }}" class="font-semibold text-slate-900 hover:underline">{{ $e['titulo'] }}</a>
                                            <div class="text-xs text-slate-500">
                                                {{ $e['fecha']->format('d/m/Y') }} · {{ $e['municipio'] }} · {{ $e['tipo'] }}
                                                @if ($e['en_curso'])<span class="text-amber-700">· van {{ $e['dias'] }} días</span>@endif
                                                @if ($e['solapados'] > 1)<span class="text-slate-400">· compartido con {{ $e['solapados'] - 1 }} evento(s)</span>@endif
                                            </div>
                                        </td>
                                        <td class="py-2 px-2 text-xs">{{ $e['candidato'] ? 'Candidato' : ($e['responsable'] ?? 'Equipo') }}</td>
                                        <td class="py-2 px-2 text-right tabular-nums">{{ $e['antes'] }}</td>
                                        <td class="py-2 px-2 text-right tabular-nums">{{ $e['despues'] }}</td>
                                        <td class="py-2 px-2 text-right tabular-nums text-slate-500">{{ $num($e['esperado'], 1) }}</td>
                                        <td class="py-2 px-2 text-right tabular-nums font-bold {{ $e['atribuibles'] > 0 ? 'text-emerald-700' : 'text-slate-400' }}">{{ $num($e['atribuibles'], 1) }}</td>
                                        <td class="py-2 px-2 text-right tabular-nums whitespace-nowrap">{{ $e['costo'] > 0 ? $pesos($e['costo']) : '—' }}</td>
                                        <td class="py-2 pl-2 text-right tabular-nums whitespace-nowrap">{{ $pesos($e['costo_registro']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
