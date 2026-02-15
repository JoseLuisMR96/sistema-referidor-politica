<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Usuarios</h2>
            <div class="text-xs text-gray-500">Gestión de cuentas y acceso</div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                Total: {{ $users->total() }}
            </span>

            @can('usuarios.crear')
                <a
                    href="{{ route('users.create') }}"
                    class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm
                           hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                >
                    ➕ Crear usuario
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
                            wire:model.live="search"
                            type="text"
                            class="w-full rounded-xl border-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Buscar por nombre o email"
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
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                {{-- Limpiar --}}
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
                            <th class="py-3 px-4">Usuario</th>
                            <th class="py-3 px-4">Email</th>
                            <th class="py-3 px-4">Rol(es)</th>
                            <th class="py-3 px-4 text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        @forelse($users as $u)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-900">{{ $u->name }}</div>
                                    <div class="text-xs text-slate-500">
                                        ID: <span class="font-mono">{{ $u->id }}</span>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="font-mono text-xs text-slate-700 break-all">{{ $u->email }}</div>
                                </td>

                                <td class="py-3 px-4">
                                    @php
                                        $roles = method_exists($u, 'getRoleNames') ? $u->getRoleNames() : collect();
                                    @endphp

                                    @if($roles->count())
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($roles as $r)
                                                @php
                                                    $rLower = strtolower($r);
                                                    $badge = 'bg-slate-100 text-slate-700 border-slate-200';
                                                    if ($rLower === 'administrador') $badge = 'bg-indigo-50 text-indigo-700 border-indigo-200';
                                                    elseif ($rLower === 'operador') $badge = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                                    elseif ($rLower === 'analista') $badge = 'bg-slate-100 text-slate-700 border-slate-200';
                                                @endphp

                                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $badge }}">
                                                    {{ $r }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-500">Sin rol</span>
                                    @endif
                                </td>

                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="inline-flex gap-2">
                                        @can('usuarios.editar')
                                            <a
                                                href="{{ route('users.edit', $u->id) }}"
                                                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                                       hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2"
                                            >
                                                ✏️ Editar
                                            </a>
                                        @endcan

                                        @can('usuarios.eliminar')
                                            <button
                                                type="button"
                                                wire:click="$dispatch('confirm-delete-user', { id: {{ $u->id }} })"
                                                class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                                       hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2"
                                            >
                                                🗑️ Eliminar
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-10 px-4 text-center text-slate-500" colspan="4">
                                    No hay usuarios para mostrar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer paginación --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 border-t bg-white">
                <div class="text-sm text-slate-600">
                    Mostrando
                    <span class="font-semibold">{{ $users->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-semibold">{{ $users->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold">{{ $users->total() }}</span>
                    usuarios
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="previousPage"
                        wire:loading.attr="disabled"
                        @disabled($users->onFirstPage())
                        class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold
                               hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        ← Anterior
                    </button>

                    <div class="text-sm text-slate-600 px-2">
                        Página <span class="font-semibold">{{ $users->currentPage() }}</span>
                        de <span class="font-semibold">{{ $users->lastPage() }}</span>
                    </div>

                    <button
                        type="button"
                        wire:click="nextPage"
                        wire:loading.attr="disabled"
                        @disabled(!$users->hasMorePages())
                        class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold
                               hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Siguiente →
                    </button>

                    <div class="hidden md:block">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
