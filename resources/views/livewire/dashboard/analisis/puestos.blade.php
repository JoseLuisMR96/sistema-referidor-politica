{{-- Sección 12 del Análisis electoral: puestos de votación (AnalisisPuestos). Se incluye desde analisis.blade.php. --}}
@php
    use App\Livewire\Dashboard\Analisis;
    use App\Models\PartidoIdeologia;
    use App\Services\Historico\EstrategiaElectoral;

    $tarjeta = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $signo = fn ($v) => $v === null ? '—' : (($v > 0 ? '+' : '').$v);
@endphp

@if ($seccion === 'puestos' && $plan)
    @if (! $plan['municipios'])
        <div class="{{ $tarjeta }} p-8 text-center text-sm text-slate-500">
            No hay resultados por puesto de votación para {{ $departamento->nombre }}.
            Se cargan con <code>php artisan historico:importar-puestos MMV_2023_NACIONAL.csv --anio=2023 --depto="{{ $departamento->nombre }}"</code>.
        </div>
    @else
        @php($d = $plan['datos'])
        @php($m = $d['municipio'])

        <div class="{{ $tarjeta }} p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <label class="text-xs font-semibold text-slate-700">Municipio
                <select wire:model.live="municipioId" class="{{ $select }}">
                    @foreach ($plan['municipios'] as $id => $mun)
                        <option value="{{ $id }}">{{ $mun['nombre'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-700">Cargo
                <select wire:model.live="cargo" class="{{ $select }}">
                    @foreach ($plan['cargos'] as $c)
                        <option value="{{ $c }}">{{ \App\Enums\CargoEnum::from($c)->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-700">Elección
                <select wire:model.live="anio" class="{{ $select }}">
                    @foreach ($d['anios'] as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-700">Partido o coalición
                <select wire:model.live="partido" class="{{ $select }}">
                    @foreach ($d['partidos'] as $clave => $p)
                        <option value="{{ $clave }}">{{ $p['nombre'] }} — {{ $num($p['votos']) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-700">Ordenar por
                <select wire:model.live="ordenPuestos" class="{{ $select }}">
                    @foreach (Analisis::ORDEN_PUESTOS as $clave => $texto)
                        <option value="{{ $clave }}">{{ $texto }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="{{ $tarjeta }} p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">Puestos</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $m['puestos'] }}</div>
                <div class="text-sm text-slate-600">{{ $num($m['mesas']) }} mesas · {{ $num($m['potencial']) }} cédulas hoy</div>
            </div>
            <div class="{{ $tarjeta }} p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">{{ $d['partido_nombre'] }}</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $m['pct_partido'] ?? '—' }}%</div>
                <div class="text-sm text-slate-600">{{ $num($m['votos_partido']) }} votos · ganó {{ $m['ganados_partido'] }} puestos</div>
            </div>
            <div class="{{ $tarjeta }} p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">Votos {{ $d['anio'] }}</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($m['votos']) }}</div>
                <div class="text-sm text-slate-600">nulos {{ $m['nulos_pct'] }}% · no marcados {{ $m['no_marcados_pct'] }}%</div>
            </div>
            <div class="{{ $tarjeta }} p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">Para testigos</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ ($d['conteo']['alto'] ?? 0) + ($d['conteo']['medio'] ?? 0) }}</div>
                <div class="text-sm text-slate-600">puestos con alguna señal ({{ $d['conteo']['alto'] ?? 0 }} con dos o más)</div>
            </div>
        </div>

        @php($lista = collect($d['puestos']))
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="{{ $tarjeta }} p-4">
                <div class="text-lg font-extrabold text-slate-900">Dónde es más fuerte</div>
                <div class="text-xs text-slate-500 mb-3">Los 10 puestos con mayor porcentaje (de al menos 100 votos)</div>
                <div class="space-y-2">
                    @foreach ($lista->where('validos', '>=', 100)->sortByDesc('pct_partido')->take(10) as $r)
                        <div wire:key="pf-{{ $r['id'] }}">
                            <div class="flex justify-between gap-2 text-sm">
                                <span class="font-semibold text-slate-900 truncate">{{ $r['nombre'] }}</span>
                                <span class="tabular-nums text-slate-700 shrink-0">{{ $r['pct_partido'] }}% · {{ $num($r['votos_partido']) }}</span>
                            </div>
                            <div class="h-2 rounded bg-slate-100">
                                <div class="h-2 rounded bg-indigo-600" style="width: {{ min(100, $r['pct_partido'] ?? 0) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="{{ $tarjeta }} p-4">
                <div class="text-lg font-extrabold text-slate-900">Dónde crecer</div>
                <div class="text-xs text-slate-500 mb-3">
                    Votos que le faltan en cada puesto para llegar a su promedio del municipio ({{ $m['pct_partido'] }}%): puestos grandes donde va flojo.
                </div>
                <div class="space-y-2">
                    @php($maxBrecha = max(1, $lista->max('brecha')))
                    @foreach ($lista->sortByDesc('brecha')->take(10)->filter(fn ($r) => $r['brecha'] > 0) as $r)
                        <div wire:key="pb-{{ $r['id'] }}">
                            <div class="flex justify-between gap-2 text-sm">
                                <span class="font-semibold text-slate-900 truncate">{{ $r['nombre'] }}</span>
                                <span class="tabular-nums text-slate-700 shrink-0">+{{ $num($r['brecha']) }} · hoy {{ $r['pct_partido'] }}%</span>
                            </div>
                            <div class="h-2 rounded bg-slate-100">
                                <div class="h-2 rounded bg-violet-600" style="width: {{ round($r['brecha'] * 100 / $maxBrecha) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="{{ $tarjeta }} p-6">
            <div class="text-lg font-extrabold text-slate-900">Todos los puestos</div>
            <div class="text-xs text-slate-500 mb-4">
                {{ \App\Enums\CargoEnum::from($cargo)->label() }} {{ $d['anio'] }}{{ $d['previo'] ? ', cambio frente a '.$d['previo'] : '' }}.
                Porcentajes sobre los votos válidos; nulos y no marcados, sobre el total.
                @unless ($d['hay_participacion']) La participación por puesto necesita el potencial del mismo año: entre elecciones la gente cambia de puesto. @endunless
                Las señales no son pruebas: marcan dónde poner testigos.
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Puesto</th>
                            <th class="py-2 px-3 text-right">Mesas</th>
                            <th class="py-2 px-3 text-right">Votos</th>
                            @if ($d['hay_participacion'])
                                <th class="py-2 px-3 text-right">Particip.</th>
                            @endif
                            <th class="py-2 px-3">Ganó</th>
                            <th class="py-2 px-3 text-right">Partido</th>
                            @if ($d['previo'])
                                <th class="py-2 px-3 text-right">vs {{ $d['previo'] }}</th>
                            @endif
                            <th class="py-2 px-3 text-right">Crecer</th>
                            <th class="py-2 px-3 text-right">Blanco</th>
                            <th class="py-2 px-3 text-right">Nulos</th>
                            <th class="py-2 px-3 text-right">No marc.</th>
                            <th class="py-2 px-3">Señales</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($d['puestos'] as $r)
                            <tr wire:key="pt-{{ $r['id'] }}">
                                <td class="py-2 pr-4">
                                    <div class="font-semibold text-slate-900">{{ $r['nombre'] }}</div>
                                    <div class="text-[11px] text-slate-400">Zona {{ $r['zona'] }} · puesto {{ $r['codigo'] }}{{ $r['comuna'] ? ' · '.$r['comuna'] : '' }}</div>
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['mesas']) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['votos']) }}</td>
                                @if ($d['hay_participacion'])
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $r['participacion'] !== null ? $r['participacion'].'%' : '—' }}</td>
                                @endif
                                <td class="py-2 px-3 text-xs">
                                    <span class="text-slate-800">{{ $r['ganador_nombre'] }}</span>
                                    @if ($r['bloque'])
                                        <span class="font-semibold" style="color: {{ PartidoIdeologia::BLOQUES[$r['bloque']][1] }}">· {{ PartidoIdeologia::BLOQUES[$r['bloque']][0] }}</span>
                                    @endif
                                    <span class="text-slate-400">{{ $r['pct_ganador'] }}%</span>
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums font-bold {{ $r['gana_partido'] ? 'text-green-700' : '' }}">{{ $r['pct_partido'] !== null ? $r['pct_partido'].'%' : '—' }}</td>
                                @if ($d['previo'])
                                    <td class="py-2 px-3 text-right tabular-nums {{ ($r['cambio'] ?? 0) < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $r['cambio'] !== null ? $signo($r['cambio']) : '—' }}</td>
                                @endif
                                <td class="py-2 px-3 text-right tabular-nums text-violet-700">{{ $r['brecha'] ? '+'.$num($r['brecha']) : '—' }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r['blanco_pct'] }}%</td>
                                <td class="py-2 px-3 text-right tabular-nums {{ isset($r['senales']['nulos']) ? 'text-red-600 font-semibold' : '' }}">{{ $r['nulos_pct'] }}%</td>
                                <td class="py-2 px-3 text-right tabular-nums {{ isset($r['senales']['no_marcados']) ? 'text-red-600 font-semibold' : '' }}">{{ $r['no_marcados_pct'] }}%</td>
                                <td class="py-2 px-3 text-xs" style="color: {{ EstrategiaElectoral::NIVELES[$r['nivel']][1] }}">{{ implode(' · ', $r['senales']) ?: '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
