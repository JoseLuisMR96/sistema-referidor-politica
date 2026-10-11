<div class="max-w-4xl mx-auto p-6 bg-white rounded-2xl shadow space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Campaña WhatsApp - WPPConnect</h1>
        <p class="text-sm text-gray-500 mt-1">
            Sube un archivo Excel con las columnas <strong>nombre</strong> y <strong>celular</strong>.
        </p>
    </div>

    @if (session()->has('ok'))
        <div class="rounded-lg border border-green-200 bg-green-50 text-green-700 px-4 py-3">
            {{ session('ok') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Nombre de la campaña
                </label>
                <input
                    type="text"
                    wire:model.defer="name"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring focus:ring-indigo-200"
                    placeholder="Ej: Campaña marzo clientes"
                >
                @error('name')
                    <span class="text-sm text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Sesión WPPConnect
                </label>
                <input
                    type="text"
                    wire:model.defer="session"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring focus:ring-indigo-200"
                    placeholder="Ej: cristian1"
                >
                @error('session')
                    <span class="text-sm text-red-600">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Mensaje / plantilla
            </label>
            <textarea
                wire:model.defer="message"
                rows="6"
                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring focus:ring-indigo-200"
                placeholder="Hola {name}, este es un mensaje de prueba."
            ></textarea>
            <p class="text-xs text-gray-500 mt-1">
                Usa <strong>{name}</strong> para reemplazar automáticamente el nombre del contacto.
            </p>
            @error('message')
                <span class="text-sm text-red-600">{{ $message }}</span>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Imagen de campaña
            </label>
            <input
                type="file"
                wire:model="image"
                accept="image/*"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 bg-white"
            >
            <p class="text-xs text-gray-500 mt-1">
                Sube la imagen principal que se enviará con la campaña.
            </p>
            @error('image')
                <span class="text-sm text-red-600">{{ $message }}</span>
            @enderror
        
            <div wire:loading wire:target="image" class="text-sm text-indigo-600 mt-2">
                Cargando imagen...
            </div>
        
            @if ($image)
                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">Vista previa de la imagen</p>
                    <div class="rounded-xl overflow-hidden border border-gray-200 bg-gray-50 max-w-sm">
                        <img
                            src="{{ $image->temporaryUrl() }}"
                            alt="Vista previa"
                            class="w-full h-auto object-cover"
                        >
                    </div>
                </div>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Archivo Excel (.xlsx)
            </label>
            <input
                type="file"
                wire:model="file"
                accept=".xlsx"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 bg-white"
            >
            <p class="text-xs text-gray-500 mt-1">
                El archivo debe tener encabezados: <strong>nombre</strong> y <strong>celular</strong>.
            </p>
            @error('file')
                <span class="text-sm text-red-600">{{ $message }}</span>
            @enderror

            <div wire:loading wire:target="file" class="text-sm text-indigo-600 mt-2">
                Cargando archivo...
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Formato esperado del Excel</h3>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-300">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-left">nombre</th>
                            <th class="border border-gray-300 px-3 py-2 text-left">celular</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="border border-gray-300 px-3 py-2">Cristian Ovalle</td>
                            <td class="border border-gray-300 px-3 py-2">3142874901</td>
                        </tr>
                        <tr>
                            <td class="border border-gray-300 px-3 py-2">Laura Ovalle</td>
                            <td class="border border-gray-300 px-3 py-2">3001234567</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if (!empty($preview))
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700">Vista previa de contactos</h3>
                    <span class="text-xs text-gray-500">Mostrando {{ count($preview) }} registros</span>
                </div>

                <div class="overflow-x-auto max-h-72 overflow-y-auto border rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Nombre</th>
                                <th class="px-3 py-2 text-left">Celular</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview as $i => $row)
                                <tr class="border-t">
                                    <td class="px-3 py-2">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2">{{ $row['name'] ?? '' }}</td>
                                    <td class="px-3 py-2">{{ $row['phone'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="flex items-center gap-3">
            <button
                type="submit"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-white font-medium hover:bg-indigo-700 disabled:opacity-50"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="save">Crear campaña y enviar</span>
                <span wire:loading wire:target="save">Procesando...</span>
            </button>

            <button
                type="button"
                wire:click="previewExcel"
                class="inline-flex items-center rounded-lg bg-gray-200 px-5 py-2.5 text-gray-800 font-medium hover:bg-gray-300"
                wire:loading.attr="disabled"
            >
                Vista previa
            </button>
        </div>
    </form>
</div>