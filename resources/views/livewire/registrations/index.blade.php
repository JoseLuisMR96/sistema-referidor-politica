<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-slate-900 leading-tight">Registros</h2>
            <div class="mt-1 text-sm text-slate-500">
                Gestión y seguimiento de registros por referidor
            </div>
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        {{-- Toolbar --}}
        <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-100">
            <div class="p-5">
                <div class="flex flex-col xl:flex-row xl:items-center xl:flex-nowrap gap-3">

                    {{-- Search --}}
                    <div class="w-full xl:flex-1 min-w-0">
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Buscar</label>

                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                🔎
                            </span>

                            <input wire:model.live="search" type="text"
                                class="w-full rounded-xl border-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                   focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Buscar por nombre, documento o teléfono">
                        </div>
                    </div>

                    {{-- Per page --}}
                    <div class="w-full xl:w-[140px]">
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Filas</label>
                        <select wire:model.live="perPage"
                            class="w-full rounded-xl border-slate-200 bg-white py-2.5 text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    {{-- Referrer --}}
                    <div class="w-full xl:w-[320px]">
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Referidor</label>
                        <select wire:model.live="referrer"
                            class="w-full rounded-xl border-slate-200 bg-white py-2.5 text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos los referidores</option>
                            @foreach ($referrers as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Export buttons --}}
                    @can('registros.exportar')
                        <div class="w-full xl:w-auto xl:shrink-0">
                            <label class="block text-[11px] font-semibold text-slate-500 mb-1">Exportar</label>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="{{ route('registrations.export') }}"
                                    class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm
                                           hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">
                                    ⬇️ CSV (Todos)
                                </a>

                                <a href="{{ route(
                                    'registrations.export',
                                    array_filter([
                                        'referrer_id' => $referrer ?: null,
                                        'search' => $search ?: null,
                                    ]),
                                ) }}"
                                    class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm
                                           hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                                    ⬇️ CSV (Filtros)
                                </a>
                            </div>
                        </div>
                    @endcan
                </div>

                {{-- Chips filtros --}}
                @if ($search || $referrer)
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        @if ($search)
                            <span
                                class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-slate-700">
                                Búsqueda:
                                <span class="font-semibold text-slate-900">"{{ $search }}"</span>
                            </span>
                        @endif

                        @if ($referrer)
                            <span
                                class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-slate-700">
                                Referidor:
                                <span class="font-semibold text-slate-900">
                                    {{ optional($referrers->firstWhere('id', (int) $referrer))->name ?? 'Seleccionado' }}
                                </span>
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- DataTable --}}
        <div class="bg-white shadow-sm sm:rounded-2xl border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr
                            class="text-left text-[11px] uppercase tracking-wider text-slate-600 border-b border-slate-200">
                            <th class="py-3.5 px-4">Ciudadano</th>
                            <th class="py-3.5 px-4 hidden md:table-cell">Documento</th>
                            <th class="py-3.5 px-4 hidden lg:table-cell">Teléfono</th>
                            <th class="py-3.5 px-4">Municipio</th>
                            <th class="py-3.5 px-4 hidden md:table-cell">Referidor</th>
                            <th class="py-3.5 px-4 text-right w-[240px]">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse($registrations as $reg)
                            <tr class="hover:bg-slate-50/70 transition-colors odd:bg-white even:bg-slate-50/30">
                                {{-- Ciudadano --}}
                                <td class="py-4 px-4">
                                    <div class="font-semibold text-slate-900 leading-tight">
                                        {{ $reg->full_name }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-500 md:hidden">
                                        {{ $reg->document_type }} {{ $reg->document_number }}
                                        @if ($reg->phone)
                                            <span class="mx-2">•</span>{{ $reg->phone }}
                                        @endif
                                    </div>
                                </td>

                                {{-- Documento --}}
                                <td class="py-4 px-4 hidden md:table-cell">
                                    <div class="text-slate-800">
                                        {{ $reg->document_type }} {{ $reg->document_number }}
                                    </div>
                                </td>

                                {{-- Teléfono --}}
                                <td class="py-4 px-4 hidden lg:table-cell">
                                    <div class="text-slate-800">
                                        {{ $reg->phone ?? '—' }}
                                    </div>
                                </td>

                                {{-- Municipio --}}
                                <td class="py-4 px-4">
                                    @php
                                        $res =
                                            $reg->residenceMunicipality?->nombre ??
                                            ($reg->residence_municipality ?? null);
                                        $vot = $reg->votingMunicipality?->nombre ?? ($reg->voting_municipality ?? null);
                                    @endphp

                                    <div class="text-slate-800">
                                        {{ $res ?: '—' }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        Vota: {{ $vot ?: '—' }}
                                    </div>
                                </td>

                                {{-- Referidor --}}
                                <td class="py-4 px-4 hidden md:table-cell">
                                    <div class="text-slate-800">
                                        {{ $reg->referrer?->name ?? '—' }}
                                    </div>
                                </td>

                                {{-- Acciones --}}
                                <td class="py-4 px-4">
                                    <div class="flex justify-end gap-2">
                                        @role('Administrador')
                                            <a href="{{ route('registrations.edit', $reg->id) }}"
                                                class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                                       hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2">
                                                ✏️ Editar
                                            </a>
                                        @endrole

                                        <a href="{{ route('registrations.show', $reg->id) }}"
                                            class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                                   hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2">
                                            👁️ Ver
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-14 px-4 text-center text-slate-500" colspan="6">
                                    No hay registros con los filtros actuales.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer --}}
            <div
                class="px-4 py-4 border-t border-slate-200 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="text-sm text-slate-600">
                    Mostrando
                    <span class="font-semibold">{{ $registrations->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-semibold">{{ $registrations->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold">{{ $registrations->total() }}</span>
                    registros
                </div>

                <div class="flex items-center gap-2">
                    <button wire:click="previousPage" @disabled(!$registrations->previousPageUrl())
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700
                               hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        type="button">
                        ← Anterior
                    </button>

                    <div class="text-sm text-slate-600 px-2">
                        Página <span class="font-semibold">{{ $registrations->currentPage() }}</span>
                        de <span class="font-semibold">{{ $registrations->lastPage() }}</span>
                    </div>

                    <button wire:click="nextPage" @disabled(!$registrations->nextPageUrl())
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700
                               hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        type="button">
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
