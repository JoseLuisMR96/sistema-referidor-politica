<x-slot name="header">
    <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Candidatos</h2>
        <div class="text-sm text-gray-500">
            Cada tenant es un candidato o campaña. Sus referidores, registros, pregoneros y envíos de WhatsApp quedan separados.
        </div>
    </div>
</x-slot>

<div>
    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Formulario --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="text-lg font-semibold text-gray-900">
                    {{ $editingId ? 'Editar tenant' : 'Nuevo tenant' }}
                </div>

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

                {{-- ===== Identidad visual ===== --}}
                <div class="border-t pt-4 space-y-4">
                    <div>
                        <div class="font-semibold text-gray-900">Identidad visual</div>
                        <div class="text-xs text-gray-500">
                            Con este candidato elegido, toda la app y sus formularios de registro se ven con sus colores, logo y foto.
                            Usa el material oficial de la campaña.
                        </div>
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
                            <label class="block text-sm font-semibold">Color primario</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="color" wire:model.live="color_primario" class="h-10 w-14 cursor-pointer rounded border-gray-300"
                                    value="{{ $color_primario ?: '#4f46e5' }}">
                                <input wire:model.live.debounce.400ms="color_primario" class="w-full rounded-md border-gray-300 font-mono" placeholder="#RRGGBB" />
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Botones, enlaces, menú activo y cabecera de los formularios.</div>
                            @error('color_primario') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                            @if ($contrastePrimario !== null && $contrastePrimario < 4.5)
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
                                    value="{{ $color_secundario ?: '#0f172a' }}">
                                <input wire:model.live.debounce.400ms="color_secundario" class="w-full rounded-md border-gray-300 font-mono" placeholder="#RRGGBB" />
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Franja superior y banda del slogan.</div>
                            @error('color_secundario') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">Logo del partido o campaña</label>
                            <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm">
                            <div class="text-xs text-gray-500 mt-1">PNG, JPG o WEBP, máx. 2 MB. Mejor con fondo transparente.</div>
                            @error('logo') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                            @if (! $logo && $tenantEditado?->logo_path)
                                <button type="button" wire:click="quitarArchivo('logo')" class="mt-1 text-xs text-rose-600 underline">Quitar logo actual</button>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">Foto del candidato</label>
                            <input type="file" wire:model="foto" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm">
                            <div class="text-xs text-gray-500 mt-1">PNG, JPG o WEBP, máx. 4 MB. Se muestra en círculo: mejor un retrato centrado.</div>
                            @error('foto') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                            @if (! $foto && $tenantEditado?->foto_path)
                                <button type="button" wire:click="quitarArchivo('foto')" class="mt-1 text-xs text-rose-600 underline">Quitar foto actual</button>
                            @endif
                        </div>
                    </div>

                    {{-- Vista previa (con los valores del formulario, sin guardar) --}}
                    @php
                        $pPrim = \App\Support\Marca::esHex($color_primario) ? $color_primario : '#4f46e5';
                        $pSec = \App\Support\Marca::esHex($color_secundario) ? $color_secundario : '#0f172a';
                        $pLogo = $logo && ! $errors->has('logo') ? $logo->temporaryUrl() : $tenantEditado?->logoUrl();
                        $pFoto = $foto && ! $errors->has('foto') ? $foto->temporaryUrl() : $tenantEditado?->fotoUrl();
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
                            <div class="bg-white px-4 py-3">
                                <span class="inline-block rounded-md px-3 py-1.5 text-xs font-semibold text-white" style="background-color: {{ $pPrim }}">Enviar registro</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    @if ($editingId)
                        <button type="button" wire:click="cancelar" class="px-4 py-2 rounded-md border text-sm">Cancelar</button>
                    @endif
                    <button type="button" wire:click="guardar" class="px-4 py-2 rounded-md bg-gray-800 text-white text-sm">
                        {{ $editingId ? 'Guardar cambios' : 'Crear tenant' }}
                    </button>
                </div>
            </div>

            {{-- Listado --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="py-3 px-4">Candidato</th>
                            <th class="py-3 px-4 text-right">Registros</th>
                            <th class="py-3 px-4 text-right">Pregoneros</th>
                            <th class="py-3 px-4 text-right">Campañas</th>
                            <th class="py-3 px-4 text-right">Usuarios</th>
                            <th class="py-3 px-4">Estado</th>
                            <th class="py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($tenants as $t)
                            <tr wire:key="tenant-{{ $t->id }}">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        @if ($t->fotoUrl())
                                            <img src="{{ $t->fotoUrl() }}" alt="" class="h-8 w-8 rounded-full object-cover">
                                        @endif
                                        @if (\App\Support\Marca::esHex($t->color_primario))
                                            <span class="h-3 w-3 rounded-full" style="background-color: {{ $t->color_primario }}" title="Color primario"></span>
                                        @endif
                                        <div class="font-semibold text-gray-900">{{ $t->nombre }}</div>
                                    </div>
                                    @if ($t->partido)
                                        <div class="text-xs text-gray-500">{{ $t->partido }}</div>
                                    @endif
                                    <div class="text-xs text-gray-500">{{ $t->descripcion ?: '—' }}</div>
                                    <div class="text-xs {{ $t->ambito() ? 'text-indigo-700' : 'text-amber-700' }}">
                                        {{ $t->circunscripcionTexto() }}
                                    </div>
                                    <div class="text-xs text-gray-400 font-mono">{{ $t->slug }}</div>
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums">{{ (int) ($registros[$t->id] ?? 0) }}</td>
                                <td class="py-3 px-4 text-right tabular-nums">{{ (int) ($pregoneros[$t->id] ?? 0) }}</td>
                                <td class="py-3 px-4 text-right tabular-nums">{{ (int) ($campanas[$t->id] ?? 0) }}</td>
                                <td class="py-3 px-4 text-right tabular-nums">{{ $t->users_count }}</td>
                                <td class="py-3 px-4">
                                    @if ($t->activo)
                                        <span class="inline-flex rounded-full bg-emerald-100 text-emerald-800 px-2.5 py-0.5 text-xs font-semibold">Activo</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 text-gray-600 px-2.5 py-0.5 text-xs font-semibold">Inactivo</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="editar({{ $t->id }})" class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50">
                                            Editar
                                        </button>
                                        <button type="button" wire:click="pedirEliminar({{ $t->id }})" class="px-3 py-1.5 rounded-md border border-rose-200 text-rose-700 text-xs font-semibold hover:bg-rose-50">
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            @if ($tenantEliminando?->id === $t->id)
                                <tr wire:key="eliminar-{{ $t->id }}" class="bg-rose-50">
                                    <td colspan="7" class="py-4 px-4">
                                        <div class="text-sm font-bold text-rose-900">¿Eliminar a {{ $t->nombre }}?</div>

                                        @if ($usuariosEliminar)
                                            <div class="mt-1 text-sm text-rose-800">
                                                No se puede: tiene {{ $usuariosEliminar }} usuario(s) asignados.
                                                Muévelos a otro candidato o elimínalos en <a href="{{ route('users.index') }}" class="underline font-semibold">Usuarios</a> primero
                                                (si no, quedarían como usuarios globales y verían a todos los candidatos).
                                            </div>
                                            <div class="mt-3">
                                                <button type="button" wire:click="cancelarEliminar" class="px-3 py-1.5 rounded-md border bg-white text-xs font-semibold">Cerrar</button>
                                            </div>
                                        @else
                                            <div class="mt-1 text-sm text-rose-800">
                                                Se borra para siempre, junto con todos sus datos. No se puede deshacer.
                                            </div>
                                            @if ($resumenEliminar)
                                                <ul class="mt-2 flex flex-wrap gap-2 text-xs">
                                                    @foreach ($resumenEliminar as $etiqueta => $total)
                                                        <li class="rounded bg-white px-2 py-1 border border-rose-200 text-rose-900">
                                                            {{ $etiqueta }}: <span class="font-bold">{{ number_format($total, 0, ',', '.') }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <div class="mt-1 text-xs text-rose-700">No tiene datos cargados.</div>
                                            @endif

                                            <label class="mt-3 block text-xs font-semibold text-rose-900">
                                                Para confirmar, escribe el nombre: <span class="font-mono">{{ $t->nombre }}</span>
                                                <input type="text" wire:model="confirmacionNombre" autocomplete="off"
                                                    class="mt-1 block w-full max-w-sm rounded-md border-rose-300 text-sm">
                                            </label>
                                            @error('confirmacionNombre')
                                                <div class="mt-1 text-xs text-rose-700 font-semibold">{{ $message }}</div>
                                            @enderror

                                            <div class="mt-3 flex gap-2">
                                                <button type="button" wire:click="cancelarEliminar" class="px-3 py-1.5 rounded-md border bg-white text-xs font-semibold">Cancelar</button>
                                                <button type="button" wire:click="eliminar" wire:loading.attr="disabled" wire:target="eliminar"
                                                    class="px-3 py-1.5 rounded-md bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700 disabled:opacity-50">
                                                    Eliminar definitivamente
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 px-4 text-center text-gray-500">Todavía no hay tenants.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
