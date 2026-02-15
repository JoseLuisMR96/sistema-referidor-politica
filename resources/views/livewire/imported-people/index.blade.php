<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Personas importadas</h2>
            <div class="text-sm text-gray-500">
                Datos cargados desde Excel/CSV • sin mezclar con referidos
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                Total: {{ $rows->total() }}
            </span>
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        {{-- Toolbar --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-end">

                {{-- Search --}}
                <div class="xl:col-span-5 min-w-0">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Buscar</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
                        <input
                            wire:model.live="search"
                            type="text"
                            class="w-full rounded-xl border-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Nombre, cédula, teléfono, puesto, municipio o batch"
                        >
                    </div>
                </div>

                {{-- Batch --}}
                <div class="xl:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Batch</label>
                    <select
                        wire:model.live="batch"
                        class="w-full rounded-xl border-slate-200 bg-white py-2.5 text-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Todos</option>
                        @foreach ($batches as $b)
                            <option value="{{ $b }}">{{ $b }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Municipio --}}
                <div class="xl:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Municipio (votación)</label>
                    <select
                        wire:model.live="municipality"
                        class="w-full rounded-xl border-slate-200 bg-white py-2.5 text-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Todos</option>
                        @foreach ($municipalities as $m)
                            <option value="{{ $m }}">{{ $m }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- PerPage --}}
                <div class="xl:col-span-1">
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

                {{-- Clear --}}
                <div class="xl:col-span-2">
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="w-full inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold
                               hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2"
                    >
                        Limpiar filtros
                    </button>
                </div>

            </div>

            {{-- Chips filtros activos --}}
            @if($search || $batch || $municipality)
                <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-600">
                    @if($search)
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1">
                            Búsqueda: <span class="font-semibold text-slate-800">"{{ $search }}"</span>
                        </span>
                    @endif

                    @if($batch)
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1">
                            Batch: <span class="font-semibold text-slate-800">{{ $batch }}</span>
                        </span>
                    @endif

                    @if($municipality)
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1">
                            Municipio: <span class="font-semibold text-slate-800">{{ $municipality }}</span>
                        </span>
                    @endif
                </div>
            @endif
        </div>

        {{-- DataTable --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs uppercase tracking-wider text-slate-600 border-b">
                            <th class="py-3 px-4">Persona</th>
                            <th class="py-3 px-4">Cédula</th>
                            <th class="py-3 px-4 hidden lg:table-cell">Teléfono</th>
                            <th class="py-3 px-4 hidden md:table-cell">Puesto</th>
                            <th class="py-3 px-4">Municipio</th>
                            <th class="py-3 px-4 hidden xl:table-cell">Batch</th>
                            <th class="py-3 px-4 text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        @forelse($rows as $row)
                            <tr class="hover:bg-slate-50 transition-colors">
                                {{-- Persona --}}
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-900">{{ $row->full_name }}</div>
                                    <div class="text-xs text-slate-500">
                                        ID #{{ $row->id }}
                                        @if($row->batch_id)
                                            <span class="mx-2">•</span>
                                            <span class="font-mono">{{ $row->batch_id }}</span>
                                        @endif
                                    </div>

                                    {{-- info compacta en móvil --}}
                                    <div class="mt-1 text-xs text-slate-500 lg:hidden">
                                        <span class="font-semibold">Tel:</span> {{ $row->phone ?? '—' }}
                                        <span class="mx-2">•</span>
                                        <span class="font-semibold">Puesto:</span> {{ $row->voting_place ?? '—' }}
                                    </div>
                                </td>

                                {{-- Cédula --}}
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                                        {{ $row->document_number }}
                                    </span>
                                </td>

                                {{-- Teléfono --}}
                                <td class="py-3 px-4 hidden lg:table-cell">
                                    @if($row->phone)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            {{ $row->phone }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                                            Sin teléfono
                                        </span>
                                    @endif
                                </td>

                                {{-- Puesto --}}
                                <td class="py-3 px-4 hidden md:table-cell text-slate-700">
                                    {{ $row->voting_place ?? '—' }}
                                </td>

                                {{-- Municipio --}}
                                <td class="py-3 px-4">
                                    @if($row->voting_municipality)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                            {{ $row->voting_municipality }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Batch (desktop) --}}
                                <td class="py-3 px-4 hidden xl:table-cell">
                                    @if($row->batch_id)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 font-mono">
                                            {{ $row->batch_id }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Acciones --}}
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <a
                                        href="{{ route('imported-people.show', $row->id) }}"
                                        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                               hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2"
                                    >
                                        👁️ Ver
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-10 px-4 text-center text-slate-500" colspan="7">
                                    No hay registros con los filtros actuales.
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
                    <span class="font-semibold">{{ $rows->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-semibold">{{ $rows->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold">{{ $rows->total() }}</span>
                    personas
                </div>

                <div class="flex items-center gap-2">
                    <button
                        wire:click="previousPage"
                        wire:loading.attr="disabled"
                        @disabled($rows->onFirstPage())
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold
                               hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        ← Anterior
                    </button>

                    <div class="text-sm text-slate-600 px-2">
                        Página <span class="font-semibold">{{ $rows->currentPage() }}</span>
                        de <span class="font-semibold">{{ $rows->lastPage() }}</span>
                    </div>

                    <button
                        wire:click="nextPage"
                        wire:loading.attr="disabled"
                        @disabled(!$rows->hasMorePages())
                        type="button"
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
