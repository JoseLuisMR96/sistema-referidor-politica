<x-slot name="header">
    @php
        $publicUrl = route('public.referir', ['id_unico' => $referidor->id_unico]);
        $exportUrl = route('referidores.export_referidos', ['referidor' => $referidor->id]);
    @endphp

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle del Referidor</h2>
            <div class="text-sm text-gray-500">
                {{ $referidor->nombre }} • CC {{ $referidor->cedula }} • {{ $referidor->puesto_votacion }}
            </div>
            <div class="text-xs text-slate-500 mt-1">
                ID único: <span class="font-mono font-semibold text-slate-700">{{ $referidor->id_unico }}</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('pregoneros.referidores.index') }}"
               class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold
                      hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2">
                ← Volver
            </a>

            @can('pregoneros_referidos.exportar')
                <a href="{{ $exportUrl }}"
                   class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm
                          hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">
                    ⬇️ CSV
                </a>
            @endcan
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        {{-- Card link público --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">

                <div class="lg:col-span-8 min-w-0">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Link público</label>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="{{ $publicUrl }}"
                               class="w-full rounded-xl border-slate-200 bg-white px-3 py-2.5 text-sm font-mono text-slate-700" />

                        <button type="button"
                                class="inline-flex items-center justify-center whitespace-nowrap rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold
                                       hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2"
                                onclick="navigator.clipboard.writeText(@js($publicUrl))">
                            📋 Copiar
                        </button>
                    </div>

                    <div class="mt-2 text-xs text-slate-500">
                        Comparte este enlace para que registren referidos asociados a este referidor.
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Resumen</label>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                            Total referidos: {{ $referidos->total() }}
                        </span>
                    </div>
                </div>

            </div>
        </div>

        {{-- Toolbar --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">

                <div class="lg:col-span-8 min-w-0">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Buscar</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
                        <input
                            type="text"
                            wire:model.live="search"
                            class="w-full rounded-xl border-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Buscar por nombre, cédula o puesto"
                        >
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Filas</label>
                    <select
                        wire:model.live="perPage"
                        class="w-full rounded-xl border-slate-200 bg-white py-2.5 text-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

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
                            <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('nombre')">Nombre</th>
                            <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('cedula')">Cédula</th>
                            <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('puesto_votacion')">Puesto</th>
                            <th class="py-3 px-4 text-right cursor-pointer" wire:click="sortBy('created_at')">Creado</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        @forelse($referidos as $ref)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-900">{{ $ref->nombre }}</div>
                                </td>

                                <td class="py-3 px-4">
                                    <span class="font-mono text-xs text-slate-700">{{ $ref->cedula }}</span>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="text-slate-800">{{ $ref->puesto_votacion }}</div>
                                </td>

                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="text-sm text-slate-700">{{ optional($ref->created_at)->format('Y-m-d') }}</div>
                                    <div class="text-xs text-slate-500">{{ optional($ref->created_at)->format('H:i') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-10 px-4 text-center text-slate-500" colspan="4">
                                    Este referidor aún no tiene referidos registrados.
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
                    <span class="font-semibold">{{ $referidos->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-semibold">{{ $referidos->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold">{{ $referidos->total() }}</span>
                    referidos
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="previousPage"
                        wire:loading.attr="disabled"
                        @disabled($referidos->onFirstPage())
                        class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold
                               hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        ← Anterior
                    </button>

                    <div class="text-sm text-slate-600 px-2">
                        Página <span class="font-semibold">{{ $referidos->currentPage() }}</span>
                        de <span class="font-semibold">{{ $referidos->lastPage() }}</span>
                    </div>

                    <button
                        type="button"
                        wire:click="nextPage"
                        wire:loading.attr="disabled"
                        @disabled(!$referidos->hasMorePages())
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