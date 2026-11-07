<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cuentas de redes {{ $tenant ? "de {$tenant->nombre}" : '' }}</h2>
            <div class="text-sm text-gray-500">Facebook e Instagram del candidato y de sus rivales. Se sincronizan a diario.</div>
        </div>

        <div class="flex items-center gap-2">
            @if ($esGlobal)
                <a href="{{ route('integraciones.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">Credenciales de Meta</a>
            @endif
            @if ($tenant)
                <a href="{{ route('redes.create') }}"
                    class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                    ➕ Nueva cuenta
                </a>
            @endif
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-4">{{ session('error') }}</div>
        @endif

        @if (! $tenant)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Elige un candidato en el menú lateral para ver y registrar sus cuentas.
            </div>
        @else
            <div class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-col lg:flex-row lg:items-end gap-4">
                <div class="w-full max-w-lg">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Buscar</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
                        <input type="text" wire:model.live.debounce.300ms="search"
                            class="w-full rounded-xl border-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Usuario o nombre del rival">
                    </div>
                </div>
                <div class="text-xs text-gray-500 lg:flex-1">
                    Instagram sin acceso del candidato: solo datos públicos y solo si la cuenta es profesional.
                    Facebook: hace falta el token de su página. Los <strong>rivales</strong> se miden solo en Instagram.
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="py-3 px-4">Cuenta</th>
                            <th class="py-3 px-4">Tipo</th>
                            <th class="py-3 px-4">Acceso</th>
                            <th class="py-3 px-4">Última sincronización</th>
                            <th class="py-3 px-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($cuentas as $c)
                            <tr wire:key="cuenta-{{ $c->id }}" class="{{ $c->activo ? '' : 'opacity-50' }}">
                                <td class="py-3 px-4">
                                    <div class="font-semibold">{{ ucfirst($c->red) }} · {{ $c->usuario }}</div>
                                    @if ($c->url)
                                        <a href="{{ $c->url }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 underline break-all">{{ $c->url }}</a>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    @if ($c->esRival())
                                        <span class="rounded-full bg-orange-100 text-orange-800 px-2 py-0.5 font-semibold">Rival: {{ $c->etiqueta }}</span>
                                    @else
                                        <span class="rounded-full bg-indigo-50 text-indigo-700 px-2 py-0.5 font-semibold">Del candidato</span>
                                    @endif
                                    @unless ($c->activo)
                                        <span class="ml-1 rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 font-semibold">Pausada</span>
                                    @endunless
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    @if ($c->tieneAccesoPropio())
                                        <span class="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 font-semibold">Token del candidato</span>
                                        <button type="button" wire:click="quitarToken({{ $c->id }})" wire:confirm="¿Quitar el token de esta cuenta?" class="ml-1 underline text-gray-500">quitar</button>
                                    @else
                                        <span class="rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 font-semibold">Solo datos públicos</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    {{ $c->ultimo_sync_at?->diffForHumans() ?? 'Nunca' }}
                                    @if ($c->ultimo_error)
                                        <div class="text-rose-600 mt-1">{{ $c->ultimo_error }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" wire:click="sincronizar({{ $c->id }})" wire:loading.attr="disabled" class="px-3 py-1.5 rounded-md border text-xs font-semibold">Sincronizar</button>
                                        @if (! $c->esRival() && $c->tieneAccesoPropio())
                                            <button type="button" wire:click="analizarComentarios({{ $c->id }})" wire:loading.attr="disabled"
                                                class="px-3 py-1.5 rounded-md border text-xs font-semibold">Analizar comentarios</button>
                                        @endif
                                        <a href="{{ route('redes.edit', $c) }}" class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50">Editar</a>
                                        <button type="button" wire:click="alternarCuenta({{ $c->id }})" class="px-3 py-1.5 rounded-md border text-xs font-semibold">
                                            {{ $c->activo ? 'Pausar' : 'Activar' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 px-4 text-center text-gray-500">
                                    {{ $search !== '' ? 'Ninguna cuenta coincide con la búsqueda.' : 'Este candidato no tiene cuentas registradas.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
