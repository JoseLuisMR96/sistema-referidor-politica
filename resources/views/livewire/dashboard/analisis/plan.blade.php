{{-- Secciones 5–8 del Análisis electoral (PlanCampania). Se incluye desde analisis.blade.php. --}}
@php
    use App\Models\PartidoIdeologia;
    use App\Services\Historico\PlanCampania;

    $cargoLabel = collect($cargos)->firstWhere('value', $cargo)?->label();
    $tarjeta = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $signo = fn ($v) => $v === null ? '—' : (($v > 0 ? '+' : '').$v);
@endphp

{{-- ===== 5. Meta y avance ===== --}}
@if ($seccion === 'meta' && $plan)
    @if (! $plan['aplica'])
        <div class="{{ $tarjeta }} p-8 text-center text-sm text-slate-500">
            La meta se calcula para gobernación, asamblea, Cámara, alcaldía y concejo.
            Para {{ $cargoLabel }} la elección es nacional o por comunas.
        </div>
    @else
        @php($e = $plan['eleccion'])

        @if ($plan['registros'] === null)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Elige un candidato en el menú para ver el avance de sus registros frente a la meta.
            </div>
        @endif

        @if (! $plan['municipal'])
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="{{ $tarjeta }} p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">{{ $plan['corporacion'] ? 'Para una curul' : 'Para ganar' }} en {{ $plan['ultimo'] }}</div>
                    @if ($plan['corporacion'])
                        <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($e['cifra']) }}</div>
                        <div class="text-sm text-slate-600">
                            cifra repartidora con {{ $e['curules'] ?? '—' }} curules · umbral {{ $num($e['umbral']) }}
                            · {{ $e['con_curul'] ?? '—' }} listas sacaron curul
                        </div>
                    @else
                        <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($e['ganador_votos']) }}</div>
                        <div class="text-sm text-slate-600">{{ $e['ganador_nombre'] }}; el segundo sacó {{ $num($e['segundo_votos']) }}</div>
                    @endif
                </div>
                <div class="{{ $tarjeta }} p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">Votos válidos</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($e['total']) }}</div>
                    <div class="text-sm text-slate-600">
                        @if ($plan['previo'])
                            {{ $signo($e['crecimiento']) }}% frente a {{ $plan['previo'] }}: se proyectan {{ $num($e['proyectado']) }}
                        @else
                            Solo hay una elección: no se proyecta crecimiento
                        @endif
                    </div>
                </div>
                <div class="{{ $tarjeta }} p-4 border-indigo-300">
                    <div class="text-xs uppercase tracking-wider text-indigo-500">Meta {{ $plan['meta_manual'] ? '(fijada a mano)' : 'proyectada' }}</div>
                    <div class="mt-1 text-2xl font-extrabold text-indigo-700">{{ $num($plan['meta']) }}</div>
                    <div class="text-sm text-slate-600">votos en todo {{ $departamento->nombre }}</div>
                </div>
                <div class="{{ $tarjeta }} p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-400">Avance{{ $tenantNombre ? ' de '.$tenantNombre : '' }}</div>
                    @if ($plan['registros'] !== null)
                        <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $plan['avance'] ?? '—' }}%</div>
                        <div class="text-sm text-slate-600">{{ $num($plan['registros']) }} registros con municipio en el departamento</div>
                        <div class="mt-2 h-2 rounded bg-slate-100">
                            <div class="h-2 rounded bg-green-600" style="width: {{ min(100, $plan['avance'] ?? 0) }}%"></div>
                        </div>
                    @else
                        <div class="mt-1 text-lg font-bold text-slate-400">Sin candidato</div>
                    @endif
                </div>
            </div>

            <div class="{{ $tarjeta }} p-4 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <label class="text-xs font-semibold text-slate-700">Meta a mano (votos)
                    <input type="number" min="1" wire:model.blur="metaManual" placeholder="{{ $e['meta'] }}" class="{{ $select }}">
                </label>
                @if ($plan['corporacion'])
                    <label class="text-xs font-semibold text-slate-700">Curules
                        <input type="number" min="1" max="120" wire:model.blur="curulesManual" placeholder="{{ $plan['curules']['departamento'] }}" class="{{ $select }}">
                    </label>
                @endif
                <div class="text-xs text-slate-500 {{ $plan['corporacion'] ? '' : 'sm:col-span-2' }}">
                    Vacío = la calculada.
                    @if ($plan['corporacion'])
                        Curules:
                        {{ $cargo === 'camara' ? 'las de la Cámara por departamento (2018–2026).' : ($plan['curules']['anio'] ? 'según los elegidos de '.$plan['curules']['anio'].' (la Registraduría no los publica desde 2019).' : 'no hay elegidos cargados; escríbelas.') }}
                    @endif
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <div class="lg:col-span-3 {{ $tarjeta }} p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                    <div class="text-lg font-extrabold text-slate-900">{{ $plan['registros'] !== null ? 'Avance por municipio' : 'Meta por municipio' }}</div>
                    <div class="text-xs text-slate-500">
                        {{ $plan['registros'] !== null ? 'Verde más intenso = más avance frente a su meta' : 'Azul más intenso = meta más alta' }}
                    </div>
                </div>
                <div id="analisis-mapa" class="h-[440px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
            </div>
            <div class="lg:col-span-2 {{ $tarjeta }} p-4">
                <div class="text-lg font-extrabold text-slate-900 mb-1">Cómo se calcula</div>
                <div class="text-sm text-slate-600 space-y-2">
                    @if ($plan['corporacion'])
                        <p>La meta es la <b>cifra repartidora</b>: lo que le costó a la última lista ganar una curul en {{ $plan['ultimo'] }}. El umbral es el 50 % del cuociente (30 % con 2 curules).</p>
                        <p>Ojo: es la meta de la <b>lista</b>. Con voto preferente, el candidato además tiene que ser de los más votados de su lista.</p>
                    @else
                        <p>La meta son los votos con los que ganó el ganador de {{ $plan['ultimo'] }}.</p>
                    @endif
                    @if ($plan['previo'])
                        <p>Se proyecta con lo que crecieron los votos entre {{ $plan['previo'] }} y {{ $plan['ultimo'] }} (máximo +50 %).</p>
                    @endif
                    @if ($plan['municipal'])
                        <p>{{ $cargoLabel }} se elige en cada municipio: cada uno tiene su propia meta.</p>
                    @else
                        <p>La meta del departamento se reparte según cuántos votos válidos puso cada municipio en {{ $plan['ultimo'] }}.</p>
                    @endif
                    @if ($plan['registros'] !== null)
                        <p class="text-amber-700">Un registro no es un voto: sirve para medir el ritmo, no para dar la elección por ganada.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="{{ $tarjeta }} p-6">
            <div class="text-lg font-extrabold text-slate-900">Todos los municipios</div>
            <div class="text-xs text-slate-500 mb-4">{{ $cargoLabel }} {{ $plan['ultimo'] }}, de la meta más alta a la más baja</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Municipio</th>
                            <th class="py-2 px-3 text-right">Votos {{ $plan['ultimo'] }}</th>
                            @if ($plan['municipal'])
                                <th class="py-2 px-3">Ganó</th>
                                @if ($plan['corporacion'])
                                    <th class="py-2 px-3 text-right">Curules</th>
                                @endif
                            @endif
                            <th class="py-2 px-3 text-right">Meta</th>
                            @if ($plan['registros'] !== null)
                                <th class="py-2 px-3 text-right">Registros</th>
                                <th class="py-2 px-3 text-right">Faltan</th>
                                <th class="py-2 px-3 w-40">Avance</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($plan['municipios'] as $r)
                            <tr wire:key="meta-{{ $r['id'] }}">
                                <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['votos']) }}</td>
                                @if ($plan['municipal'])
                                    <td class="py-2 px-3 text-slate-600 text-xs">{{ $r['eleccion']['ganador_nombre'] ?? '—' }}</td>
                                    @if ($plan['corporacion'])
                                        <td class="py-2 px-3 text-right tabular-nums">{{ $r['eleccion']['curules'] ?? '—' }}</td>
                                    @endif
                                @endif
                                <td class="py-2 px-3 text-right tabular-nums font-bold">{{ $num($r['meta']) }}</td>
                                @if ($plan['registros'] !== null)
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['registros']) }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['falta']) }}</td>
                                    <td class="py-2 px-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 flex-1 rounded bg-slate-100">
                                                <div class="h-2 rounded bg-green-600" style="width: {{ min(100, $r['avance'] ?? 0) }}%"></div>
                                            </div>
                                            <span class="tabular-nums text-xs text-slate-600 w-12 text-right">{{ $r['avance'] !== null ? $r['avance'].'%' : '—' }}</span>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif

