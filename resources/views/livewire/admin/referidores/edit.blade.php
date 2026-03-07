<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Referidor Pregonero</h2>
            <div class="text-sm text-gray-500">
                {{ $referidor->nombre }} • CC {{ $referidor->cedula }}
                • ID único: <span class="font-mono font-semibold">{{ $referidor->id_unico }}</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('pregoneros.referidores.show', ['referidor' => $referidor->id]) }}"
               class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold
                      hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2">
                ← Volver
            </a>
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                <div class="font-semibold">Actualización exitosa</div>
                <div class="text-sm mt-1">{{ session('success') }}</div>
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">

            <div>
                <h3 class="text-sm font-semibold text-slate-800">Datos base</h3>
                <p class="text-xs text-slate-500 mt-1">Campos obligatorios para identificar al referidor.</p>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700">Nombre</label>
                    <input wire:model.live="nombre"
                           class="mt-1 w-full rounded-xl border-slate-200 bg-white px-3 py-2.5 text-sm
                                  focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('nombre') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Cédula</label>
                    <input wire:model.live="cedula"
                           class="mt-1 w-full rounded-xl border-slate-200 bg-white px-3 py-2.5 text-sm font-mono
                                  focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('cedula') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                {{-- ✅ NUEVO: Celular --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700">Celular</label>
                    <input wire:model.live="celular" type="text"
                           placeholder="Ej: 3001234567"
                           class="mt-1 w-full rounded-xl border-slate-200 bg-white px-3 py-2.5 text-sm
                                  focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('celular') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Puesto de votación</label>
                    <input wire:model.live="puesto_votacion"
                           class="mt-1 w-full rounded-xl border-slate-200 bg-white px-3 py-2.5 text-sm
                                  focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('puesto_votacion') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="border-t pt-6">
                <h3 class="text-sm font-semibold text-slate-800">Pago (opcional)</h3>
                <p class="text-xs text-slate-500 mt-1">Si no aplica, puedes dejarlo en blanco.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Monto a pagar</label>
                        <input wire:model.live="monto_pagar" type="number" step="0.01"
                               class="mt-1 w-full rounded-xl border-slate-200 bg-white px-3 py-2.5 text-sm
                                      focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('monto_pagar') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Pago realizado</label>
                        <select wire:model.live="pago_realizado"
                                class="mt-1 w-full rounded-xl border-slate-200 bg-white px-3 py-2.5 text-sm
                                       focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Sin definir</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                        @error('pago_realizado') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Hora del pago</label>
                        <input wire:model.live="hora_pago" type="datetime-local"
                               class="mt-1 w-full rounded-xl border-slate-200 bg-white px-3 py-2.5 text-sm
                                      focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('hora_pago') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Imagen del pago (ruta/url)</label>
                        <input wire:model.live="imagen_pago" type="text"
                               placeholder="storage/pagos/xxx.jpg o URL"
                               class="mt-1 w-full rounded-xl border-slate-200 bg-white px-3 py-2.5 text-sm
                                      focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('imagen_pago') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2">
                <div class="flex items-center gap-2 justify-end">
                    <a href="{{ route('pregoneros.referidores.index') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold
                              hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2">
                        Cancelar
                    </a>

                    <button wire:click="save"
                            class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm
                                   hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                            wire:loading.attr="disabled">
                        💾 Guardar cambios
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>