<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Referidores</h2>
            <div class="text-sm text-gray-500">Gestión de códigos y enlaces de referido</div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                Total: {{ $referrers->total() }}
            </span>

            @can('referidores.crear')
                <a
                    href="{{ route('referrers.create') }}"
                    class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm
                           hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                >
                    ➕ Crear
                </a>
            @endcan
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        {{-- Toolbar --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">

                {{-- Search --}}
                <div class="lg:col-span-8 min-w-0">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Buscar</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
                        <input
                            type="text"
                            wire:model.live="search"
                            class="w-full rounded-xl border-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Buscar por nombre, código o teléfono"
                        >
                    </div>
                </div>

                {{-- PerPage --}}
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Filas</label>
                    <select
                        wire:model.live="perPage"
                        class="w-full rounded-xl border-slate-200 bg-white py-2.5 text-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                {{-- Clear (opcional) --}}
                <div class="lg:col-span-2">
                    <button
                        type="button"
                        wire:click="$set('search','')"
                        class="w-full inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold
                               hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2"
                    >
                        Limpiar
                    </button>
                </div>
            </div>

            {{-- Chips filtros activos --}}
            @if($search)
                <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-600">
                    <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1">
                        Búsqueda: <span class="font-semibold text-slate-800">"{{ $search }}"</span>
                    </span>
                </div>
            @endif
        </div>

        {{-- DataTable --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs uppercase tracking-wider text-slate-600 border-b">
                            <th class="py-3 px-4">Referidor</th>
                            <th class="py-3 px-4">Código</th>
                            <th class="py-3 px-4">Estado</th>
                            <th class="py-3 px-4 hidden md:table-cell">Link</th>
                            <th class="py-3 px-4 text-center">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        @forelse($referrers as $r)
                            <tr class="hover:bg-slate-50 transition-colors">
                                {{-- Referidor --}}
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-900">{{ $r->name }}</div>
                                    <div class="text-xs text-slate-500 md:hidden break-all">
                                        {{ url('/registro?ref=' . $r->code) }}
                                    </div>
                                </td>

                                {{-- Código --}}
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 font-mono">
                                        {{ $r->code }}
                                    </span>
                                </td>

                                {{-- Estado --}}
                                <td class="py-3 px-4">
                                    @if($r->is_active)
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 px-3 py-1 text-xs font-semibold">
                                            Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-700 px-3 py-1 text-xs font-semibold">
                                            Inactivo
                                        </span>
                                    @endif
                                </td>

                                {{-- Link --}}
                                <td class="py-3 px-4 hidden md:table-cell">
                                    <span class="font-mono text-xs text-slate-600 break-all">
                                        {{ url('/registro?ref=' . $r->code) }}
                                    </span>
                                </td>

                                {{-- Acciones --}}
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-2">

                                        @can('registros.exportar')
                                            <a
                                                href="{{ route('registrations.export', ['referrer_id' => $r->id]) }}"
                                                class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                                       hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2"
                                                title="Descargar registros de este referidor"
                                            >
                                                ⬇️ CSV
                                            </a>
                                        @endcan

                                        @can('referidores.editar')
                                            <a
                                                href="{{ route('referrers.edit', $r->id) }}"
                                                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                                       hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2"
                                            >
                                                ✏️ Editar
                                            </a>

                                            @if($r->is_active)
                                                <button
                                                    type="button"
                                                    wire:click="toggle({{ $r->id }})"
                                                    class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                                           hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2"
                                                >
                                                    ⛔ Desactivar
                                                </button>
                                            @else
                                                <button
                                                    type="button"
                                                    wire:click="toggle({{ $r->id }})"
                                                    class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                                           hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2"
                                                >
                                                    ✅ Activar
                                                </button>
                                            @endif
                                        @endcan

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-10 px-4 text-center text-slate-500" colspan="5">
                                    No hay referidores con los filtros actuales.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer paginación --}}
            <div class="px-4 py-4 border-t bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="text-sm text-slate-600">
                    Mostrando
                    <span class="font-semibold">{{ $referrers->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-semibold">{{ $referrers->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold">{{ $referrers->total() }}</span>
                    referidores
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="previousPage"
                        wire:loading.attr="disabled"
                        @disabled($referrers->onFirstPage())
                        class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold
                               hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        ← Anterior
                    </button>

                    <div class="text-sm text-slate-600 px-2">
                        Página <span class="font-semibold">{{ $referrers->currentPage() }}</span>
                        de <span class="font-semibold">{{ $referrers->lastPage() }}</span>
                    </div>

                    <button
                        type="button"
                        wire:click="nextPage"
                        wire:loading.attr="disabled"
                        @disabled(!$referrers->hasMorePages())
                        class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold
                               hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
