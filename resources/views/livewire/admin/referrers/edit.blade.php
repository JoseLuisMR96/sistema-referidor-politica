<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar referidor</h2>
            <div class="text-sm text-gray-500">Actualiza datos y controla el estado del código</div>
        </div>

        <a
            href="{{ route('referrers.index') }}"
            class="inline-flex items-center justify-center rounded-md border px-4 py-2 text-sm font-semibold
                   hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
        >
            ← Volver
        </a>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

        {{-- Alert éxito --}}
        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                <div class="font-semibold">Cambios guardados</div>
                <div class="text-sm">{{ session('success') }}</div>
            </div>
        @endif

        {{-- Card: Código / Link --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Código</div>
                    <div class="mt-1 font-mono text-lg text-gray-900">{{ $referrer->code }}</div>

                    <div class="mt-4 text-sm font-semibold text-gray-900">Link público</div>
                    <div class="mt-1 font-mono text-xs text-gray-700 break-all">{{ $link }}</div>

                    <div class="mt-2 text-xs text-gray-500">
                        Comparte este enlace para que los usuarios se registren con este referidor.
                    </div>
                </div>

                <button
                    type="button"
                    onclick="navigator.clipboard.writeText(@js($link))"
                    class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-sm
                           hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                    title="Copiar link"
                >
                    📋 Copiar
                </button>
            </div>
        </div>

        {{-- Formulario --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="space-y-5">

                <div>
                    <label class="block text-sm font-semibold text-gray-900">Nombre</label>
                    <div class="mt-1">
                        <input
                            wire:model.live="name"
                            class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Ej: Juan Pérez"
                        />
                    </div>
                    @error('name')
                        <div class="text-sm text-rose-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900">Teléfono</label>
                        <div class="mt-1">
                            <input
                                wire:model.live="phone"
                                class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Ej: 3001234567"
                            />
                        </div>
                        @error('phone')
                            <div class="text-sm text-rose-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900">Email</label>
                        <div class="mt-1">
                            <input
                                wire:model.live="email"
                                class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Ej: correo@dominio.com"
                            />
                        </div>
                        @error('email')
                            <div class="text-sm text-rose-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Estado --}}
                <div class="rounded-lg border p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Estado</div>
                            <div class="text-xs text-gray-500">
                                Si está inactivo, el registro público con este código debe bloquearse.
                            </div>
                        </div>

                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" class="sr-only" wire:model.live="is_active">
                            <span
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition
                                       {{ $is_active ? 'bg-emerald-600' : 'bg-gray-300' }}"
                            >
                                <span
                                    class="inline-block h-5 w-5 transform rounded-full bg-white transition
                                           {{ $is_active ? 'translate-x-5' : 'translate-x-1' }}"
                                ></span>
                            </span>
                            <span class="text-sm font-semibold {{ $is_active ? 'text-emerald-700' : 'text-gray-700' }}">
                                {{ $is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Acciones --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 pt-2 border-t">
                    <a
                        href="{{ route('referrers.index') }}"
                        class="inline-flex items-center justify-center rounded-md border px-4 py-2 text-sm font-semibold
                               hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                    >
                        Cancelar
                    </a>

                    <button
                        type="button"
                        wire:click="update"
                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                               hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2"
                    >
                        💾 Guardar cambios
                    </button>
                </div>

            </div>
        </div>

    </div>
</div>
