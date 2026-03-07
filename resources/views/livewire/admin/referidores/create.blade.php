<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear referidor pregonero</h2>
</x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">

            <div>
                <label class="block text-sm font-semibold">Nombre</label>
                <input wire:model.live="nombre" class="w-full rounded-md border-gray-300" />
                @error('nombre') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold">Cédula</label>
                <input wire:model.live="cedula" class="w-full rounded-md border-gray-300" />
                @error('cedula') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold">Celular</label>
                <input wire:model.live="celular"
                       class="w-full rounded-md border-gray-300"
                       placeholder="Ej: 3001234567" />
                @error('celular') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold">Puesto de votación</label>
                <input wire:model.live="puesto_votacion" class="w-full rounded-md border-gray-300" />
                @error('puesto_votacion') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="pt-2 border-t">
                <div class="text-sm font-semibold text-gray-800">Pago (opcional)</div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                    <div>
                        <label class="block text-sm font-semibold">Monto a pagar</label>
                        <input type="number" step="0.01" wire:model.live="monto_pagar"
                               class="w-full rounded-md border-gray-300" />
                        @error('monto_pagar') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Pago realizado</label>
                        <select wire:model.live="pago_realizado" class="w-full rounded-md border-gray-300">
                            <option value="">Sin definir</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                        @error('pago_realizado') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Hora del pago</label>
                        <input type="datetime-local" wire:model.live="hora_pago"
                               class="w-full rounded-md border-gray-300" />
                        @error('hora_pago') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Imagen del pago (ruta/url)</label>
                        <input type="text" wire:model.live="imagen_pago"
                               placeholder="storage/pagos/xxx.jpg o URL"
                               class="w-full rounded-md border-gray-300" />
                        @error('imagen_pago') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('pregoneros.referidores.index') }}"
                   class="px-4 py-2 rounded-md border text-sm">
                    Volver
                </a>

                <button wire:click="guardar"
                        class="px-4 py-2 rounded-md bg-gray-800 text-white text-sm"
                        wire:loading.attr="disabled">
                    <span>Guardar</span>
                </button>
            </div>

        </div>
    </div>
</div>