{{-- ===== 6. Votos por conquistar ===== --}}
@if ($seccion === 'conquistar' && $plan)
    @php($t = $plan['totales'])

    @if (! $plan['hay_potencial'])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            No está cargado el potencial electoral de {{ $departamento->nombre }}: sin él no se sabe cuántos no votaron.
            Se carga con <code>php artisan municipios:potencial</code>. Abajo solo se ve el voto en blanco.
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="{{ $tarjeta }} p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">No votaron en {{ $plan['ultimo'] }}</div>
            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($t['no_votaron']) }}</div>
            <div class="text-sm text-slate-600">votó el {{ $t['participacion'] ?? '—' }}% del potencial</div>
        </div>
        <div class="{{ $tarjeta }} p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Votaron en blanco</div>
            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($t['blanco']) }}</div>
            <div class="text-sm text-slate-600">{{ $t['blanco_pct'] ?? '—' }}% de los votos válidos</div>
        </div>
        <div class="{{ $tarjeta }} p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Votan por presidente, no aquí</div>
            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($t['solo_presidencial']) }}</div>
            <div class="text-sm text-slate-600">
                {{ $plan['anio_presidencial'] ? 'más votos en presidencia '.$plan['anio_presidencial'].' (1.ª vuelta) que aquí' : 'sin presidencial cargada' }}
            </div>
        </div>
        <div class="{{ $tarjeta }} p-4 border-violet-300">
            <div class="text-xs uppercase tracking-wider text-violet-500">Por conquistar</div>
            <div class="mt-1 text-2xl font-extrabold text-violet-700">{{ $num($t['conquistables']) }}</div>
            <div class="text-sm text-slate-600">no votaron + en blanco</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 {{ $tarjeta }} p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <div class="text-lg font-extrabold text-slate-900">Dónde están</div>
                <div class="text-xs text-slate-500">Morado más intenso = más gente por conquistar</div>
            </div>
            <div id="analisis-mapa" class="h-[440px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
        </div>
        <div class="lg:col-span-2 {{ $tarjeta }} p-4">
            <div class="text-lg font-extrabold text-slate-900 mb-1">Cómo leerlo</div>
            <div class="text-sm text-slate-600 space-y-2">
                <p><b>No votaron:</b> cédulas habilitadas menos votos válidos. Es aproximado: el potencial es el más reciente y el histórico no trae nulos ni no marcados.</p>
                <p><b>En blanco:</b> votantes que fueron a votar pero no les convenció nadie. Son los más fáciles de convencer.</p>
                <p><b>Votan por presidente y no aquí:</b> gente que sí sale a votar cuando la elección les importa. Se despiertan con temas de opinión.</p>
                <p class="text-slate-500">Son cifras por municipio: no dicen quiénes son esas personas.</p>
            </div>
        </div>
    </div>

    <div class="{{ $tarjeta }} p-6">
        <div class="text-lg font-extrabold text-slate-900">Todos los municipios</div>
        <div class="text-xs text-slate-500 mb-4">{{ $cargoLabel }} {{ $plan['ultimo'] }}, de más a menos gente por conquistar</div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="py-2 pr-4">Municipio</th>
                        <th class="py-2 px-3 text-right">Potencial</th>
                        <th class="py-2 px-3 text-right">Votaron</th>
                        <th class="py-2 px-3 text-right">Participación</th>
                        @if ($plan['previo'])
                            <th class="py-2 px-3 text-right">Votos vs {{ $plan['previo'] }}</th>
                        @endif
                        <th class="py-2 px-3 text-right">No votaron</th>
                        <th class="py-2 px-3 text-right">En blanco</th>
                        @if ($plan['anio_presidencial'])
                            <th class="py-2 px-3 text-right">Solo presidencial</th>
                        @endif
                        <th class="py-2 px-3 text-right">Por conquistar</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($plan['municipios'] as $r)
                        <tr wire:key="cq-{{ $r['id'] }}">
                            <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['potencial']) }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['votaron']) }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ $r['participacion'] !== null ? $r['participacion'].'%' : '—' }}</td>
                            @if ($plan['previo'])
                                <td class="py-2 px-3 text-right tabular-nums {{ ($r['crecio'] ?? 0) < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $r['crecio'] !== null ? $signo($r['crecio']).'%' : '—' }}</td>
                            @endif
                            <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['no_votaron']) }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['blanco']) }} <span class="text-xs text-slate-400">{{ $r['blanco_pct'] }}%</span></td>
                            @if ($plan['anio_presidencial'])
                                <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['solo_presidencial']) }}</td>
                            @endif
                            <td class="py-2 px-3 text-right tabular-nums font-bold text-violet-700">{{ $num($r['conquistables']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- ===== 7. Municipios en disputa ===== --}}
@if ($seccion === 'disputa' && $plan)
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach (['disputado' => ['text-orange-600', 'margen corto o cambio de bloque'], 'inclinado' => ['text-slate-900', 'ganó uno, pero no es seguro'], 'fortin' => ['text-slate-900', 'ganó holgado dos veces seguidas']] as $estado => [$color, $texto])
            <div class="{{ $tarjeta }} p-4">
                <div class="text-xs uppercase tracking-wider text-slate-400">{{ PlanCampania::ESTADOS[$estado] }}</div>
                <div class="mt-1 text-2xl font-extrabold {{ $color }}">{{ $plan['conteo'][$estado] }}</div>
                <div class="text-sm text-slate-600">municipios: {{ $texto }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 {{ $tarjeta }} p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <div class="text-lg font-extrabold text-slate-900">{{ $cargoLabel }} {{ $plan['ultimo'] }}</div>
                <div class="flex flex-wrap gap-3 text-xs text-slate-500">
                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded" style="background:#ea580c"></span>Disputado</span>
                    @foreach (PartidoIdeologia::BLOQUES as $b)
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded" style="background: {{ $b[1] }}"></span>{{ $b[0] }}</span>
                    @endforeach
                    <span>(claro = inclinado, fuerte = fortín)</span>
                </div>
            </div>
            <div id="analisis-mapa" class="h-[440px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
        </div>
        <div class="lg:col-span-2 {{ $tarjeta }} p-4">
            <div class="text-lg font-extrabold text-slate-900 mb-1">Cómo se mide</div>
            <div class="text-sm text-slate-600 space-y-2">
                <p>Puntaje de 0 a 100:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li><b>60 %</b> margen entre los dos primeros (0 puntos de diferencia = máximo; 30 o más = nada).</li>
                    <li><b>25 %</b> si el ganador cambió de bloque frente a {{ $plan['previo'] ?? 'la anterior' }}. Si no se sabe el bloque, cambiar de partido cuenta un cuarto.</li>
                    <li><b>15 %</b> cuántos votos pasaron de un bloque a otro.</li>
                </ul>
                <p>Disputado = 55 puntos o más, o menos de 5 de margen. Los bloques salen de la tabla de la sección 3: clasificar las coaliciones afina este puntaje.</p>
            </div>
        </div>
    </div>

    <div class="{{ $tarjeta }} p-6">
        <div class="text-lg font-extrabold text-slate-900">Todos los municipios</div>
        <div class="text-xs text-slate-500 mb-4">Del más disputado al más seguro</div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="py-2 pr-4">Municipio</th>
                        <th class="py-2 px-3">Estado</th>
                        <th class="py-2 px-3 text-right">Puntaje</th>
                        <th class="py-2 px-3">Ganó {{ $plan['ultimo'] }}</th>
                        <th class="py-2 px-3 text-right">Margen</th>
                        @if ($plan['previo'])
                            <th class="py-2 px-3">Ganó {{ $plan['previo'] }}</th>
                            <th class="py-2 px-3 text-right">Margen</th>
                            <th class="py-2 px-3 text-right">Votos que cambiaron de bloque</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($plan['municipios'] as $r)
                        <tr wire:key="dp-{{ $r['id'] }}">
                            <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                            <td class="py-2 px-3">
                                <span class="text-xs font-semibold {{ $r['estado'] === 'disputado' ? 'text-orange-600' : 'text-slate-600' }}">{{ PlanCampania::ESTADOS[$r['estado']] }}</span>
                            </td>
                            <td class="py-2 px-3 text-right tabular-nums font-bold">{{ $r['puntaje'] }}</td>
                            <td class="py-2 px-3 text-xs">
                                <span class="text-slate-900">{{ $r['ganador_nombre'] }}</span>
                                @if ($r['bloque'])
                                    <span class="font-semibold" style="color: {{ PartidoIdeologia::BLOQUES[$r['bloque']][1] }}">· {{ PartidoIdeologia::BLOQUES[$r['bloque']][0] }}</span>
                                @endif
                            </td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ $r['margen'] }} pts</td>
                            @if ($plan['previo'])
                                <td class="py-2 px-3 text-xs text-slate-600">
                                    {{ $r['ganador_previo'] ?? '—' }}
                                    @if ($r['bloque_previo'])
                                        <span class="font-semibold" style="color: {{ PartidoIdeologia::BLOQUES[$r['bloque_previo']][1] }}">· {{ PartidoIdeologia::BLOQUES[$r['bloque_previo']][0] }}</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r['margen_previo'] !== null ? $r['margen_previo'].' pts' : '—' }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r['volatilidad'] !== null ? $r['volatilidad'].'%' : '—' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- ===== 8. Prioridades ===== --}}
