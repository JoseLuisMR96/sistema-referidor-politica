@php
    use App\Models\IntegranteEquipo;

    $pesos = fn ($v) => '$ '.number_format((float) $v, 0, ',', '.');
@endphp

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Equipo de campaña {{ $tenant ? "de {$tenant->nombre}" : '' }}</h2>
            <div class="text-sm text-gray-500">Emisarios, coordinadores y demás personas que van a los eventos en nombre del candidato.</div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('eventos.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">Eventos</a>
            @if ($tenant && $puede)
                <button type="button" wire:click="abrir"
                    class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                    ➕ Agregar persona
                </button>
            @endif
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">{{ session('success') }}</div>
        @endif

        @if (! $tenant)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Elige un candidato en el menú lateral para ver y registrar su equipo.
            </div>
        @else
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Buscar</label>
                <div class="relative max-w-lg">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Nombre"
                        class="w-full rounded-xl border-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="py-3 px-4">Persona</th>
                            <th class="py-3 px-4">Rol</th>
                            <th class="py-3 px-4 text-right">Eventos como responsable</th>
                            <th class="py-3 px-4 text-right">Acompañó</th>
                            <th class="py-3 px-4 text-right">Dinero asignado</th>
                            <th class="py-3 px-4 text-right">Gastado</th>
                            <th class="py-3 px-4">Estado</th>
                            @if ($puede)
                                <th class="py-3 px-4 text-right">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($integrantes as $i)
                            @php($r = $resumen[$i->id] ?? null)
                            <tr wire:key="int-{{ $i->id }}" class="{{ $i->activo ? '' : 'opacity-60' }}">
                                <td class="py-3 px-4">
                                    <a href="{{ route('eventos.index', ['persona' => $i->id]) }}" class="font-semibold text-gray-900 hover:underline">{{ $i->nombre }}</a>
                                    @if ($i->telefono)
                                        <div class="text-xs text-gray-500">{{ $i->telefono }}</div>
                                    @endif
                                    @if ($i->notas)
                                        <div class="text-xs text-gray-400">{{ $i->notas }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-xs">{{ $i->rolTexto() }}</td>
                                <td class="py-3 px-4 text-right tabular-nums">{{ $r->eventos ?? 0 }}</td>
                                <td class="py-3 px-4 text-right tabular-nums">{{ $i->eventos_count }}</td>
                                <td class="py-3 px-4 text-right tabular-nums font-bold">{{ $pesos($r->asignado ?? 0) }}</td>
                                <td class="py-3 px-4 text-right tabular-nums">{{ $pesos($r->gastado ?? 0) }}</td>
                                <td class="py-3 px-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $i->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $i->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                @if ($puede)
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" wire:click="abrir({{ $i->id }})" class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50">Editar</button>
                                            <button type="button" wire:click="eliminar({{ $i->id }})"
                                                wire:confirm="¿Quitar a {{ $i->nombre }} del equipo? Si ya tiene eventos, solo se desactiva."
                                                class="px-3 py-1.5 rounded-md border border-rose-200 text-rose-700 text-xs font-semibold hover:bg-rose-50">Quitar</button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-8 px-4 text-center text-gray-500">Todavía no hay personas en el equipo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Modal --}}
    @if ($modal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="modal-equipo"
            x-data x-on:keydown.escape.window="$wire.cerrar()">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl space-y-4">
                <div class="text-lg font-bold text-gray-900">{{ $editandoId ? 'Editar persona' : 'Agregar persona al equipo' }}</div>

                <label class="block text-sm font-semibold">Nombre
                    <input wire:model="nombre" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Ana Pérez">
                </label>
                @error('nombre') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block text-sm font-semibold">Rol
                        <select wire:model="rol" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            @foreach (IntegranteEquipo::ROLES as $valor => $texto)
                                <option value="{{ $valor }}">{{ $texto }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">Teléfono (opcional)
                        <input wire:model="telefono" inputmode="tel" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="300 123 4567">
                    </label>
                </div>
                @error('telefono') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

                <label class="block text-sm font-semibold">Notas (opcional)
                    <input wire:model="notas" maxlength="255" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Zona que cubre, a quién reporta…">
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="activo" class="rounded border-gray-300"> Activo
                </label>

                @error('tenant') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" wire:click="cerrar" class="px-4 py-2 rounded-md border text-sm font-semibold">Cancelar</button>
                    <button type="button" wire:click="guardar" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
                </div>
            </div>
        </div>
    @endif
</div>
