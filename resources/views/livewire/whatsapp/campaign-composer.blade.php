<div class="max-w-4xl mx-auto p-6">
    <div class="mb-4">
        <h2 class="text-xl font-semibold">Mensajería masiva WhatsApp</h2>
        <p class="text-sm text-gray-600">Crea una campaña, adjunta media/ubicación y envía en lote con trazabilidad de
            estados.</p>
    </div>

    @if (session('ok'))
        <div class="p-3 rounded bg-green-50 text-green-800 mb-4">{{ session('ok') }}</div>
    @endif

    <div class="mt-2 p-4 rounded border bg-white">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="text-sm font-semibold">Modo prueba (recomendado antes del envío masivo)</div>
                <div class="text-xs text-gray-600">
                    Envía a tu número de prueba o a los primeros N destinatarios para validar plantilla, media y link.
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" class="rounded border-gray-300" wire:model="testMode">
                <span>Activar</span>
            </label>
        </div>

        @if ($testMode)
            <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="text-xs font-medium">Número de prueba</label>
                    <input type="text" class="w-full border rounded p-2" wire:model="testPhone"
                        placeholder="+573001112233">
                    <div class="text-[11px] text-gray-500 mt-1">Se enviará como whatsapp:+57… automáticamente.</div>
                    @error('testPhone')
                        <div class="text-red-600 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs font-medium">Alternativa: enviar solo a los primeros N de la lista</label>
                    <div class="flex items-center gap-3 mt-1">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" class="rounded border-gray-300" wire:model="testOnlyFirstN">
                            <span>Usar primeros N</span>
                        </label>

                        <input type="number" class="w-24 border rounded p-2" wire:model="testN" min="1"
                            max="50" @disabled(!$testOnlyFirstN)>
                        <span class="text-xs text-gray-500">Máx. 50</span>
                    </div>

                    <div class="text-[11px] text-gray-500 mt-1">
                        Si activas “primeros N”, se ignora el número de prueba y se envía a una muestra controlada.
                    </div>
                </div>
            </div>

            <div class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-3">
                Tip: si Twilio te pide “verified caller / sandbox”, asegúrate de que tu número esté autorizado en tu
                consola de Twilio
                (especialmente en entornos de prueba).
            </div>
        @endif
    </div>


    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
            <label class="text-sm font-medium">Nombre de campaña</label>
            <input type="text" class="w-full border rounded p-2" wire:model="name">
            @error('name')
                <div class="text-red-600 text-sm">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label class="text-sm font-medium">Tipo</label>
            <select class="w-full border rounded p-2" wire:model="type">
                <option value="media">Imagen/Video + Texto</option>
                <option value="text">Solo texto</option>
                <option value="location">Texto + Ubicación (link)</option>
            </select>
            @error('type')
                <div class="text-red-600 text-sm">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="mt-4">
        <label class="text-sm font-medium">Mensaje (usa {name} para personalizar)</label>
        <textarea class="w-full border rounded p-2" rows="5" wire:model="body"></textarea>
        @error('body')
            <div class="text-red-600 text-sm">{{ $message }}</div>
        @enderror
        <div class="text-xs text-gray-500 mt-1">Ejemplo: "Hola {name}, te esperamos..."</div>
    </div>

    @if ($type === 'media')
        <div class="mt-4">
            <label class="text-sm font-medium">Adjuntar imagen o video</label>
            <input type="file" class="w-full" wire:model="mediaFile" accept="image/*,video/*">
            @error('mediaFile')
                <div class="text-red-600 text-sm">{{ $message }}</div>
            @enderror
            <div class="text-xs text-gray-500 mt-1">El archivo se sube a Storage público para que Twilio lo pueda leer.
            </div>
        </div>
    @endif

    @if ($type === 'location')
        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="text-sm font-medium">Etiqueta (opcional)</label>
                <input type="text" class="w-full border rounded p-2" wire:model="location_label"
                    placeholder="Parque X">
            </div>
            <div>
                <label class="text-sm font-medium">Latitud</label>
                <input type="text" class="w-full border rounded p-2" wire:model="location_lat"
                    placeholder="4.1423456">
            </div>
            <div>
                <label class="text-sm font-medium">Longitud</label>
                <input type="text" class="w-full border rounded p-2" wire:model="location_lng"
                    placeholder="-73.6354321">
            </div>
            <div class="md:col-span-3 text-xs text-gray-500">
                Se enviará un enlace tipo Google Maps. Si quieres que se vea como botón real, toca usar Template
                aprobado con botón URL.
            </div>
        </div>
    @endif

    <div class="mt-6">
        <label class="text-sm font-medium">Destinatarios (1 por línea: +57...|Nombre)</label>
        <textarea class="w-full border rounded p-2 font-mono" rows="6" wire:model="recipientsText"
            placeholder="+573001112233|Jose Mora&#10;+573001112234|Maria"></textarea>
        @error('recipientsText')
            <div class="text-red-600 text-sm">{{ $message }}</div>
        @enderror
        <div class="text-xs text-gray-500 mt-1">Esto te permite cargar rápido. Luego si quieres lo conectamos a tu tabla
            de contactos.</div>
    </div>

    <div class="mt-6 flex gap-3">
        <button class="px-4 py-2 rounded bg-black text-white" wire:click="createAndSend" wire:loading.attr="disabled">
            Enviar campaña
        </button>
        <span class="text-sm text-gray-600" wire:loading>Procesando… (la cola se encarga del envío)</span>
    </div>
</div>
