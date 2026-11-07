{{-- Secciones 9–11 del Análisis electoral (EstrategiaElectoral). Se incluye desde analisis.blade.php. --}}
@php
    use App\Services\Historico\EstrategiaElectoral;
    use App\Services\Historico\PlanCampania;

    $cargoLabel = collect($cargos)->firstWhere('value', $cargo)?->label();
    $tarjeta = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $signo = fn ($v) => $v === null ? '—' : (($v > 0 ? '+' : '').$v);
@endphp

{{-- ===== 9. Simulador de alianzas ===== --}}
@if ($seccion === 'alianzas' && $plan)
    @php($a = $plan['alianza'])

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-2 {{ $tarjeta }} p-4">
            <div class="text-lg font-extrabold text-slate-900">Arma la alianza</div>
            <div class="text-xs text-slate-500 mb-3">{{ $cargoLabel }} {{ $anio }} · hasta 8 partidos. El primero es el principal.</div>

            <label class="block text-xs font-semibold text-slate-700">Votos de los socios que se pasan a la alianza: {{ $transferencia }}%
                <input type="range" min="0" max="100" step="5" wire:model.live.debounce.300ms="transferencia" class="w-full mt-1 accent-indigo-600">
            </label>
            <div class="text-[11px] text-slate-500 mb-3">En una alianza no todos los votos se pasan: 70–85 % es lo usual.</div>

            <input type="search" wire:model.live.debounce.300ms="buscarPartido" placeholder="Buscar partido…" class="{{ $select }} mb-2">
            <div class="max-h-96 overflow-y-auto divide-y border border-slate-100 rounded-xl">
                @foreach ($plan['opciones'] as $o)
                    @php($pos = array_search($o['clave'], $aliados, true))
                    <div wire:key="op-{{ md5($o['clave']) }}" class="flex items-center gap-2 px-3 py-2 text-sm">
                        <input type="checkbox" @checked($pos !== false) wire:click="alternarAliado({{ \Illuminate\Support\Js::from($o['clave']) }})" class="rounded border-slate-300 text-indigo-600">
                        <span class="flex-1 text-slate-800">
                            {{ $o['nombre'] }}
                            @if ($o['propio'])
                                <span class="text-[10px] font-semibold uppercase text-indigo-600">tu partido</span>
                            @endif
                        </span>
                        <span class="tabular-nums text-xs text-slate-500">{{ $num($o['votos']) }}</span>
                        @if ($pos === 0)
                            <span class="text-[10px] font-semibold uppercase text-indigo-600">principal</span>
                        @elseif ($pos !== false)
                            <button type="button" wire:click="principal({{ \Illuminate\Support\Js::from($o['clave']) }})" class="text-[10px] uppercase text-slate-400 hover:text-indigo-600">hacer principal</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="lg:col-span-3 space-y-6">
            @if (! $a)
                <div class="{{ $tarjeta }} p-8 text-center text-sm text-slate-500">Marca al menos un partido para simular.</div>
            @else
                @php($r = $a['resumen'])
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="{{ $tarjeta }} p-4">
                        <div class="text-xs uppercase tracking-wider text-slate-400">La alianza en el departamento</div>
                        <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($r['votos']) }}</div>
                        <div class="text-sm text-slate-600">{{ $r['porcentaje'] }}% de los votos válidos</div>
                    </div>
                    <div class="{{ $tarjeta }} p-4">
                        <div class="text-xs uppercase tracking-wider text-slate-400">Mejor rival</div>
                        <div class="mt-1 text-lg font-extrabold text-slate-900 leading-tight">{{ $r['rival_nombre'] ?? '—' }}</div>
                        <div class="text-sm text-slate-600">{{ $num($r['rival_votos']) }} votos</div>
                    </div>
                    @if ($a['corporacion'])
                        <div class="{{ $tarjeta }} p-4">
                            <div class="text-xs uppercase tracking-wider text-slate-400">Curules (de {{ $a['curules']['total'] ?? '—' }})</div>
                            <div class="mt-1 text-2xl font-extrabold {{ $a['curules']['juntos'] > $a['curules']['separados'] ? 'text-green-700' : ($a['curules']['juntos'] < $a['curules']['separados'] ? 'text-red-600' : 'text-slate-900') }}">
                                {{ $a['curules']['separados'] }} → {{ $a['curules']['juntos'] }}
                            </div>
                            <div class="text-sm text-slate-600">separados → juntos{{ $a['municipal'] ? ', sumando todos los concejos' : '' }}</div>
                        </div>
                    @else
                        <div class="{{ $tarjeta }} p-4">
                            <div class="text-xs uppercase tracking-wider text-slate-400">{{ $a['municipal'] ? 'Alcaldías' : 'Resultado' }}</div>
                            @if ($a['municipal'])
                                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $a['ganados_antes'] }} → {{ $a['ganados_juntos'] }}</div>
                                <div class="text-sm text-slate-600">municipios ganados: por separado → juntos</div>
                            @else
                                <div class="mt-1 text-2xl font-extrabold {{ $r['gana'] ? 'text-green-700' : 'text-red-600' }}">{{ $r['gana'] ? 'Gana' : 'Pierde' }}</div>
                                <div class="text-sm text-slate-600">{{ $r['gana'] ? 'por '.$num($r['votos'] - $r['rival_votos']).' votos' : 'le faltan '.$num($r['falta']).' votos' }}</div>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($a['corporacion'] && ! $a['municipal'] && $cargo !== 'camara')
                    <div class="{{ $tarjeta }} p-3 flex flex-wrap items-end gap-3">
                        <label class="text-xs font-semibold text-slate-700">Curules
                            <input type="number" min="1" max="120" wire:model.blur="curulesManual" placeholder="{{ $a['curules']['total'] }}" class="{{ $select }} w-28">
                        </label>
                        <div class="text-xs text-slate-500">Según los elegidos de {{ $a['curules']['anio'] ?? '—' }}. Cámbialas si ya no son esas.</div>
                    </div>
                @endif

                <div class="{{ $tarjeta }} p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                        <div class="text-lg font-extrabold text-slate-900">Dónde gana la alianza</div>
                        <div class="text-xs text-slate-500">Verde = gana, rojo = pierde · más intenso = más margen</div>
                    </div>
                    <div id="analisis-mapa" class="h-[400px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
                </div>
            @endif
        </div>
    </div>

    @if ($a)
        <div class="{{ $tarjeta }} p-6">
            <div class="text-lg font-extrabold text-slate-900">Municipio por municipio</div>
            <div class="text-xs text-slate-500 mb-4">
                {{ $a['ganados_antes'] }} municipios los ganaba ya algún partido de la alianza; juntos ganarían {{ $a['ganados_juntos'] }}.
                Es una suma de votos de {{ $anio }}: no cuenta a quién atraería o espantaría la alianza.
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Municipio</th>
                            <th class="py-2 px-3 text-right">Alianza</th>
                            <th class="py-2 px-3">Mejor rival</th>
                            <th class="py-2 px-3 text-right">Margen</th>
                            <th class="py-2 px-3">Resultado</th>
                            @if ($a['corporacion'] && $a['municipal'])
                                <th class="py-2 px-3 text-right">Curules</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($a['municipios'] as $r)
                            <tr wire:key="al-{{ $r['id'] }}">
                                <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['votos']) }} <span class="text-xs text-slate-400">{{ $r['porcentaje'] }}%</span></td>
                                <td class="py-2 px-3 text-xs text-slate-600">{{ $r['rival_nombre'] ?? '—' }} · {{ $num($r['rival_votos']) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums {{ $r['margen'] >= 0 ? 'text-green-700' : 'text-red-600' }}">{{ $signo($r['margen']) }} pts</td>
                                <td class="py-2 px-3 text-xs">
                                    @if ($r['gana'])
                                        <span class="font-semibold text-green-700">{{ $r['ganaba'] ? 'Ya ganaba' : 'Gana por la alianza' }}</span>
                                    @else
                                        <span class="text-red-600">Faltan {{ $num($r['falta']) }}</span>
                                    @endif
                                </td>
                                @if ($a['corporacion'] && $a['municipal'])
                                    <td class="py-2 px-3 text-right tabular-nums">{{ isset($r['curules_separados']) ? $r['curules_separados'].' → '.$r['curules_juntos'] : '—' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif

{{-- ===== 10. Opinión y maquinaria ===== --}}
@if ($seccion === 'maquinaria' && $plan)
    @php($m = $plan['maquinaria'])

    <div class="{{ $tarjeta }} p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
        <label class="text-xs font-semibold text-slate-700">Partido
            <select wire:model.live="partidoMaq" class="{{ $select }}">
                @foreach ($plan['partidos'] as $clave => $p)
                    <option value="{{ $clave }}">{{ $p['nombre'] }}</option>
                @endforeach
            </select>
        </label>
        <div class="text-xs text-slate-500">
            Solo partidos que fueron al Senado y a concejo o asamblea. En el Senado se vota por la marca en todo el país (opinión);
            en concejo y asamblea pesan los candidatos y líderes del municipio (estructura).
        </div>
    </div>

    @if ($m)
        @php($dep = $m['departamento'])
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="{{ $tarjeta }} p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">Senado {{ $m['anios']['senado'] ?? '' }}</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $dep['opinion'] ?? '—' }}%</div>
                <div class="text-sm text-slate-600">voto de opinión</div>
            </div>
            @foreach (EstrategiaElectoral::ESTRUCTURA as $c)
                <div class="{{ $tarjeta }} p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">{{ ucfirst($c) }} {{ $m['anios'][$c] ?? '' }}</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $dep['por_cargo'][$c] ?? '—' }}%</div>
                    <div class="text-sm text-slate-600">estructura local</div>
                </div>
            @endforeach
            <div class="{{ $tarjeta }} p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">En {{ $departamento->nombre }}</div>
                <div class="mt-1 text-lg font-extrabold leading-tight" style="color: {{ EstrategiaElectoral::TIPOS[$dep['tipo']][2] }}">{{ EstrategiaElectoral::TIPOS[$dep['tipo']][0] }}</div>
                <div class="text-xs text-slate-600">{{ EstrategiaElectoral::TIPOS[$dep['tipo']][1] }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <div class="lg:col-span-3 {{ $tarjeta }} p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                    <div class="text-lg font-extrabold text-slate-900">{{ $m['nombre'] }}</div>
                    <div class="flex flex-wrap gap-3 text-xs text-slate-500">
                        @foreach (array_slice(EstrategiaElectoral::TIPOS, 0, 3) as $t)
                            <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded" style="background: {{ $t[2] }}"></span>{{ $t[0] }}</span>
                        @endforeach
                    </div>
                </div>
                <div id="analisis-mapa" class="h-[440px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
            </div>
            <div class="lg:col-span-2 {{ $tarjeta }} p-4">
                <div class="text-lg font-extrabold text-slate-900 mb-1">Qué significa</div>
                <div class="text-sm text-slate-600 space-y-3">
                    @foreach (array_slice(EstrategiaElectoral::TIPOS, 0, 3, true) as $clave => $t)
                        <div>
                            <div class="font-semibold" style="color: {{ $t[2] }}">{{ $t[0] }} · {{ $m['conteo'][$clave] ?? 0 }} municipios</div>
                            <div>{{ $t[1] }}</div>
                        </div>
                    @endforeach
                    <p class="text-slate-500">
                        Para un candidato: donde su partido es <b>opinión</b>, le sirven los medios y las redes; donde es <b>estructura</b>,
                        tiene que sumar a los líderes locales (abajo).
                    </p>
                </div>
            </div>
        </div>

        <div class="{{ $tarjeta }} p-6">
            <div class="text-lg font-extrabold text-slate-900">Municipio por municipio</div>
            <div class="text-xs text-slate-500 mb-4">Porcentaje del partido en cada elección. Local = promedio de concejo y asamblea.</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Municipio</th>
                            <th class="py-2 px-3 text-right">Senado</th>
                            @foreach (EstrategiaElectoral::ESTRUCTURA as $c)
                                <th class="py-2 px-3 text-right">{{ ucfirst($c) }}</th>
                            @endforeach
                            <th class="py-2 px-3 text-right">Local − Senado</th>
                            <th class="py-2 px-3">Carácter</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($m['municipios'] as $r)
                            <tr wire:key="mq-{{ $r['id'] }}">
                                <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r['opinion'] !== null ? $r['opinion'].'%' : '—' }}</td>
                                @foreach (EstrategiaElectoral::ESTRUCTURA as $c)
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $r['por_cargo'][$c] !== null ? $r['por_cargo'][$c].'%' : '—' }}</td>
                                @endforeach
                                <td class="py-2 px-3 text-right tabular-nums font-bold">{{ $signo($r['diferencia']) }}</td>
                                <td class="py-2 px-3 text-xs font-semibold" style="color: {{ EstrategiaElectoral::TIPOS[$r['tipo']][2] }}">{{ EstrategiaElectoral::TIPOS[$r['tipo']][0] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @php($l = $plan['lideres'])
    @php($municipal = PlanCampania::esMunicipal($cargo))
    <div class="{{ $tarjeta }} p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <div>
                <div class="text-lg font-extrabold text-slate-900">Líderes: los candidatos más votados · {{ $cargoLabel }} {{ $l['anio'] }}</div>
                <div class="text-xs text-slate-500">
                    @if ($municipal)
                        Cada uno compitió en un solo municipio: el % es lo que sacó ahí.
                    @else
                        Concentración = % de sus votos que salió de su municipio más fuerte. Si es alta o necesita pocos municipios para el 80 %,
                        es un líder regional con estructura; si están repartidos, es voto de opinión.
                    @endif
                    Cambia el cargo arriba para ver otros.
                </div>
            </div>
            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                <input type="checkbox" wire:model.live="soloPartido" class="rounded border-slate-300 text-indigo-600">
                Solo {{ $m['nombre'] ?? 'el partido elegido' }}
            </label>
        </div>
        @if (! $l['candidatos'])
            <div class="mt-4 text-sm text-slate-500">No hay votos por candidato para este cargo (en algunos años solo viene el voto por la lista).</div>
        @else
            <div class="overflow-x-auto mt-4">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">#</th>
                            <th class="py-2 pr-4">Candidato</th>
                            <th class="py-2 px-3">Partido</th>
                            <th class="py-2 px-3 text-right">Votos</th>
                            <th class="py-2 px-3">{{ $municipal ? 'Municipio' : 'Municipio más fuerte' }}</th>
                            <th class="py-2 px-3 text-right">% ahí</th>
                            @unless ($municipal)
                                <th class="py-2 px-3 text-right">Concentración</th>
                                <th class="py-2 px-3 text-right">Municipios para el 80 %</th>
                                <th class="py-2 px-3">Dónde más</th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($l['candidatos'] as $c)
                            <tr wire:key="ld-{{ md5($c['clave'].$c['candidato']) }}">
                                <td class="py-2 pr-4 text-slate-400 tabular-nums">{{ $loop->iteration }}</td>
                                <td class="py-2 pr-4 font-semibold text-slate-900">{{ $c['candidato'] }}</td>
                                <td class="py-2 px-3 text-xs text-slate-600">{{ $c['partido'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums font-bold">{{ $num($c['votos']) }}</td>
                                <td class="py-2 px-3 text-xs">{{ $c['fuerte'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $c['fuerte_pct'] !== null ? $c['fuerte_pct'].'%' : '—' }}</td>
                                @unless ($municipal)
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $c['concentracion'] }}%</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $c['para80'] }} de {{ $c['con_votos'] }}</td>
                                    <td class="py-2 px-3 text-xs text-slate-500">
                                        {{ collect($c['top'])->slice(1, 3)->map(fn ($x, $mun) => $mun.' '.$x['porcentaje'].'%')->implode(' · ') }}
                                    </td>
                                @endunless
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif

{{-- ===== 11. Testigos electorales ===== --}}
@if ($seccion === 'testigos' && $plan)
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Son señales para decidir dónde poner testigos y mirar con más cuidado, <b>no pruebas de fraude</b>: un municipio pequeño
        o una elección local muy reñida también pueden mover las cifras.
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach (EstrategiaElectoral::NIVELES as $clave => [$nombre, $color])
            <div class="{{ $tarjeta }} p-4">
                <div class="flex items-center gap-2 text-xs uppercase tracking-wider text-slate-400">
                    <span class="inline-block w-3 h-3 rounded" style="background: {{ $color }}"></span>{{ $nombre }}
                </div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $plan['conteo'][$clave] ?? 0 }}</div>
                <div class="text-xs text-slate-600">municipios</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 {{ $tarjeta }} p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <div class="text-lg font-extrabold text-slate-900">{{ $cargoLabel }} {{ $plan['ultimo'] }}</div>
                <div class="text-xs text-slate-500">Rojo = más señales</div>
            </div>
            <div id="analisis-mapa" class="h-[440px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
        </div>
        <div class="lg:col-span-2 {{ $tarjeta }} p-4">
            <div class="text-lg font-extrabold text-slate-900 mb-1">Qué se revisa</div>
            <ul class="text-sm text-slate-600 list-disc pl-5 space-y-1">
                <li><b>Participación muy alta:</b> votó el 85 % o más del potencial.</li>
                @if ($plan['previo'])
                    <li><b>Salto de votos:</b> crecieron 40 puntos más que en el departamento ({{ $signo($plan['crecimiento_depto']) }}% entre {{ $plan['previo'] }} y {{ $plan['ultimo'] }}).</li>
                    <li><b>Subida del ganador:</b> su partido subió 40 puntos o más frente a {{ $plan['previo'] }}.</li>
                @endif
                <li><b>Casi unanimidad:</b> el ganador sacó 80 % o más.</li>
                @if ($plan['mismo_dia'])
                    <li><b>Mismo día:</b> 15 % más o menos votos que {{ collect($plan['mismo_dia'])->map(fn ($c) => \App\Enums\CargoEnum::from($c)->label())->implode(', ') }}, que se votaron en la misma jornada.</li>
                @endif
            </ul>
            <p class="mt-3 text-xs text-slate-500">Nivel: alto con 4 puntos o más, medio con 2 o 3 (la subida del ganador vale 1, las demás 2). El histórico no trae nulos ni tarjetas no marcadas, que también serían señal.</p>
        </div>
    </div>

    <div class="{{ $tarjeta }} p-6">
        <div class="text-lg font-extrabold text-slate-900">Todos los municipios</div>
        <div class="text-xs text-slate-500 mb-4">De más a menos señales</div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="py-2 pr-4">Municipio</th>
                        <th class="py-2 px-3">Nivel</th>
                        <th class="py-2 px-3 text-right">Votos</th>
                        <th class="py-2 px-3 text-right">Participación</th>
                        @if ($plan['previo'])
                            <th class="py-2 px-3 text-right">Votos vs {{ $plan['previo'] }}</th>
                        @endif
                        <th class="py-2 px-3 text-right">Ganador</th>
                        @if ($plan['mismo_dia'])
                            <th class="py-2 px-3 text-right">vs mismo día</th>
                        @endif
                        <th class="py-2 px-3">Señales</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($plan['municipios'] as $r)
                        <tr wire:key="ts-{{ $r['id'] }}">
                            <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                            <td class="py-2 px-3 text-xs font-semibold whitespace-nowrap" style="color: {{ EstrategiaElectoral::NIVELES[$r['nivel']][1] }}">{{ EstrategiaElectoral::NIVELES[$r['nivel']][0] }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['votos']) }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ $r['participacion'] !== null ? $r['participacion'].'%' : '—' }}</td>
                            @if ($plan['previo'])
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r['crecio'] !== null ? $signo($r['crecio']).'%' : '—' }}</td>
                            @endif
                            <td class="py-2 px-3 text-right tabular-nums">{{ $r['pct_ganador'] }}%</td>
                            @if ($plan['mismo_dia'])
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r['diferencia_mismo_dia'] !== null ? $signo($r['diferencia_mismo_dia']).'%' : '—' }}</td>
                            @endif
                            <td class="py-2 px-3 text-xs text-slate-600">{{ implode(' · ', $r['senales']) ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