@if ($seccion === 'prioridades' && $plan)
    <div class="{{ $tarjeta }} p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
        <label class="text-xs font-semibold text-slate-700">Enfoque
            <select wire:model.live="enfoque" class="{{ $select }}">
                @foreach (PlanCampania::ENFOQUES as $clave => [$nombre])
                    <option value="{{ $clave }}">{{ $nombre }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-slate-700">Afinidad con
            <select wire:model.live="afinidad" class="{{ $select }}">
                <option value="">No medir afinidad</option>
                @foreach (PartidoIdeologia::BLOQUES as $clave => $b)
                    <option value="{{ $clave }}">{{ $b[0] }}</option>
                @endforeach
            </select>
        </label>
        <div class="text-xs text-slate-500 sm:col-span-2">
            Peso de cada criterio:
            {{ collect($plan['pesos'])->filter()->map(fn ($w, $k) => PlanCampania::CRITERIOS[$k].' '.round($w * 100 / max(1, array_sum($plan['pesos']))).'%')->implode(' · ') }}.
            @if (! $plan['con_registros'])
                Sin candidato elegido, "falta para la meta" es la meta entera.
            @endif
            @if ($plan['bloque'])
                La {{ mb_strtolower(PartidoIdeologia::BLOQUES[$plan['bloque']][0]) }} sacó {{ $plan['bloque_depto'] }}% en el departamento en {{ $plan['ultimo'] }}.
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach (PlanCampania::ACCIONES as $clave => [$nombre, $texto, $color])
            <div class="{{ $tarjeta }} p-4">
                <div class="flex items-center gap-2 text-xs uppercase tracking-wider text-slate-400">
                    <span class="inline-block w-3 h-3 rounded" style="background: {{ $color }}"></span>{{ $nombre }}
                </div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $plan['acciones'][$clave] ?? 0 }}</div>
                <div class="text-xs text-slate-600">{{ $texto }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 {{ $tarjeta }} p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <div class="text-lg font-extrabold text-slate-900">Dónde ir primero</div>
                <div class="text-xs text-slate-500">Color = qué hacer · más intenso = más prioritario</div>
            </div>
            <div id="analisis-mapa" class="h-[440px] w-full rounded-xl overflow-hidden bg-slate-100" wire:ignore></div>
        </div>
        <div class="lg:col-span-2 {{ $tarjeta }} p-4">
            <div class="text-lg font-extrabold text-slate-900 mb-1">Los 10 primeros</div>
            <div class="text-xs text-slate-500 mb-3">{{ $cargoLabel }} · enfoque {{ mb_strtolower(PlanCampania::ENFOQUES[$plan['enfoque']][0]) }}</div>
            <div class="space-y-3">
                @foreach (array_slice($plan['municipios'], 0, 10) as $r)
                    @php($accion = PlanCampania::ACCIONES[$r['accion']])
                    <div wire:key="top-{{ $r['id'] }}">
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold text-slate-900">{{ $r['puesto'] }}. {{ $r['municipio'] }}</span>
                            <span class="tabular-nums text-slate-700">{{ $r['puntaje'] }} pts</span>
                        </div>
                        <div class="text-xs">
                            <span class="font-semibold" style="color: {{ $accion[2] }}">{{ $accion[0] }}</span>
                            <span class="text-slate-500">· {{ collect($r['razones'])->map(fn ($k) => PlanCampania::CRITERIOS[$k])->implode(', ') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="{{ $tarjeta }} p-6">
        <div class="text-lg font-extrabold text-slate-900">Todos los municipios</div>
        <div class="text-xs text-slate-500 mb-4">
            Cada criterio va de 0 a 1 frente a los demás municipios del departamento. Es una guía para repartir tiempo y recursos, no un pronóstico.
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="py-2 pr-4">#</th>
                        <th class="py-2 pr-4">Municipio</th>
                        <th class="py-2 px-3 text-right">Puntaje</th>
                        <th class="py-2 px-3">Qué hacer</th>
                        @foreach ($plan['pesos'] as $k => $w)
                            <th class="py-2 px-3 text-right">{{ PlanCampania::CRITERIOS[$k] }}</th>
                        @endforeach
                        @if ($plan['bloque'])
                            <th class="py-2 px-3 text-right">{{ PartidoIdeologia::BLOQUES[$plan['bloque']][0] }} vs depto</th>
                        @endif
                        <th class="py-2 px-3 text-right">Faltan</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($plan['municipios'] as $r)
                        @php($accion = PlanCampania::ACCIONES[$r['accion']])
                        <tr wire:key="pr-{{ $r['id'] }}">
                            <td class="py-2 pr-4 text-slate-400 tabular-nums">{{ $r['puesto'] }}</td>
                            <td class="py-2 pr-4 font-semibold text-slate-900">{{ $r['municipio'] }}</td>
                            <td class="py-2 px-3 text-right tabular-nums font-bold">{{ $r['puntaje'] }}</td>
                            <td class="py-2 px-3 text-xs font-semibold whitespace-nowrap" style="color: {{ $accion[2] }}">{{ $accion[0] }}</td>
                            @foreach ($plan['pesos'] as $k => $w)
                                <td class="py-2 px-3 text-right tabular-nums text-slate-600">{{ number_format($r['criterios'][$k], 2) }}</td>
                            @endforeach
                            @if ($plan['bloque'])
                                <td class="py-2 px-3 text-right tabular-nums {{ ($r['afinidad'] ?? 0) < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $r['afinidad'] !== null ? $signo($r['afinidad']).' pts' : '—' }}</td>
                            @endif
                            <td class="py-2 px-3 text-right tabular-nums">{{ $num($r['falta']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
