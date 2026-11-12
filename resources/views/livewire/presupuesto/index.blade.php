@php
    use App\Models\GastoCampana;

    $pesos = fn ($v) => $v === null ? '—' : '$ '.number_format((float) $v, 0, ',', '.');
    $tarjeta = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $campo = 'mt-1 w-full rounded-md border-gray-300 text-sm';
    $colorNivel = ['rojo' => 'bg-rose-600', 'naranja' => 'bg-orange-500', 'amarillo' => 'bg-amber-400'];
    $cajaNivel = ['rojo' => 'border-rose-200 bg-rose-50 text-rose-800', 'naranja' => 'border-orange-200 bg-orange-50 text-orange-800', 'amarillo' => 'border-amber-200 bg-amber-50 text-amber-800'];
@endphp

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Presupuesto de campaña</h2>
            <div class="text-sm text-gray-500">Gastos contra el tope legal, por categoría de Cuentas Claras.</div>
        </div>
        @if ($tenant)
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('presupuesto.exportar') }}" class="rounded-xl border px-3 py-2 text-sm font-semibold hover:bg-gray-50">⬇ Exportar CSV</a>
                @if ($puede)
                    <button type="button" x-data x-on:click="Livewire.dispatch('presupuesto-editar')" class="rounded-xl border px-3 py-2 text-sm font-semibold hover:bg-gray-50">Editar presupuesto</button>
                    <button type="button" x-data x-on:click="Livewire.dispatch('presupuesto-gasto')" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">➕ Registrar gasto</button>
                @endif
            </div>
        @endif
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">{{ session('success') }}</div>
        @endif

        @if (! $tenant)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Elige un candidato en el menú lateral.</div>
        @else
            {{-- ===== Tope ===== --}}
            <div class="{{ $tarjeta }} p-5">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-slate-400">Tope legal de gastos</div>
                        <div class="mt-1 text-3xl font-extrabold text-slate-900">{{ $pesos($r['tope']) }}</div>
                        <div class="text-xs text-slate-500">{{ $r['tope_fuente'] ?: 'Lo fija el CNE para cada circunscripción.' }}</div>
                    </div>
                    <div class="grid grid-cols-3 gap-6 text-right">
                        <div><div class="text-xs text-slate-400">Gastado</div><div class="text-lg font-bold text-slate-900">{{ $pesos($r['gastado']) }}</div></div>
                        <div><div class="text-xs text-slate-400">Comprometido</div><div class="text-lg font-bold text-amber-700">{{ $pesos($r['comprometido']) }}</div></div>
                        <div><div class="text-xs text-slate-400">Disponible</div><div class="text-lg font-bold {{ ($r['disponible'] ?? 0) < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $pesos($r['disponible']) }}</div></div>
                    </div>
                </div>
                @if ($r['tope'])
                    @php($pG = min(100, $r['gastado'] * 100 / $r['tope']))
                    @php($pC = min(100 - $pG, $r['comprometido'] * 100 / $r['tope']))
                    <div class="mt-4 h-4 w-full overflow-hidden rounded-full bg-slate-100 flex">
                        <div class="h-4 {{ $colorNivel[$r['nivel']] ?? 'bg-indigo-600' }}" style="width: {{ $pG }}%"></div>
                        <div class="h-4 bg-amber-300" style="width: {{ $pC }}%"></div>
                    </div>
                    <div class="mt-1 flex justify-between text-xs text-slate-500">
                        <span>{{ $r['pct_tope'] }}% del tope (gastado + comprometido)</span>
                        <span>avisos al 80, 95 y 100 %</span>
                    </div>
                @endif
            </div>

            @foreach ($r['alertas'] as [$nivel, $texto])
                <div class="rounded-xl border px-4 py-3 text-sm {{ $cajaNivel[$nivel] }}">{{ $texto }}</div>
            @endforeach

            {{-- ===== Por categoría ===== --}}
            <div class="{{ $tarjeta }} p-5">
                <div class="text-lg font-extrabold text-slate-900">Por categoría</div>
                <div class="text-xs text-slate-500 mb-3">
                    Categorías del informe de gastos de Cuentas Claras (verifica los códigos con el formato vigente).
                    El dinero de los eventos cae en "Actos públicos": lo legalizado como gastado y lo asignado sin legalizar como comprometido.
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="py-2 pr-3">Categoría</th>
                                <th class="py-2 px-2 text-right">Presupuesto</th>
                                <th class="py-2 px-2 text-right">Gastado</th>
                                <th class="py-2 px-2 text-right">Comprometido</th>
                                <th class="py-2 px-2 w-1/4">Uso</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($r['categorias'] as $c)
                                <tr wire:key="cat-{{ $c['codigo'] }}" class="{{ $c['usado'] || $c['presupuesto'] ? '' : 'text-slate-400' }}">
                                    <td class="py-2 pr-3">
                                        <button type="button" wire:click="$set('categoria', '{{ $c['codigo'] }}')" class="text-left hover:underline">
                                            <span class="font-mono text-xs text-slate-400">{{ $c['codigo'] }}</span>
                                            <span class="font-semibold">{{ $c['nombre'] }}</span>
                                        </button>
                                    </td>
                                    <td class="py-2 px-2 text-right tabular-nums whitespace-nowrap">{{ $c['presupuesto'] ? $pesos($c['presupuesto']) : '—' }}</td>
                                    <td class="py-2 px-2 text-right tabular-nums whitespace-nowrap">{{ $c['gastado'] ? $pesos($c['gastado']) : '—' }}</td>
                                    <td class="py-2 px-2 text-right tabular-nums whitespace-nowrap text-amber-700">{{ $c['comprometido'] ? $pesos($c['comprometido']) : '' }}</td>
                                    <td class="py-2 px-2">
                                        @if ($c['pct'] !== null)
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 flex-1 rounded bg-slate-100"><div class="h-2 rounded {{ $c['excedido'] ? 'bg-rose-600' : 'bg-indigo-600' }}" style="width: {{ min(100, $c['pct']) }}%"></div></div>
                                                <span class="w-12 text-right text-xs tabular-nums {{ $c['excedido'] ? 'text-rose-600 font-bold' : '' }}">{{ $c['pct'] }}%</span>
                                            </div>
                                        @elseif ($c['usado'])
                                            <span class="text-xs text-slate-400">sin presupuesto</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($r['eventos_sin_legalizar']->isNotEmpty())
                <div class="{{ $tarjeta }} p-5">
                    <div class="text-lg font-extrabold text-slate-900">Eventos sin legalizar</div>
                    <div class="text-xs text-slate-500 mb-3">Dinero asignado que falta soportar: escribe el monto gastado en el evento cuando se legalice.</div>
                    <div class="divide-y">
                        @foreach ($r['eventos_sin_legalizar'] as $e)
                            <a href="{{ route('eventos.edit', $e->id) }}" class="flex items-center justify-between gap-3 py-2 text-sm hover:bg-slate-50" wire:key="sl-{{ $e->id }}">
                                <span>
                                    <span class="font-semibold">{{ $e->titulo }}</span>
                                    <span class="text-xs {{ $e->fecha->lt(now()->subDays(\App\Services\Territorio\PresupuestoCampana::DIAS_LEGALIZAR)) ? 'text-rose-600 font-semibold' : 'text-slate-500' }}">· {{ $e->fecha->format('d/m/Y') }}</span>
                                </span>
                                <span class="tabular-nums font-bold">{{ $pesos($e->monto_asignado) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Gastos ===== --}}
            <div class="{{ $tarjeta }} p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="text-[11px] font-semibold text-slate-500 sm:col-span-2">Buscar
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Concepto, proveedor o número de factura" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                </label>
                <label class="text-[11px] font-semibold text-slate-500">Categoría
                    <select wire:model.live="categoria" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Todas</option>
                        @foreach (GastoCampana::CATEGORIAS as $codigo => $nombre)
                            <option value="{{ $codigo }}">{{ $codigo }} · {{ $nombre }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="py-3 px-4">Fecha</th>
                            <th class="py-3 px-4">Concepto</th>
                            <th class="py-3 px-4">Categoría</th>
                            <th class="py-3 px-4">Proveedor</th>
                            <th class="py-3 px-4 text-right">Valor</th>
                            <th class="py-3 px-4">Soporte</th>
                            @if ($puede)
                                <th class="py-3 px-4 text-right">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($gastos as $g)
                            <tr wire:key="g-{{ $g->id }}">
                                <td class="py-3 px-4 whitespace-nowrap">{{ $g->fecha->format('d/m/Y') }}</td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-gray-900">{{ $g->concepto }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ $g->documento ? 'Doc. '.$g->documento : '' }}
                                        @if ($g->evento) · <a href="{{ route('eventos.show', $g->evento_id) }}" class="underline">{{ $g->evento->titulo }}</a> @endif
                                        @if ($g->responsable) · {{ $g->responsable->nombre }} @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-xs">{{ $g->categoriaTexto() }}</td>
                                <td class="py-3 px-4 text-xs">{{ $g->proveedor ?: '—' }}</td>
                                <td class="py-3 px-4 text-right tabular-nums whitespace-nowrap font-bold">
                                    {{ $pesos($g->valor) }}
                                    @if ($g->estado === 'por_pagar')
                                        <div class="text-[11px] font-semibold text-amber-700">por pagar</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    @if ($g->soporte_ruta)
                                        <a href="{{ route('presupuesto.soporte', $g) }}" target="_blank" rel="noopener" class="font-semibold text-indigo-700 underline">Ver</a>
                                    @else
                                        <span class="text-amber-700">Falta</span>
                                    @endif
                                </td>
                                @if ($puede)
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" wire:click="abrirGasto({{ $g->id }})" class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50">Editar</button>
                                            <button type="button" wire:click="eliminarGasto({{ $g->id }})" wire:confirm="¿Eliminar este gasto?"
                                                class="px-3 py-1.5 rounded-md border border-rose-200 text-rose-700 text-xs font-semibold hover:bg-rose-50">Eliminar</button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-8 px-4 text-center text-gray-500">No hay gastos registrados{{ $categoria !== '' ? ' en esta categoría' : '' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $gastos->links() }}</div>
        @endif
    </div>

    {{-- ===== Modal: gasto ===== --}}
    @if ($modalGasto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="modal-gasto" x-data x-on:keydown.escape.window="$wire.set('modalGasto', false)">
            <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl space-y-4">
                <div class="text-lg font-bold text-gray-900">{{ $gastoId ? 'Editar gasto' : 'Registrar gasto' }}</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block text-sm font-semibold sm:col-span-2">Concepto
                        <input wire:model="gasto.concepto" class="{{ $campo }}" placeholder="Impresión de 5.000 volantes">
                        @error('gasto.concepto') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold">Categoría
                        <select wire:model="gasto.categoria" class="{{ $campo }}">
                            @foreach (GastoCampana::CATEGORIAS as $codigo => $nombre)
                                <option value="{{ $codigo }}">{{ $codigo }} · {{ $nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">Fecha
                        <input type="date" wire:model="gasto.fecha" class="{{ $campo }}">
                        @error('gasto.fecha') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold">Valor
                        <input wire:model="gasto.valor" inputmode="decimal" class="{{ $campo }}" placeholder="350.000">
                        @error('gasto.valor') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold">Estado
                        <select wire:model="gasto.estado" class="{{ $campo }}">
                            @foreach (GastoCampana::ESTADOS as $valor => $texto)
                                <option value="{{ $valor }}">{{ $texto }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">Proveedor (opcional)
                        <input wire:model="gasto.proveedor" class="{{ $campo }}" placeholder="Litografía El Llano">
                    </label>
                    <label class="block text-sm font-semibold">N.º de factura o soporte (opcional)
                        <input wire:model="gasto.documento" class="{{ $campo }}" placeholder="FE-1234">
                    </label>
                    <label class="block text-sm font-semibold">Evento (opcional)
                        <select wire:model="gasto.evento_id" class="{{ $campo }}">
                            <option value="">— Ninguno —</option>
                            @foreach ($eventosLista as $e)
                                <option value="{{ $e->id }}">{{ $e->fecha->format('d/m') }} · {{ \Illuminate\Support\Str::limit($e->titulo, 50) }}</option>
                            @endforeach
                        </select>
                        <span class="block text-xs font-normal text-gray-500 mt-1">Si lo ligas, el dinero del evento no se cuenta dos veces.</span>
                    </label>
                    <label class="block text-sm font-semibold">Responsable (opcional)
                        <select wire:model="gasto.responsable_id" class="{{ $campo }}">
                            <option value="">— Ninguno —</option>
                            @foreach ($equipo as $i)
                                <option value="{{ $i->id }}">{{ $i->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold sm:col-span-2">Soporte (factura o cuenta de cobro, PDF o foto)
                        <input type="file" wire:model="soporte" accept="application/pdf,image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm font-normal">
                        <span wire:loading wire:target="soporte" class="text-xs font-normal text-slate-500">Subiendo…</span>
                        @error('soporte') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                    </label>
                </div>
                @error('tenant') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" wire:click="$set('modalGasto', false)" class="px-4 py-2 rounded-md border text-sm font-semibold">Cancelar</button>
                    <button type="button" wire:click="guardarGasto" wire:loading.attr="disabled" wire:target="guardarGasto,soporte"
                        class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Modal: presupuesto ===== --}}
    @if ($modalPresupuesto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="modal-presupuesto" x-data x-on:keydown.escape.window="$wire.set('modalPresupuesto', false)">
            <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl space-y-4">
                <div class="text-lg font-bold text-gray-900">Presupuesto y tope legal</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block text-sm font-semibold">Tope legal de gastos
                        <input wire:model="tope" inputmode="decimal" class="{{ $campo }}" placeholder="1.500.000.000">
                        @error('tope') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold">Fuente del tope
                        <input wire:model="topeFuente" class="{{ $campo }}" placeholder="Resolución CNE … de 2027">
                    </label>
                </div>
                <div>
                    <div class="text-sm font-semibold">Presupuesto por categoría</div>
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach (GastoCampana::CATEGORIAS as $codigo => $nombre)
                            <label class="block text-xs font-semibold text-slate-600">{{ $codigo }} · {{ $nombre }}
                                <input wire:model="rubros.{{ $codigo }}" inputmode="decimal" class="{{ $campo }}" placeholder="0">
                            </label>
                        @endforeach
                    </div>
                    @foreach ($errors->get('rubros.*') as $m)
                        <div class="text-sm text-red-600 mt-1">{{ $m[0] }}</div>
                    @endforeach
                </div>
                @error('tenant') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" wire:click="$set('modalPresupuesto', false)" class="px-4 py-2 rounded-md border text-sm font-semibold">Cancelar</button>
                    <button type="button" wire:click="guardarPresupuesto" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
                </div>
            </div>
        </div>
    @endif
</div>
