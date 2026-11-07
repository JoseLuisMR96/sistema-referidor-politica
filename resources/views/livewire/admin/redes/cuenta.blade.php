<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $cuenta ? 'Editar cuenta' : 'Nueva cuenta' }}</h2>
            <div class="text-sm text-gray-500">{{ $tenant ? "Redes de {$tenant->nombre}" : 'Elige un candidato' }}</div>
        </div>
        <a href="{{ route('redes.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">← Volver a las cuentas</a>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <x-tenant-requerido accion="registrar cuentas de redes" />

        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold">Tipo de cuenta</label>
                    <select wire:model.live="tipo" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="propia">Del candidato</option>
                        <option value="rival">Rival (otro candidato)</option>
                    </select>
                    @error('tipo') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    @if ($tipo === 'rival')
                        <label class="block text-sm font-semibold">Nombre del rival</label>
                        <input wire:model="etiqueta" class="mt-1 w-full rounded-md border-gray-300" placeholder="Juan Pérez" />
                        @error('etiqueta') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-semibold">Red</label>
                    <select wire:model.live="red" class="mt-1 w-full rounded-md border-gray-300">
                        @foreach ($redes as $r)
                            <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                        @endforeach
                    </select>
                    @if ($tipo === 'rival')
                        <div class="text-xs text-gray-500 mt-1">Los rivales solo se miden en Instagram.</div>
                    @endif
                    @error('red') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold">
                        {{ $red === 'instagram' ? 'Usuario de Instagram' : 'ID o alias de la página' }}
                    </label>
                    <input wire:model="usuario" class="mt-1 w-full rounded-md border-gray-300"
                        placeholder="{{ $red === 'instagram' ? 'camilocfer' : 'CamiloCferOficial' }}" />
                    @error('usuario') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold">URL del perfil</label>
                    <input wire:model="url" class="mt-1 w-full rounded-md border-gray-300" placeholder="https://www.{{ $red }}.com/..." />
                    @error('url') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold">ID en Meta (opcional)</label>
                    <input wire:model="external_id" class="mt-1 w-full rounded-md border-gray-300 font-mono" />
                    <div class="text-xs text-gray-500 mt-1">Se completa solo en la primera sincronización de Instagram.</div>
                    @error('external_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                @if ($tipo !== 'rival')
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold">Token de acceso del candidato (opcional)</label>
                        <input type="password" wire:model="cuenta_token" autocomplete="new-password"
                            class="mt-1 w-full rounded-md border-gray-300 font-mono"
                            placeholder="{{ $cuenta?->tieneAccesoPropio() ? 'Guardado (vacío = conservar el actual)' : 'Cuando el candidato te dé acceso a su página o cuenta' }}" />
                        <div class="text-xs text-gray-500 mt-1">Con el token se leen sus métricas completas y se analizan sus comentarios.</div>
                        @error('cuenta_token') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                @endif
            </div>

            @error('tenant') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

            <div class="flex justify-end gap-2 border-t pt-4">
                <a href="{{ route('redes.index') }}" class="px-4 py-2 rounded-md border text-sm">Cancelar</a>
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar"
                    class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                    {{ $cuenta ? 'Guardar cambios' : 'Agregar cuenta' }}
                </button>
            </div>
        </div>
    </div>
</div>
