    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear referidor</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold">Nombre</label>
                    <input wire:model.live="name" class="w-full rounded-md border-gray-300" />
                    @error('name') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold">Teléfono (opcional)</label>
                    <input wire:model.live="phone" class="w-full rounded-md border-gray-300" />
                    @error('phone') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold">Email (opcional)</label>
                    <input wire:model.live="email" class="w-full rounded-md border-gray-300" />
                    @error('email') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" wire:model.live="is_active" />
                    <span class="text-sm">Activo</span>
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('referrers.index') }}" class="px-4 py-2 rounded-md border text-sm">Volver</a>
                    <button wire:click="save" class="px-4 py-2 rounded-md bg-gray-800 text-white text-sm">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
