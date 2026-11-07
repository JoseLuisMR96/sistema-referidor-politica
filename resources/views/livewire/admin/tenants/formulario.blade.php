<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $tenant ? 'Editar candidato' : 'Nuevo candidato' }}</h2>
            <div class="text-sm text-gray-500">{{ $tenant ? $tenant->nombre : 'Datos, circunscripción e identidad visual' }}</div>
        </div>
        <a href="{{ route('tenants.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">← Volver a candidatos</a>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
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
                        <label class="block text-sm font-semibold">Cargo al que se postula</label>
                        <select wire:model.live="cargo" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">— Elige el cargo —</option>
                            @foreach ($cargos as $c)
                                <option value="{{ $c->value }}">{{ $c->label() }} ({{ $c->ambito() }})</option>
                            @endforeach
                        </select>
                        @error('cargo') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @if ($pideDepartamento)
                            <div>
                                <label class="block text-sm font-semibold">Departamento</label>
                                <select wire:model.live="departamento_id" class="mt-1 w-full rounded-md border-gray-300">
                                    <option value="">— Departamento —</option>
                                    @foreach ($departamentos as $d)
                                        <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('departamento_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        @if ($pideMunicipio)
                            <div>
                                <label class="block text-sm font-semibold">Municipio</label>
                                <select wire:model="municipio_id" class="mt-1 w-full rounded-md border-gray-300" @disabled(! $departamento_id)>
                                    <option value="">— Municipio —</option>
                                    @foreach ($municipios as $m)
                                        <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('municipio_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        @if ($cargo !== '' && ! $pideDepartamento)
                            <div class="text-xs text-gray-500 sm:col-span-2 sm:mt-7">Cargo nacional: no se registra lugar.</div>
                        @endif
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

                {{-- ===== Identidad: va en la cabecera de sus formularios de registro ===== --}}
                <div class="border-t pt-4 space-y-4">
                    <div>
                        <div class="font-semibold text-gray-900">Identidad</div>
                        <div class="text-xs text-gray-500">Va en la cabecera de sus formularios de registro. Usa el material oficial de la campaña.</div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold">Partido o movimiento</label>
                            <input wire:model.live.debounce.400ms="partido" class="mt-1 w-full rounded-md border-gray-300" />
                            @error('partido') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">Slogan</label>
                            <input wire:model.live.debounce.400ms="slogan" class="mt-1 w-full rounded-md border-gray-300" />
                            @error('slogan') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">Logo del partido o campaña</label>
                            <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm">
                            <div class="text-xs text-gray-500 mt-1">PNG, JPG o WEBP, máx. 2 MB. Mejor con fondo transparente.</div>
                            @error('logo') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                            @if (! $logo && $tenant?->logo_path)
                                <button type="button" wire:click="quitarArchivo('logo')" wire:confirm="¿Quitar el logo actual?" class="mt-1 text-xs text-rose-600 underline">Quitar logo actual</button>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">Foto del candidato</label>
                            <input type="file" wire:model="foto" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm">
                            <div class="text-xs text-gray-500 mt-1">PNG, JPG o WEBP, máx. 4 MB. Se muestra en círculo: mejor un retrato centrado.</div>
                            @error('foto') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                            @if (! $foto && $tenant?->foto_path)
                                <button type="button" wire:click="quitarArchivo('foto')" wire:confirm="¿Quitar la foto actual?" class="mt-1 text-xs text-rose-600 underline">Quitar foto actual</button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ===== Apariencia: colores de la app y de sus formularios ===== --}}
                <div class="border-t pt-4 space-y-4">
                    <div>
                        <div class="font-semibold text-gray-900">Apariencia</div>
                        <div class="text-xs text-gray-500">Cómo se ve la app con este candidato elegido, y sus formularios de registro.</div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-3 rounded-xl border px-4 py-3 cursor-pointer {{ ! $usar_colores ? 'border-indigo-500 ring-2 ring-indigo-500' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="usar_colores" value="0" class="mt-1">
                            <span>
                                <span class="block text-sm font-semibold">Colores por defecto de la app</span>
                                <span class="block text-xs text-gray-500">Fondos blancos y el índigo de VoteuSoft, sin imagen de fondo.</span>
                                <span class="mt-2 flex gap-1">
                                    <span class="h-4 w-4 rounded-full border" style="background: {{ \App\Support\Marca::PRIMARIO_DEFECTO }}"></span>
                                    <span class="h-4 w-4 rounded-full border" style="background: {{ \App\Support\Marca::SECUNDARIO_DEFECTO }}"></span>
                                    <span class="h-4 w-4 rounded-full border bg-white"></span>
                                </span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-xl border px-4 py-3 cursor-pointer {{ $usar_colores ? 'border-indigo-500 ring-2 ring-indigo-500' : 'hover:bg-gray-50' }}">
                            <input type="radio" wire:model.live="usar_colores" value="1" class="mt-1">
                            <span>
                                <span class="block text-sm font-semibold">Colores del candidato</span>
                                <span class="block text-xs text-gray-500">Sus colores, el tema completo (si lo marcas) y su imagen de fondo.</span>
                                <span class="mt-2 flex gap-1">
                                    <span class="h-4 w-4 rounded-full border" style="background: {{ \App\Support\Marca::esHex($color_primario) ? $color_primario : \App\Support\Marca::PRIMARIO_DEFECTO }}"></span>
                                    <span class="h-4 w-4 rounded-full border" style="background: {{ \App\Support\Marca::esHex($color_secundario) ? $color_secundario : \App\Support\Marca::SECUNDARIO_DEFECTO }}"></span>
                                </span>
                            </span>
                        </label>
                    </div>

                    @if (! $usar_colores && $tenant && $tenant->usar_colores)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Al guardar, la app vuelve a los colores por defecto. Los colores del candidato quedan guardados abajo:
                            para usarlos otra vez, elige "Colores del candidato".
                        </div>
                    @endif

                    <div class="{{ $usar_colores ? '' : 'opacity-60' }} space-y-4">
                        @unless ($usar_colores)
                            <div class="text-xs font-semibold text-slate-500">Colores guardados del candidato (no se aplican mientras uses los de la app)</div>
                        @endunless

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold">Color primario</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input type="color" wire:model.live="color_primario" class="h-10 w-14 cursor-pointer rounded border-gray-300"
                                        value="{{ $color_primario ?: \App\Support\Marca::PRIMARIO_DEFECTO }}">
                                    <input wire:model.live.debounce.400ms="color_primario" class="w-full rounded-md border-gray-300 font-mono" placeholder="#RRGGBB" />
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Botones, enlaces, menú activo y cabecera de los formularios.</div>
                                @error('color_primario') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                                @if ($usar_colores && $contrastePrimario !== null && $contrastePrimario < 4.5)
                                    <div class="text-xs text-amber-700 mt-1">
                                        El texto blanco se lee mal sobre este color (contraste {{ $contrastePrimario }}:1, se recomienda 4.5:1).
                                        Prueba un tono más oscuro.
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-semibold">Color secundario</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input type="color" wire:model.live="color_secundario" class="h-10 w-14 cursor-pointer rounded border-gray-300"
                                        value="{{ $color_secundario ?: \App\Support\Marca::SECUNDARIO_DEFECTO }}">
                                    <input wire:model.live.debounce.400ms="color_secundario" class="w-full rounded-md border-gray-300 font-mono" placeholder="#RRGGBB" />
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Franja superior y banda del slogan. Con el tema completo, color de botones, menú activo y enlaces.</div>
                                @error('color_secundario') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <label class="sm:col-span-2 flex items-start gap-3 rounded-lg border px-3 py-2">
                                <input type="checkbox" wire:model.live="tema_completo" class="mt-1 rounded border-gray-300">
                                <span>
                                    <span class="block text-sm font-semibold">Pintar toda la app con su color (tema completo)</span>
                                    <span class="block text-xs text-gray-500">
                                        El fondo, las tarjetas y los textos toman el color primario (letras blancas o negras, según cuál se lea mejor).
                                        Sin marcar, los fondos quedan blancos y solo cambian botones y enlaces.
                                    </span>
                                </span>
                            </label>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold">Imagen de fondo</label>
                                <input type="file" wire:model="fondo" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm">
                                <div class="text-xs text-gray-500 mt-1">
                                    PNG o WEBP con fondo transparente, máx. 6 MB (p. ej. la bandera o el tigre con la bandera).
                                    Se ve suave detrás de las tarjetas en toda la app y en sus formularios de registro.
                                </div>
                                @error('fondo') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                                @if ($fondo && ! $errors->has('fondo'))
                                    <img src="{{ $fondo->temporaryUrl() }}" alt="" class="mt-2 h-20 w-auto rounded border object-contain">
                                @elseif ($tenant?->fondoUrl())
                                    <img src="{{ $tenant->fondoUrl() }}" alt="" class="mt-2 h-20 w-auto rounded border object-contain">
                                    <button type="button" wire:click="quitarArchivo('fondo')" wire:confirm="¿Quitar la imagen de fondo?" class="mt-1 block text-xs text-rose-600 underline">Quitar imagen de fondo</button>
                                @endif
                            </div>
                        </div>

                        <div>
                            <button type="button" wire:click="coloresPorDefecto"
                                class="rounded-md border px-3 py-1.5 text-xs font-semibold hover:bg-gray-50">
                                Poner los colores de la app en estos campos
                            </button>
                            <span class="ml-2 text-xs text-gray-500">Para empezar a editar desde el índigo de VoteuSoft.</span>
                        </div>
                    </div>

                    {{-- Vista previa (con los valores del formulario, sin guardar) --}}
                    @php
                        $pPrim = $usar_colores && \App\Support\Marca::esHex($color_primario) ? $color_primario : \App\Support\Marca::PRIMARIO_DEFECTO;
                        $pSec = $usar_colores && \App\Support\Marca::esHex($color_secundario) ? $color_secundario : \App\Support\Marca::SECUNDARIO_DEFECTO;
                        $pLogo = $logo && ! $errors->has('logo') ? $logo->temporaryUrl() : $tenant?->logoUrl();
                        $pFoto = $foto && ! $errors->has('foto') ? $foto->temporaryUrl() : $tenant?->fotoUrl();
                    @endphp
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-400 mb-1">Vista previa del formulario de registro</div>
                        <div class="max-w-md overflow-hidden rounded-xl border">
                            <div class="flex items-center gap-3 px-4 py-3 text-white" style="background-color: {{ $pPrim }}">
                                @if ($pFoto)
                                    <img src="{{ $pFoto }}" alt="" class="h-14 w-14 rounded-full object-cover ring-2 ring-white/80">
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="text-lg font-extrabold truncate">{{ $nombre ?: 'Nombre del candidato' }}</div>
                                    <div class="text-xs font-semibold uppercase tracking-wide opacity-80 truncate">{{ $partido ?: 'Partido' }}</div>
                                </div>
                                @if ($pLogo)
                                    <img src="{{ $pLogo }}" alt="" class="h-12 w-12 rounded-lg bg-white object-contain p-1">
                                @endif
                            </div>
                            <div class="px-4 py-1.5 text-center text-xs font-bold uppercase tracking-wide"
                                style="background-color: {{ $pSec }}; color: {{ \App\Support\Marca::textoSobre($pSec) }}">
                                {{ $slogan ?: 'Slogan de la campaña' }}
                            </div>
                            <div class="px-4 py-3" style="background-color: {{ $usar_colores && $tema_completo ? $pPrim : '#ffffff' }}">
                                <span class="inline-block rounded-md px-3 py-1.5 text-xs font-semibold text-white" style="background-color: {{ $usar_colores && $tema_completo ? $pSec : $pPrim }}">Enviar registro</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <a href="{{ route('tenants.index') }}" class="px-4 py-2 rounded-md border text-sm">Cancelar</a>
                    <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar"
                        class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                        {{ $tenant ? 'Guardar cambios' : 'Crear candidato' }}
                    </button>
                </div>
            </div>
    </div>
</div>
