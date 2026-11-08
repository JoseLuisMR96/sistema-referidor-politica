@php
    use App\Services\Historico\PlanCampania;

    $tarjeta = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $num = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', '.');
    $puedeAgendar = auth()->user()->can('eventos.crear');
@endphp

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Plan de giras</h2>
            <div class="text-sm text-gray-500">A dónde ir pronto: prioridad electoral contra dónde ya se ha estado.</div>
        </div>
        <a href="{{ route('eventos.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">← Eventos</a>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (! $tenant)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Elige un candidato en el menú lateral.</div>
        @elseif (in_array($p['modo'], ['nacional', 'incompleto', 'sin_historico', 'sin_puestos'], true))
            <div class="{{ $tarjeta }} p-8 text-center text-sm text-slate-500">
                @switch($p['modo'])
                    @case('nacional') Para cargos nacionales todavía no hay plan de giras: se prioriza por municipio dentro de un departamento. @break
                    @case('incompleto') Al candidato le falta el cargo o el lugar: complétalo en <a href="{{ route('tenants.index') }}" class="underline font-semibold">Candidatos</a>. @break
                    @case('sin_historico') No hay resultados electorales cargados para el departamento del candidato. @break
                    @case('sin_puestos') No hay resultados por puesto de votación para {{ $tenant->municipio?->nombre }}: se cargan con <code>historico:importar-puestos</code>. @break
                @endswitch
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- ===== Sugerencias ===== --}}
                <div class="lg:col-span-2 space-y-4">
                    @if ($p['modo'] === 'municipios')
                        <div class="{{ $tarjeta }} p-4 flex flex-wrap items-end justify-between gap-3">
                            <label class="text-[11px] font-semibold text-slate-500">Enfoque de la prioridad
                                <select wire:model.live="enfoque" class="mt-1 w-56 rounded-xl border-slate-200 text-sm">
                                    @foreach (PlanCampania::ENFOQUES as $clave => [$nombre])
                                        <option value="{{ $clave }}">{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="text-xs text-slate-500 max-w-md">
                                Prioridad de la sección 8 del Análisis ({{ \App\Enums\CargoEnum::from($p['cargo'])->label() }}),
                                más urgente cuanto más tiempo sin estar ahí. Lo que ya tiene un evento planeado baja a la mitad.
                                {{ $p['sin_visita'] }} municipios nunca se han visitado.
                            </div>
                        </div>

                        <div class="{{ $tarjeta }} divide-y">
                            @foreach (array_slice($p['municipios'], 0, 15) as $i => $m)
                                @php($accion = PlanCampania::ACCIONES[$m['accion']])
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-4" wire:key="pg-{{ $m['id'] }}">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-extrabold text-indigo-700">{{ $i + 1 }}</div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-bold text-slate-900">{{ $m['municipio'] }}</span>
                                            <span class="text-xs font-semibold" style="color: {{ $accion[2] }}">{{ $accion[0] }}</span>
                                            @if ($m['planeado'])
                                                <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">Planeado {{ $m['planeado']->fecha->format('d/m') }}</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ $m['eventos'] ? $m['eventos'].' evento(s), el último hace '.$m['dias_sin_visita'].' días' : 'Sin eventos' }}
                                            · candidato: {{ $m['dias_sin_candidato'] !== null ? 'hace '.$m['dias_sin_candidato'].' días' : 'nunca' }}
                                            · {{ $num($m['potencial']) }} cédulas
                                        </div>
                                        @if ($m['motivos'])
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach ($m['motivos'] as $motivo)
                                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800">{{ $motivo }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 sm:shrink-0">
                                        <div class="text-right">
                                            <div class="text-lg font-extrabold text-slate-900 tabular-nums">{{ $m['urgencia'] }}</div>
                                            <div class="text-[10px] uppercase text-slate-400">urgencia</div>
                                        </div>
                                        @if ($puedeAgendar)
                                            <a href="{{ route('eventos.create', ['municipio' => $m['id'], 'estado' => 'planeado']) }}"
                                                class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Agendar</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="{{ $tarjeta }} p-4 text-xs text-slate-500">
                            {{ $tenant->cargo->label() }}: la campaña es en {{ $p['municipio'] }}, así que se prioriza por <b>puesto de votación</b>
                            ({{ \App\Enums\CargoEnum::from($p['cargo'])->label() }} {{ $p['anio'] }}{{ $p['partido_propio'] ? ', '.$p['partido'] : '' }}):
                            los puestos más grandes y donde {{ $p['partido_propio'] ? 'el partido' : 'el más votado' }} quedó más lejos de su promedio.
                            Los eventos no se cruzan por puesto: anota el puesto en el sitio del evento.
                        </div>
                        <div class="{{ $tarjeta }} divide-y">
                            @foreach (array_slice($p['puestos'], 0, 15) as $i => $pu)
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-4" wire:key="pp-{{ $pu['id'] }}">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-extrabold text-indigo-700">{{ $i + 1 }}</div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-slate-900">{{ $pu['nombre'] }}</div>
                                        <div class="text-xs text-slate-500">
                                            Zona {{ $pu['zona'] }}{{ $pu['comuna'] ? ' · '.$pu['comuna'] : '' }} · {{ $num($pu['votos']) }} votos · {{ $num($pu['mesas']) }} mesas
                                            · partido {{ $pu['pct_partido'] ?? '—' }}%{{ $pu['brecha'] ? ' · le faltan '.$num($pu['brecha']).' para su promedio' : '' }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 sm:shrink-0">
                                        <div class="text-right">
                                            <div class="text-lg font-extrabold text-slate-900 tabular-nums">{{ $pu['urgencia'] }}</div>
                                            <div class="text-[10px] uppercase text-slate-400">urgencia</div>
                                        </div>
                                        @if ($puedeAgendar)
                                            <a href="{{ route('eventos.create', ['municipio' => $p['municipio_id'], 'estado' => 'planeado', 'lugar' => $pu['nombre']]) }}"
                                                class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Agendar</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ===== Agenda ===== --}}
                <div class="{{ $tarjeta }} p-4 h-fit">
                    <div class="text-lg font-extrabold text-slate-900">Próximos eventos planeados</div>
                    <div class="text-xs text-slate-500 mb-3">Lo que ya está agendado</div>
                    <div class="divide-y">
                        @forelse ($p['proximos'] as $e)
                            <a href="{{ route('eventos.show', $e->id) }}" class="block py-2 hover:bg-slate-50" wire:key="px-{{ $e->id }}">
                                <div class="text-xs font-semibold text-indigo-700">{{ $e->fecha->locale('es')->isoFormat('ddd D [de] MMM, h:mm a') }}</div>
                                <div class="text-sm font-semibold text-slate-900">{{ $e->titulo }}</div>
                            </a>
                        @empty
                            <div class="py-4 text-sm text-slate-500">No hay eventos planeados. Usa "Agendar" en las sugerencias.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
