<x-slot name="header">
    <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tenants</h2>
        <div class="text-sm text-gray-500">
            Cada tenant es un candidato o campaña. Sus referidores, registros, pregoneros y envíos de WhatsApp quedan separados.
        </div>
    </div>
</x-slot>

<div>
    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Formulario --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="text-lg font-semibold text-gray-900">
                    {{ $editingId ? 'Editar tenant' : 'Nuevo tenant' }}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold">Nombre del candidato</label>
                        <input wire:model="nombre" class="mt-1 w-full rounded-md border-gray-300" placeholder="Camilo Cfer" />
                        @error('nombre') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Descripción</label>
                        <input wire:model="descripcion" class="mt-1 w-full rounded-md border-gray-300" placeholder="Postulado al Concejo municipal" />
                        @error('descripcion') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Messaging Service SID de Twilio (opcional)</label>
                        <input wire:model="twilio_messaging_service_sid" class="mt-1 w-full rounded-md border-gray-300 font-mono" placeholder="MG..." />
                        <div class="text-xs text-gray-500 mt-1">El número desde el que salen sus WhatsApp. Vacío = el de la configuración general.</div>
                        @error('twilio_messaging_service_sid') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="flex items-center gap-2 sm:mt-7">
                        <input type="checkbox" wire:model="activo" id="tenant-activo" />
                        <label for="tenant-activo" class="text-sm">Activo</label>
                        <span class="text-xs text-gray-500">(inactivo: sus usuarios restringidos no pueden entrar)</span>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    @if ($editingId)
                        <button type="button" wire:click="cancelar" class="px-4 py-2 rounded-md border text-sm">Cancelar</button>
                    @endif
                    <button type="button" wire:click="guardar" class="px-4 py-2 rounded-md bg-gray-800 text-white text-sm">
                        {{ $editingId ? 'Guardar cambios' : 'Crear tenant' }}
                    </button>
                </div>
            </div>

            {{-- Listado --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="py-3 px-4">Candidato</th>
                            <th class="py-3 px-4 text-right">Registros</th>
                            <th class="py-3 px-4 text-right">Pregoneros</th>
                            <th class="py-3 px-4 text-right">Campañas</th>
                            <th class="py-3 px-4 text-right">Usuarios</th>
                            <th class="py-3 px-4">Estado</th>
                            <th class="py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($tenants as $t)
                            <tr wire:key="tenant-{{ $t->id }}">
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-gray-900">{{ $t->nombre }}</div>
                                    <div class="text-xs text-gray-500">{{ $t->descripcion ?: '—' }}</div>
                                    <div class="text-xs text-gray-400 font-mono">{{ $t->slug }}</div>
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
                                    <button type="button" wire:click="editar({{ $t->id }})" class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50">
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 px-4 text-center text-gray-500">Todavía no hay tenants.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
