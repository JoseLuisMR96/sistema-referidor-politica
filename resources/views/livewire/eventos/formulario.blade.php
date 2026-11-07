@php
    use App\Models\EventoCampana;

    $campo = 'mt-1 w-full rounded-md border-gray-300 text-sm';
    $error = fn ($c) => $errors->first($c);
@endphp

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $evento ? 'Editar evento' : 'Nuevo evento de campaña' }}</h2>
            <div class="text-sm text-gray-500">{{ $tenant ? $tenant->circunscripcionTexto() : 'Elige un candidato' }}</div>
        </div>
        <a href="{{ $evento ? route('eventos.show', $evento) : route('eventos.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">
            ← {{ $evento ? 'Volver al evento' : 'Volver a los eventos' }}
        </a>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <x-tenant-requerido accion="registrar eventos de campaña" />

        @if ($tenant && ! $lugares?->completo())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Al candidato le falta el cargo o el lugar al que se postula: complétalo en
                <a href="{{ route('tenants.index') }}" class="underline font-semibold">Candidatos</a> para registrar eventos.
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">

            {{-- ===== Qué y cuándo ===== --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold">Qué se hizo</label>
                    <input wire:model="titulo" class="{{ $campo }}" placeholder="Reunión con líderes de la vereda La Esperanza" />
                    @error('titulo') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold">Tipo</label>
                    <select wire:model="tipo" class="{{ $campo }}">
                        @foreach (EventoCampana::TIPOS as $valor => $texto)
                            <option value="{{ $valor }}">{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold">Estado</label>
                    <select wire:model="estado" class="{{ $campo }}">
                        @foreach (EventoCampana::ESTADOS as $valor => [$texto])
                            <option value="{{ $valor }}">{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold">Fecha y hora</label>
                    <input type="datetime-local" wire:model="fecha" class="{{ $campo }}" />
                    @error('fecha') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold">Asistentes (aprox.)</label>
                    <input type="number" min="0" wire:model="asistentes" class="{{ $campo }}" placeholder="40" />
                    @error('asistentes') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            {{-- ===== Dónde ===== --}}
            <div class="border-t pt-5 space-y-4">
                <div>
                    <div class="font-semibold text-gray-900">Dónde</div>
                    <div class="text-xs text-gray-500">Solo dentro de la circunscripción del candidato.</div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if ($lugares && $lugares->departamentoFijo() === null)
                        <div>
                            <label class="block text-sm font-semibold">Departamento</label>
                            <select wire:model.live="departamento_id" class="{{ $campo }}">
                                <option value="">— Elige —</option>
                                @foreach ($departamentos as $d)
                                    <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-semibold">Municipio</label>
                        @if ($lugares?->municipioFijo())
                            <div class="mt-1 rounded-md border bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ $municipios->first()?->nombre }}</div>
                        @else
                            <select wire:model="municipio_id" class="{{ $campo }}" @disabled($municipios->isEmpty())>
                                <option value="">— Elige —</option>
                                @foreach ($municipios as $m)
                                    <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                                @endforeach
                            </select>
                        @endif
                        @error('municipio_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold">Sitio</label>
                        <input wire:model="lugar" class="{{ $campo }}" placeholder="Salón comunal, finca, colegio, vereda…" />
                        @error('lugar') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold">Dirección</label>
                        <input wire:model="direccion" class="{{ $campo }}" placeholder="Calle 10 # 5-20, barrio El Centro" />
                        @error('direccion') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="sm:col-span-2" x-data="{ buscando: false, aviso: '' }">
                        <label class="block text-sm font-semibold">Ubicación en el mapa (opcional)</label>
                        <div class="mt-1 flex flex-col sm:flex-row gap-2">
                            <input wire:model="coordenadas" class="w-full rounded-md border-gray-300 text-sm font-mono" placeholder="4.1420, -73.6266" />
                            <button type="button" class="whitespace-nowrap rounded-md border px-3 py-2 text-sm font-semibold hover:bg-gray-50"
                                x-on:click="
                                    if (! navigator.geolocation) { aviso = 'Este navegador no da la ubicación.'; return; }
                                    buscando = true; aviso = '';
                                    navigator.geolocation.getCurrentPosition(
                                        (p) => { buscando = false; $wire.set('coordenadas', p.coords.latitude.toFixed(6) + ', ' + p.coords.longitude.toFixed(6)); },
                                        () => { buscando = false; aviso = 'No se pudo obtener la ubicación (revisa el permiso del navegador).'; },
                                        { enableHighAccuracy: true, timeout: 15000 }
                                    )">
                                <span x-show="! buscando">📍 Usar mi ubicación</span>
                                <span x-show="buscando" x-cloak>Buscando…</span>
                            </button>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Desde el sitio, usa el botón; o en Google Maps haz clic derecho en el lugar y copia las coordenadas. Así sale como punto en el mapa de eventos.
                        </div>
                        <div class="text-xs text-amber-700 mt-1" x-show="aviso" x-text="aviso"></div>
                        @error('coordenadas') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- ===== Quién fue ===== --}}
            <div class="border-t pt-5 space-y-4">
                <div>
                    <div class="font-semibold text-gray-900">Quién fue</div>
                    <div class="text-xs text-gray-500">
                        El candidato no puede estar en todas partes: registra si fue él o quién del equipo fue en su nombre.
                        @if ($equipo->isEmpty())
                            Primero agrega a las personas en <a href="{{ route('equipo.index') }}" class="underline font-semibold">Equipo de campaña</a>.
                        @endif
                    </div>
                </div>

                <label class="flex items-start gap-3 rounded-lg border px-3 py-2">
                    <input type="checkbox" wire:model.live="asistio_candidato" class="mt-1 rounded border-gray-300">
                    <span>
                        <span class="block text-sm font-semibold">El candidato estuvo en persona</span>
                        <span class="block text-xs text-gray-500">Sin marcar: fue un emisario o el equipo.</span>
                    </span>
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold">Responsable del evento</label>
                        <select wire:model.live="responsable_id" class="{{ $campo }}">
                            <option value="">— Nadie del equipo —</option>
                            @foreach ($equipo as $i)
                                <option value="{{ $i->id }}">{{ $i->nombre }} ({{ $i->rolTexto() }}){{ $i->activo ? '' : ' · inactivo' }}</option>
                            @endforeach
                        </select>
                        <div class="text-xs text-gray-500 mt-1">Quien lo organizó o fue en nombre del candidato. Si hay dinero, queda a su nombre.</div>
                        @error('responsable_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    @if ($equipo->count() > 1)
                        <div>
                            <div class="block text-sm font-semibold">Otros del equipo que fueron</div>
                            <div class="mt-1 max-h-40 overflow-y-auto rounded-md border divide-y">
                                @foreach ($equipo as $i)
                                    @continue($i->id === $responsable_id)
                                    <label class="flex items-center gap-2 px-3 py-1.5 text-sm" wire:key="int-{{ $i->id }}">
                                        <input type="checkbox" wire:model="integrantes" value="{{ $i->id }}" class="rounded border-gray-300">
                                        {{ $i->nombre }} <span class="text-xs text-gray-400">{{ $i->rolTexto() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ===== Dinero ===== --}}
            <div class="border-t pt-5 space-y-4">
                <div>
                    <div class="font-semibold text-gray-900">Dinero</div>
                    <div class="text-xs text-gray-500">En pesos. El monto asignado queda a nombre del responsable.</div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold">Monto asignado</label>
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">$</span>
                            <input wire:model="monto_asignado" inputmode="decimal" class="w-full rounded-md border-gray-300 pl-7 text-sm" placeholder="500.000" />
                        </div>
                        @error('monto_asignado') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold">Monto gastado (cuando se legalice)</label>
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">$</span>
                            <input wire:model="monto_gastado" inputmode="decimal" class="w-full rounded-md border-gray-300 pl-7 text-sm" placeholder="Vacío si aún no se sabe" />
                        </div>
                        @error('monto_gastado') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- ===== Detalle ===== --}}
            <div class="border-t pt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold">Qué se habló o propuso</label>
                    <textarea wire:model="descripcion" rows="4" class="{{ $campo }}" placeholder="Temas, propuestas presentadas, quiénes convocaron…"></textarea>
                    @error('descripcion') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold">Resultado</label>
                    <textarea wire:model="resultado" rows="4" class="{{ $campo }}" placeholder="Compromisos, nuevos apoyos, pendientes…"></textarea>
                    @error('resultado') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            {{-- ===== Evidencias ===== --}}
            <div class="border-t pt-5 space-y-3">
                <div>
                    <div class="font-semibold text-gray-900">Evidencias</div>
                    <div class="text-xs text-gray-500">
                        Fotos (JPG, PNG, WEBP) y soportes en PDF (facturas, listas de asistencia, actas), hasta 10 MB cada uno.
                        Quedan privados: solo los ven los usuarios de este candidato.
                    </div>
                </div>

                <input type="file" multiple wire:model="archivos" accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-sm">
                <div wire:loading wire:target="archivos" class="text-xs text-slate-500">Subiendo…</div>
                @error('archivos') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                @foreach ($errors->get('archivos.*') as $mensajes)
                    <div class="text-sm text-red-600">{{ $mensajes[0] }}</div>
                @endforeach

                @if ($archivos)
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                        @foreach ($archivos as $i => $archivo)
                            <div class="relative rounded-lg border p-1" wire:key="nuevo-{{ $i }}">
                                @if (str_starts_with((string) $archivo->getMimeType(), 'image/') && ! $errors->has("archivos.$i"))
                                    <img src="{{ $archivo->temporaryUrl() }}" alt="" class="h-24 w-full rounded object-cover">
                                @else
                                    <div class="flex h-24 items-center justify-center rounded bg-slate-50 text-xs text-slate-500 text-center px-1 break-all">📄 {{ $archivo->getClientOriginalName() }}</div>
                                @endif
                                <button type="button" wire:click="quitarArchivo({{ $i }})" class="absolute right-1 top-1 rounded bg-white/90 px-1.5 text-xs font-bold text-rose-600 shadow">✕</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($evidencias->isNotEmpty())
                    <div class="text-xs text-slate-500">Ya guardadas: {{ $evidencias->count() }} (se quitan desde la ficha del evento).</div>
                @endif
            </div>

            @error('tenant') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

            <div class="flex justify-end gap-2 border-t pt-4">
                <a href="{{ $evento ? route('eventos.show', $evento) : route('eventos.index') }}" class="px-4 py-2 rounded-md border text-sm">Cancelar</a>
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar,archivos"
                    class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                    {{ $evento ? 'Guardar cambios' : 'Registrar evento' }}
                </button>
            </div>
        </div>
    </div>
</div>
