<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Referidores Pregoneros</h2>
            <div class="text-sm text-gray-500">Gestión de referidores, enlaces públicos y referidos por ID único</div>
        </div>

        <div class="flex items-center gap-2">
            <span
                class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                Total: {{ $referidores->total() }}
            </span>

            @can('pregoneros_referidores.exportar_masivo')
                <a href="{{ route('pregoneros.referidores.export_masivo') }}"
                    class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm
              hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">
                    ⬇️ Excel Masivo
                </a>
            @endcan

            @can('pregoneros_referidores.crear')
                <a href="{{ route('pregoneros.referidores.create') }}"
                    class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm
                           hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                    ➕ Crear
                </a>
            @endcan
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-screen-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

        {{-- Toolbar --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">

                {{-- Search --}}
                <div class="lg:col-span-8 min-w-0">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Buscar</label>
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
                        <input type="text" wire:model.live="search"
                            class="w-full rounded-xl border-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Buscar por nombre, cédula, puesto o ID único">
                    </div>
                </div>

                {{-- PerPage --}}
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Filas</label>
                    <select wire:model.live="perPage"
                        class="w-full rounded-xl border-slate-200 bg-white py-2.5 text-sm
                               focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                {{-- Clear --}}
                <div class="lg:col-span-2">
                    <button type="button" wire:click="$set('search','')"
                        class="w-full inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold
                               hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2">
                        Limpiar
                    </button>
                </div>
            </div>

            {{-- Chips filtros activos --}}
            @if ($search)
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
                            <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('nombre')">Referidor</th>
                            <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('cedula')">Cédula</th>
                            <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('celular')">Celular</th>
                            <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('puesto_votacion')">Puesto</th>
                            <th class="py-3 px-4 text-center">Referidos</th>
                            <th class="py-3 px-4 hidden md:table-cell">Link público</th>
                            <th class="py-3 px-4 text-right cursor-pointer" wire:click="sortBy('created_at')">Creado
                            </th>
                            <th class="py-3 px-4 text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        @forelse($referidores as $r)
                            @php
                                $publicUrl = route('public.referir', ['id_unico' => $r->id_unico]);
                                $canShow = auth()->user()->can('pregoneros_referidos.ver');

                                $telRaw = (string) ($r->celular ?? '');
                                $tel = preg_replace('/[^0-9+]/', '', $telRaw);

                                if ($tel !== '' && $tel[0] !== '+' && strlen($tel) === 10) {
                                    $tel = '+57' . $tel;
                                }
                            @endphp

                            <tr class="hover:bg-slate-50 transition-colors">

                                {{-- Referidor --}}
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-900">
                                        @if ($canShow)
                                            <a href="{{ route('pregoneros.referidores.show', ['referidor' => $r->id]) }}"
                                                class="text-indigo-700 hover:text-indigo-800">
                                                {{ $r->nombre }}
                                            </a>
                                        @else
                                            {{ $r->nombre }}
                                        @endif
                                    </div>

                                    <div class="text-xs text-slate-500 mt-0.5">
                                        ID único: <span class="font-mono">{{ $r->id_unico }}</span>
                                    </div>

                                    {{-- En móvil mostramos el link acá (igual a tu módulo) --}}
                                    <div class="text-xs text-slate-500 md:hidden break-all mt-1">
                                        {{ $publicUrl }}
                                    </div>
                                </td>

                                {{-- Cédula --}}
                                <td class="py-3 px-4">
                                    <span class="font-mono text-xs text-slate-700">{{ $r->cedula }}</span>
                                </td>
                                {{-- Celular --}}
                                <td class="py-3 px-4">
                                    @if (!empty($r->celular))
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs text-slate-700">{{ $r->celular }}</span>

                                            @if (!empty($tel))
                                                <a href="tel:{{ $tel }}"
                                                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold
                          hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2"
                                                    title="Llamar a {{ $r->celular }}">
                                                    📞 Llamar
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Puesto --}}
                                <td class="py-3 px-4">
                                    <div class="text-slate-800">{{ $r->puesto_votacion }}</div>
                                </td>

                                {{-- Referidos --}}
                                <td class="py-3 px-4 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                                        {{ $r->referidos_count }}
                                    </span>
                                </td>

                                {{-- Link --}}
                                <td class="py-3 px-4 hidden md:table-cell">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs text-slate-600 break-all">
                                            {{ $publicUrl }}
                                        </span>

                                        <button type="button"
                                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold
                                                   hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2"
                                            onclick="navigator.clipboard.writeText(@js($publicUrl))"
                                            title="Copiar link">
                                            📋 Copiar
                                        </button>
                                    </div>
                                </td>

                                {{-- Creado --}}
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="text-sm text-slate-700">
                                        {{ optional($r->created_at)->format('Y-m-d') }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ optional($r->created_at)->format('H:i') }}
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        @can('pregoneros_referidos.ver')
                                            <a href="{{ route('pregoneros.referidores.show', ['referidor' => $r->id]) }}"
                                                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                       hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2">
                                                👀 Ver
                                            </a>
                                        @endcan

                                        @can('pregoneros_referidores.editar')
                                            <a href="{{ route('pregoneros.referidores.edit', ['referidor' => $r->id]) }}"
                                                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
              hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2">
                                                ✏️ Editar
                                            </a>
                                        @endcan
                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td class="py-10 px-4 text-center text-slate-500" colspan="6">
                                    No hay referidores pregoneros con los filtros actuales.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer paginación (igual a tu módulo) --}}
            <div class="px-4 py-4 border-t bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="text-sm text-slate-600">
                    Mostrando
                    <span class="font-semibold">{{ $referidores->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-semibold">{{ $referidores->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold">{{ $referidores->total() }}</span>
                    referidores
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" wire:click="previousPage" wire:loading.attr="disabled"
                        @disabled($referidores->onFirstPage())
                        class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold
                               hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        ← Anterior
                    </button>

                    <div class="text-sm text-slate-600 px-2">
                        Página <span class="font-semibold">{{ $referidores->currentPage() }}</span>
                        de <span class="font-semibold">{{ $referidores->lastPage() }}</span>
                    </div>

                    <button type="button" wire:click="nextPage" wire:loading.attr="disabled"
                        @disabled(!$referidores->hasMorePages())
                        class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold
                               hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
