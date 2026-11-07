<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Candidatos</h2>
            <div class="text-sm text-gray-500">
                Cada candidato tiene sus referidores, registros, pregoneros y envíos de WhatsApp por separado.
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                Total: {{ $total }}
            </span>
            <a href="{{ route('tenants.create') }}"
                class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                ➕ Nuevo candidato
            </a>
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">{{ session('success') }}</div>
        @endif

        {{-- Buscador --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">Buscar</label>
            <div class="relative max-w-lg">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
                <input type="text" wire:model.live.debounce.300ms="search"
                    class="w-full rounded-xl border-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Nombre, partido o identificador">
            </div>
        </div>

        {{-- Tabla --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="py-3 px-4">Candidato</th>
                        <th class="py-3 px-4">Circunscripción</th>
                        <th class="py-3 px-4 text-right">Registros</th>
                        <th class="py-3 px-4 text-right">Pregoneros</th>
                        <th class="py-3 px-4 text-right">Campañas</th>
                        <th class="py-3 px-4 text-right">Usuarios</th>
                        <th class="py-3 px-4">Estado</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($tenants as $t)
                        <tr wire:key="tenant-{{ $t->id }}">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    @if ($t->fotoUrl())
                                        <img src="{{ $t->fotoUrl() }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                    @else
                                        <span class="h-9 w-9 rounded-full bg-slate-100"></span>
                                    @endif
                                    <div class="min-w-[12rem]">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-gray-900">{{ $t->nombre }}</span>
                                            @if (! \App\Support\Marca::usaColores($t))
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600" title="Sus colores quedan guardados">Colores de la app</span>
                                            @elseif (\App\Support\Marca::esHex($t->color_primario))
                                                <span class="h-3 w-3 rounded-full" style="background-color: {{ $t->color_primario }}" title="Color primario"></span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500">{{ $t->partido ?: ($t->descripcion ?: '—') }}</div>
                                        <div class="text-[11px] text-gray-400 font-mono">{{ $t->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-xs {{ $t->ambito() ? 'text-indigo-700' : 'text-amber-700' }}">
                                {{ $t->circunscripcionTexto() }}
                            </td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ (int) ($registros[$t->id] ?? 0) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ (int) ($pregoneros[$t->id] ?? 0) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ (int) ($campanas[$t->id] ?? 0) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ $t->users_count }}</td>
                            <td class="py-3 px-4">
                                @if ($t->activo)
                                    <span class="inline-flex rounded-full bg-emerald-100 text-emerald-800 px-2.5 py-0.5 text-xs font-semibold">Activo</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 text-gray-600 px-2.5 py-0.5 text-xs font-semibold">Inactivo</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('tenants.edit', $t) }}"
                                        class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50">Editar</a>
                                    <button type="button" wire:click="pedirEliminar({{ $t->id }})"
                                        class="px-3 py-1.5 rounded-md border border-rose-200 text-rose-700 text-xs font-semibold hover:bg-rose-50">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 px-4 text-center text-gray-500">
                                {{ $search !== '' ? 'Ningún candidato coincide con la búsqueda.' : 'Todavía no hay candidatos.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Confirmar borrado (modal) --}}
    @if ($tenantEliminando)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="modal-eliminar"
            x-data x-on:keydown.escape.window="$wire.cancelarEliminar()">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <div class="text-lg font-bold text-rose-900">¿Eliminar a {{ $tenantEliminando->nombre }}?</div>

                @if ($usuariosEliminar)
                    <div class="mt-2 text-sm text-rose-800">
                        No se puede: tiene {{ $usuariosEliminar }} usuario(s) asignados.
                        Muévelos a otro candidato o elimínalos en <a href="{{ route('users.index') }}" class="underline font-semibold">Usuarios</a> primero
                        (si no, quedarían como usuarios globales y verían a todos los candidatos).
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="cancelarEliminar" class="px-4 py-2 rounded-md border text-sm font-semibold">Cerrar</button>
                    </div>
                @else
                    <div class="mt-2 text-sm text-rose-800">Se borra para siempre, junto con todos sus datos. No se puede deshacer.</div>
                    @if ($resumenEliminar)
                        <ul class="mt-3 flex flex-wrap gap-2 text-xs">
                            @foreach ($resumenEliminar as $etiqueta => $cantidad)
                                <li class="rounded bg-rose-50 px-2 py-1 border border-rose-200 text-rose-900">
                                    {{ $etiqueta }}: <span class="font-bold">{{ number_format($cantidad, 0, ',', '.') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="mt-2 text-xs text-rose-700">No tiene datos cargados.</div>
                    @endif

                    <label class="mt-4 block text-xs font-semibold text-rose-900">
                        Para confirmar, escribe el nombre: <span class="font-mono">{{ $tenantEliminando->nombre }}</span>
                        <input type="text" wire:model="confirmacionNombre" autocomplete="off"
                            class="mt-1 block w-full rounded-md border-rose-300 text-sm">
                    </label>
                    @error('confirmacionNombre')
                        <div class="mt-1 text-xs text-rose-700 font-semibold">{{ $message }}</div>
                    @enderror

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" wire:click="cancelarEliminar" class="px-4 py-2 rounded-md border text-sm font-semibold">Cancelar</button>
                        <button type="button" wire:click="eliminar" wire:loading.attr="disabled" wire:target="eliminar"
                            class="px-4 py-2 rounded-md bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700 disabled:opacity-50">
                            Eliminar definitivamente
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
