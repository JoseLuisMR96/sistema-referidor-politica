<div>
    @if (!$referidor)
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
            <div class="font-semibold">Link inválido</div>
            <div class="text-sm mt-1">
                El enlace de referidor pregonero no es válido. Solicita un enlace correcto.
            </div>
        </div>

    @else
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="w-full max-w-2xl bg-white rounded-2xl shadow p-6 sm:p-8">

                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Registro</h1>
                        <p class="text-sm text-gray-500 mt-1">
                            Completa tus datos. Tu información quedará registrada correctamente.
                        </p>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-gray-500">ID único</div>
                        <div class="font-mono text-sm">{{ $referidor->id_unico ?? '—' }}</div>
                    </div>
                </div>

                <div class="mt-6 rounded-xl border bg-gray-50 p-4">
                    <div class="text-sm text-gray-600">
                        Referido por: <span class="font-semibold text-gray-900">{{ $referidor->nombre }}</span>
                        <span class="text-gray-500">•</span>
                        <span class="text-gray-700">CC {{ $referidor->cedula }}</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        Link público validado por ID único.
                    </div>
                </div>

                @if (session('success'))
                    <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                        <div class="font-semibold">Registro enviado</div>
                        <div class="text-sm mt-1">{{ session('success') }}</div>
                    </div>
                @endif

                <form wire:submit.prevent="guardar" class="mt-6 space-y-5">

                    <div>
                        <label class="block text-sm font-semibold">Nombre completo</label>
                        <input type="text" wire:model.defer="nombre"
                            class="mt-1 w-full rounded-xl border-gray-300"
                            placeholder="Ej: Juan Pérez">
                        @error('nombre')
                            <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold">Cédula</label>
                            <input type="text" wire:model.defer="cedula"
                                class="mt-1 w-full rounded-xl border-gray-300"
                                placeholder="Ej: 1234567890">
                            @error('cedula')
                                <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">Puesto de votación</label>
                            <input type="text" wire:model.defer="puesto_votacion"
                                class="mt-1 w-full rounded-xl border-gray-300"
                                placeholder="Ej: Colegio XYZ - Mesa 12">
                            @error('puesto_votacion')
                                <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @error('general')
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-red-700 text-sm">
                            {{ $message }}
                        </div>
                    @enderror

                    <button type="submit"
                        class="w-full sm:w-auto rounded-xl bg-gray-900 text-white px-5 py-2.5 font-semibold hover:bg-black"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove>Enviar registro</span>
                        <span wire:loading>Enviando...</span>
                    </button>
                </form>

                <div class="mt-8 pt-6 border-t text-xs text-gray-500">
                    {{ config('app.name') }} • Formulario de referido pregonero
                </div>

            </div>
        </div>
    @endif
</div>