<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">
    <x-tenant-requerido accion="enviar campañas" />

    {{-- Header --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">
                Mensajería masiva WhatsApp
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Crea campañas, adjunta media/ubicación y envía en lote con trazabilidad de estados (Twilio).
            </p>
        </div>

        <div class="hidden sm:flex items-center gap-2">
            <span
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Listo para envío
            </span>
        </div>
    </div>

    {{-- Flash --}}
    @if (session('ok'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">
            <div class="flex items-start gap-3">
                <div
                    class="mt-0.5 h-8 w-8 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold">
                    ✓
                </div>
                <div>
                    <div class="text-sm font-semibold">Operación realizada</div>
                    <div class="text-sm">{{ session('ok') }}</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Form --}}
    <form wire:submit.prevent="createAndSend" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left: Settings --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Card: Campaign basics --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <div>
                            <div class="text-sm font-bold text-slate-900">Configuración de campaña</div>
                            <div class="text-xs text-slate-500">
                                Se envía con un template aprobado de Twilio: es lo que WhatsApp exige para escribirle primero a una persona.
                            </div>
                        </div>
                    </div>

                    <div class="p-5 space-y-5">

                        <div>
                            <label class="text-xs font-semibold text-slate-700">Nombre de la campaña (opcional)</label>
                            <input type="text"
                                class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                wire:model="name" placeholder="Ej: Invitación cierre de campaña Villavicencio">
                            <div class="mt-1 text-[11px] text-slate-500">Para reconocerla luego. Vacío = "Campaña Twilio".</div>
                            @error('name')
                                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Template aprobado de Twilio (único modo de envío) --}}
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-bold text-slate-900">Template aprobado (Twilio)</div>

                                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-xs font-semibold text-slate-700">Content SID (HX...)</label>
                                        <input type="text"
                                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                            wire:model="contentSid" placeholder="HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                        @error('contentSid')
                                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="text-xs font-semibold text-slate-700">Messaging Service SID
                                            (MG...) (opcional)</label>
                                        <input type="text"
                                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                            wire:model="messagingServiceSid"
                                            placeholder="MGxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                        <div class="mt-1 text-[11px] text-slate-500">
                                            Vacío = el número del candidato elegido (Tenants) o, si no tiene, el de
                                            <span class="font-mono">.env</span>.
                                        </div>
                                        @error('messagingServiceSid')
                                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="text-xs font-semibold text-slate-700">Variables (JSON)
                                            (opcional)</label>
                                        <input type="text"
                                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                            wire:model="contentVarsJson" placeholder='{"1":"{name}"}'>
                                        @error('contentVarsJson')
                                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                                        @enderror
                                        <div class="mt-1 text-[11px] text-slate-500">Si tu template no usa variables,
                                            déjalo vacío.</div>
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>

                {{-- Card: Recipients --}}
                {{-- Card: Recipients --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <div class="text-sm font-bold text-slate-900">Destinatarios</div>
                        <div class="text-xs text-slate-500">Manual o Excel (nombre, celular)</div>
                    </div>

                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div class="md:col-span-1">
                                <label class="text-xs font-semibold text-slate-700">Origen</label>
                                <select
                                    class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                    wire:model.live="recipientsSource">
                                    <option value="manual">Manual (1 por línea)</option>
                                    <option value="excel">Excel (.xlsx)</option>
                                </select>
                            </div>

                            <div class="md:col-span-2 text-[11px] text-slate-500">
                                En Excel usa columnas: <b>nombre</b> y <b>celular</b> (o “name/phone”). Se aceptan
                                encabezados en mayúsculas/minúsculas.
                            </div>
                        </div>

                        @if ($recipientsSource === 'manual')
                            <div>
                                <div class="text-xs text-slate-500 mb-2">1 por línea: <span
                                        class="font-mono">+57...|Nombre</span></div>
                                <textarea
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                    rows="7" wire:model="recipientsText" placeholder="+573001112233|Jose Mora&#10;+573001112234|Maria"></textarea>

                                @error('recipientsText')
                                    <div class="mt-2 text-xs text-rose-600">{{ $message }}</div>
                                @enderror
                            </div>
                        @else
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Subir Excel de destinatarios
                                        </div>
                                        <div class="text-[11px] text-slate-600">
                                            Formato: columnas <b>nombre</b> y <b>celular</b>. Ej: “Jose”, “3142874901” o
                                            “+573142874901”.
                                        </div>
                                    </div>
                                    <div class="text-[11px] text-slate-500">.xlsx</div>
                                </div>

                                <input type="file"
                                    class="mt-3 block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800"
                                    wire:model="recipientsFile" accept=".xlsx">

                                @error('recipientsFile')
                                    <div class="mt-2 text-xs text-rose-600">{{ $message }}</div>
                                @enderror

                                <div class="mt-2 text-xs text-slate-600" wire:loading wire:target="recipientsFile">
                                    Subiendo archivo…
                                </div>
                            </div>
                        @endif

                        <div class="inline-flex items-center gap-2 text-[11px] text-slate-600">
                            <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                            Formato E.164 recomendado
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: Test / Actions --}}
            <div class="space-y-6">
                {{-- Card: Test mode --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <div class="text-sm font-bold text-slate-900">Control de riesgo</div>
                        <div class="text-xs text-slate-500">Prueba antes de masivo. Evita “blasts” accidentales.</div>
                    </div>

                    <div class="p-5 space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-xs font-semibold text-slate-700">Modo prueba</div>
                                <div class="text-[11px] text-slate-500">Envía solo a 1 número o a primeros N.</div>
                            </div>

                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" class="rounded border-slate-300" wire:model="testMode">
                                <span class="text-xs font-semibold text-slate-700">Activar</span>
                            </label>
                        </div>

                        @if ($testMode)
                            <div>
                                <label class="text-xs font-semibold text-slate-700">Número de prueba</label>
                                <input type="text"
                                    class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                    wire:model="testPhone" placeholder="+573001112233">
                                @error('testPhone')
                                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                                @enderror
                                <div class="mt-1 text-[11px] text-slate-500">Se enviará como <span
                                        class="font-mono">whatsapp:+57…</span></div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Muestra controlada</div>
                                        <div class="text-[11px] text-slate-600">Envío a los primeros N.</div>
                                    </div>

                                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                        <input type="checkbox" class="rounded border-slate-300"
                                            wire:model="testOnlyFirstN">
                                        Usar
                                    </label>
                                </div>

                                <div class="mt-3 flex items-center gap-3">
                                    <input type="number"
                                        class="w-24 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                        wire:model="testN" min="1" max="50"
                                        @disabled(!$testOnlyFirstN)>
                                    <span class="text-[11px] text-slate-500">Máx. 50</span>
                                </div>

                                <div class="mt-2 text-[11px] text-slate-500">
                                    Si activas “primeros N”, se ignora el número de prueba.
                                </div>
                            </div>
                        @else
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                                <div class="text-xs font-bold">Modo prueba desactivado</div>
                                <div class="mt-1 text-[11px]">
                                    Recomendación: envía primero un test antes de masivo. Tu yo del futuro te lo
                                    agradece.
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Card: Action --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <div class="text-sm font-bold text-slate-900">Acciones</div>
                        <div class="text-xs text-slate-500">Dispara la campaña y deja que la cola haga su magia.</div>
                    </div>

                    <div class="p-5 space-y-3">
                        {{-- ✅ Botón como <a> --}}
                        <a href="#" role="button" wire:click.prevent="createAndSend"
                            wire:loading.attr="aria-disabled" wire:target="createAndSend,recipientsFile"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-slate-800 active:bg-slate-950">
                            <span>Enviar campaña</span>
                            <span class="text-white/80">→</span>
                        </a>

                        <div class="text-center text-[11px] text-slate-500">
                            Al enviar, se crean los mensajes y se encolan en <span
                                class="font-mono">queue=whatsapp</span>.
                        </div>

                        <div class="text-sm text-slate-600" wire:loading wire:target="createAndSend,recipientsFile">
                            Procesando… (la cola se encarga del envío)
                        </div>
                    </div>
                </div>

                {{-- Card: Mini checklist --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="px-5 py-4">
                        <div class="text-xs font-bold text-slate-900">Checklist rápido</div>
                        <ul class="mt-2 space-y-2 text-[11px] text-slate-600">
                            <li class="flex gap-2"><span class="mt-0.5 h-2 w-2 rounded-full bg-slate-300"></span>
                                APP_URL correcto</li>
                            <li class="flex gap-2"><span class="mt-0.5 h-2 w-2 rounded-full bg-slate-300"></span>
                                Webhook statusCallback accesible públicamente</li>
                            <li class="flex gap-2"><span class="mt-0.5 h-2 w-2 rounded-full bg-slate-300"></span>
                                Template aprobado por WhatsApp (Content SID HX...)</li>
                            <li class="flex gap-2"><span class="mt-0.5 h-2 w-2 rounded-full bg-slate-300"></span>
                                Worker/cron corriendo cola</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
