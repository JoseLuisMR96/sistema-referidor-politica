@php
    use App\Livewire\Admin\Ideologias\Index;
    use App\Models\PartidoIdeologia;

    $ideologias = PartidoIdeologia::IDEOLOGIAS;
    $puede = $this->puedeClasificar();
    $num = fn ($n) => $n === null ? '—' : number_format($n, 0, ',', '.');
@endphp

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ideología de los partidos</h2>
            <div class="text-sm text-gray-500">
                Izquierda, centro o derecha de cada partido. La usan la tendencia, la disputa y las prioridades del Análisis electoral.
            </div>
        </div>
        @if ($puede)
            <button type="button" x-data x-on:click="Livewire.dispatch('ideologia-abrir')"
                class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                ➕ Clasificar partido
            </button>
        @endif
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">{{ session('success') }}</div>
        @endif

        @unless ($puede)
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Solo un usuario global con permiso de importar el histórico puede cambiar la clasificación: aplica a todos los candidatos.
            </div>
        @endunless

        {{-- Filtros --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <label class="text-[11px] font-semibold text-slate-500">Departamento
                <select wire:model.live="departamentoId" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    @foreach ($departamentos as $d)
                        <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-[11px] font-semibold text-slate-500">Cargo
                <select wire:model.live="cargo" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    @foreach ($cargos as $c)
                        <option value="{{ $c->value }}">{{ $c->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-[11px] font-semibold text-slate-500">Estado
                <select wire:model.live="estado" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    @foreach (Index::ESTADOS as $clave => $texto)
                        <option value="{{ $clave }}">{{ $texto }}{{ isset($conteo[$clave]) ? " ({$conteo[$clave]})" : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-[11px] font-semibold text-slate-500">Buscar
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Nombre del partido…"
                    class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            </label>
        </div>

        <div class="text-xs text-slate-500">
            {{ $estado === 'clasificados' ? 'Toda la tabla guardada.' : 'Partidos con votos en este departamento y cargo.' }}
            Una coalición sin clasificar toma la ideología de sus partidos si todos son del mismo bloque ("inferida").
            @if ($total > $filas->count())
                Se muestran los primeros {{ $filas->count() }} de {{ $total }}: usa el buscador.
            @endif
        </div>

        {{-- Tabla --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="py-3 px-4">Partido o coalición</th>
                        <th class="py-3 px-4 text-right">Votos</th>
                        <th class="py-3 px-4">Ideología</th>
                        <th class="py-3 px-4">Nota</th>
                        @if ($puede)
                            <th class="py-3 px-4 text-right">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($filas as $f)
                        <tr wire:key="ideo-{{ md5($f['clave']) }}">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-gray-900">{{ $f['nombre'] }}</div>
                                @if ($f['nombre'] !== $f['clave'])
                                    <div class="text-[11px] text-gray-400">{{ $f['clave'] }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ $num($f['votos']) }}</td>
                            <td class="py-3 px-4">
                                @if ($f['ideologia'])
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold" style="color: {{ $ideologias[$f['ideologia']][2] }}">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $ideologias[$f['ideologia']][2] }}"></span>
                                        {{ $ideologias[$f['ideologia']][0] }}
                                    </span>
                                @elseif ($f['inferida'])
                                    <span class="text-xs text-slate-500">Inferida: {{ $ideologias[$f['inferida']][0] }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-50 text-amber-700 px-2 py-0.5 text-xs font-semibold">Sin clasificar</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-600 max-w-md">{{ $f['nota'] ?: '' }}</td>
                            @if ($puede)
                                <td class="py-3 px-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="abrir({{ \Illuminate\Support\Js::from($f['clave']) }})"
                                            class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50">
                                            {{ $f['ideologia'] ? 'Editar' : 'Clasificar' }}
                                        </button>
                                        @if ($f['ideologia'])
                                            <button type="button" wire:click="quitar({{ \Illuminate\Support\Js::from($f['clave']) }})"
                                                wire:confirm="¿Quitar la clasificación de este partido?"
                                                class="px-3 py-1.5 rounded-md border border-rose-200 text-rose-700 text-xs font-semibold hover:bg-rose-50">Quitar</button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 px-4 text-center text-gray-500">No hay partidos con este filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal de clasificación --}}
    @if ($modal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="modal-ideologia"
            x-data x-on:keydown.escape.window="$wire.cerrar()">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl space-y-4">
                <div class="text-lg font-bold text-gray-900">Clasificar partido</div>

                @if (! $elegirPartido)
                    <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800">{{ $clave }}</div>
                @else
                    <label class="block text-sm font-semibold">Partido sin clasificar
                        <select wire:model="clave" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">— Elige —</option>
                            @foreach ($opciones as $o)
                                <option value="{{ $o['clave'] }}">{{ $o['nombre'] }} ({{ $num($o['votos']) }} votos)</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @error('clave') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

                <div>
                    <div class="block text-sm font-semibold mb-1">Ideología</div>
                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-2">
                        @foreach ($ideologias as $valor => [$nombre, , $color])
                            <label class="flex sm:flex-col items-center gap-2 rounded-lg border px-2 py-2 text-xs font-semibold cursor-pointer text-center
                                {{ $ideologia === $valor ? 'ring-2 ring-indigo-500 border-indigo-500' : 'hover:bg-gray-50' }}">
                                <input type="radio" wire:model.live="ideologia" value="{{ $valor }}" class="sr-only">
                                <span class="h-3 w-3 rounded-full" style="background: {{ $color }}"></span>
                                {{ $nombre }}
                            </label>
                        @endforeach
                    </div>
                    @error('ideologia') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <label class="block text-sm font-semibold">Nota (opcional)
                    <input type="text" wire:model="nota" maxlength="500" placeholder="Fuente o motivo de la clasificación"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </label>
                @error('nota') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" wire:click="cerrar" class="px-4 py-2 rounded-md border text-sm font-semibold">Cancelar</button>
                    <button type="button" wire:click="guardar" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
                </div>
            </div>
        </div>
    @endif
</div>
