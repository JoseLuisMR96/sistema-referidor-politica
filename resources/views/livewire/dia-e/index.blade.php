@php
    $tarjeta = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
    $campo = 'mt-1 w-full rounded-md border-gray-300 text-sm';
    $num = fn ($v) => number_format((int) $v, 0, ',', '.');
@endphp

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Día de elecciones</h2>
            <div class="text-sm text-gray-500">Testigos en las mesas y conteo propio en vivo con los E-14.</div>
        </div>
        @if ($tenant && $puede)
            <div class="flex flex-wrap items-center gap-2">
                @if ($actual)
                    <button type="button" x-data x-on:click="Livewire.dispatch('diae-eleccion')" class="rounded-xl border px-3 py-2 text-sm font-semibold hover:bg-gray-50">Editar elección</button>
                @endif
                <button type="button" x-data x-on:click="Livewire.dispatch('diae-eleccion', { nueva: true })" class="rounded-xl {{ $actual ? 'border px-3 py-2 hover:bg-gray-50' : 'bg-indigo-600 px-4 py-2.5 text-white hover:bg-indigo-700' }} text-sm font-semibold">➕ Nueva elección</button>
            </div>
        @endif
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">{{ session('success') }}</div>
        @endif

        @if (! $tenant)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Elige un candidato en el menú lateral.</div>
        @elseif (! $actual)
            <div class="{{ $tarjeta }} p-8 text-center text-sm text-slate-500">
                Todavía no hay una elección configurada. Crea una con los candidatos o listas que aparecen en el E-14 y luego asigna los testigos.
            </div>
        @else
            <div class="{{ $tarjeta }} p-4 flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-wrap items-end gap-3">
                    @if ($elecciones->count() > 1)
                        <label class="text-[11px] font-semibold text-slate-500">Elección
                            <select wire:model.live="eleccionId" class="mt-1 rounded-xl border-slate-200 text-sm">
                                @foreach ($elecciones as $e)
                                    <option value="{{ $e->id }}">{{ $e->nombre }} · {{ $e->fecha->format('d/m/Y') }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <div>
                        <div class="text-lg font-extrabold text-slate-900">{{ $actual->nombre }}</div>
                        <div class="text-xs text-slate-500">
                            {{ $actual->fecha->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }} ·
                            <span class="font-semibold {{ $actual->abierta ? 'text-emerald-700' : 'text-slate-500' }}">{{ $actual->abierta ? 'Abierta para reportar' : 'Cerrada' }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex rounded-xl border p-1 text-sm font-semibold">
                    @foreach (['resultados' => 'Resultados en vivo', 'testigos' => 'Testigos'] as $v => $t)
                        <button type="button" wire:click="$set('vista', '{{ $v }}')"
                            class="rounded-lg px-3 py-1.5 {{ $vista === $v ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50' }}">{{ $t }}</button>
                    @endforeach
                </div>
            </div>

            {{-- ===== Resultados ===== --}}
            @if ($vista === 'resultados' && $r)
                <div wire:poll.15s class="space-y-6">
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Conteo propio de la campaña con lo que reportan los testigos: es parcial y sin validar. El resultado oficial es el escrutinio.
                        Se actualiza solo cada 15 segundos{{ $r['ultimo'] ? ' · último reporte '.$r['ultimo']->locale('es')->diffForHumans() : '' }}.
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="{{ $tarjeta }} p-4">
                            <div class="text-xs uppercase tracking-wider text-slate-400">Mesas reportadas</div>
                            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($r['reportadas']) }}<span class="text-base text-slate-400"> de {{ $num($r['asignadas']) }}</span></div>
                            <div class="mt-1 h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-indigo-600" style="width: {{ $r['asignadas'] ? min(100, round($r['reportadas'] * 100 / $r['asignadas'])) : 0 }}%"></div></div>
                            <div class="text-xs text-slate-500 mt-1">asignadas a testigos</div>
                        </div>
                        <div class="{{ $tarjeta }} p-4">
                            <div class="text-xs uppercase tracking-wider text-slate-400">Mesas de esos puestos</div>
                            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($r['mesas_puestos']) }}</div>
                            <div class="text-xs text-slate-500">según el DIVIPOL</div>
                        </div>
                        <div class="{{ $tarjeta }} p-4">
                            <div class="text-xs uppercase tracking-wider text-slate-400">Votos contados</div>
                            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($r['total']) }}</div>
                            <div class="text-xs text-slate-500">blanco {{ $num($r['especiales']['blanco']) }} · nulos {{ $num($r['especiales']['nulos']) }} · no marcados {{ $num($r['especiales']['no_marcados']) }}</div>
                        </div>
                        <div class="{{ $tarjeta }} p-4">
                            <div class="text-xs uppercase tracking-wider text-slate-400">Con foto del E-14</div>
                            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $num($r['con_foto']) }}</div>
                            <div class="text-xs text-slate-500">de {{ $num($r['reportadas']) }} reportes</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                        <div class="lg:col-span-3 {{ $tarjeta }} p-4">
                            <div class="text-lg font-extrabold text-slate-900 mb-3">Votos por candidato o lista</div>
                            @php($maximo = max(1, collect($r['opciones'])->max('votos')))
                            <div class="space-y-3">
                                @foreach ($r['opciones'] as $o)
                                    <div wire:key="op-{{ $o['id'] }}">
                                        <div class="flex justify-between text-sm">
                                            <span class="font-semibold {{ $o['propia'] ? 'text-indigo-700' : 'text-slate-900' }}">
                                                {{ $o['nombre'] }} @if ($o['propia'])<span class="text-[10px] uppercase">· nuestro</span>@endif
                                                <span class="text-xs font-normal text-slate-400">{{ $o['partido'] }}</span>
                                            </span>
                                            <span class="tabular-nums">{{ $num($o['votos']) }} · <b>{{ $o['pct'] ?? 0 }}%</b></span>
                                        </div>
                                        <div class="h-3 rounded bg-slate-100"><div class="h-3 rounded {{ $o['propia'] ? 'bg-indigo-600' : 'bg-slate-400' }}" style="width: {{ round($o['votos'] * 100 / $maximo) }}%"></div></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="lg:col-span-2 {{ $tarjeta }} p-4">
                            <div class="text-lg font-extrabold text-slate-900 mb-3">Últimos reportes</div>
                            <div class="divide-y">
                                @forelse ($r['recientes'] as $rep)
                                    <div class="py-2 text-sm" wire:key="rec-{{ $rep->id }}">
                                        <div class="font-semibold text-slate-900">{{ $rep->puesto?->nombre }} · mesa {{ $rep->mesa }}</div>
                                        <div class="text-xs text-slate-500">
                                            {{ $rep->testigo?->nombre }} · {{ $rep->updated_at->locale('es')->diffForHumans() }} · {{ $num($rep->total) }} votos
                                            @if ($rep->foto_ruta) · <a href="{{ route('diae.foto', $rep) }}" target="_blank" rel="noopener" class="underline text-indigo-700">E-14</a> @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="py-4 text-sm text-slate-500">Aún no hay reportes.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @if ($r['por_puesto'])
                        <div class="{{ $tarjeta }} p-4 overflow-x-auto">
                            <div class="text-lg font-extrabold text-slate-900 mb-3">Por puesto</div>
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs uppercase tracking-wider text-slate-400">
                                    <tr>
                                        <th class="py-2 pr-3">Puesto</th>
                                        <th class="py-2 px-2 text-right">Reportadas</th>
                                        <th class="py-2 px-2 text-right">Votos</th>
                                        @foreach (collect($r['opciones'])->take(4) as $o)
                                            <th class="py-2 px-2 text-right">{{ \Illuminate\Support\Str::limit($o['nombre'], 18) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach ($r['por_puesto'] as $p)
                                        <tr wire:key="pp-{{ $p['id'] }}">
                                            <td class="py-2 pr-3"><div class="font-semibold">{{ $p['nombre'] }}</div><div class="text-xs text-slate-500">{{ $p['municipio'] }}</div></td>
                                            <td class="py-2 px-2 text-right tabular-nums">{{ $p['reportadas'] }} / {{ $p['asignadas'] }}</td>
                                            <td class="py-2 px-2 text-right tabular-nums">{{ $num($p['total']) }}</td>
                                            @foreach (collect($r['opciones'])->take(4) as $o)
                                                <td class="py-2 px-2 text-right tabular-nums {{ $p['ganador'] == $o['id'] ? 'font-bold' : '' }}">{{ $num($p['votos'][$o['id']] ?? 0) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ===== Testigos ===== --}}
            @if ($vista === 'testigos')
                @if ($puede)
                    <div class="flex justify-end">
                        <button type="button" wire:click="abrirTestigo" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">➕ Asignar testigo</button>
                    </div>
                @endif
                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="py-3 px-4">Testigo</th>
                                <th class="py-3 px-4">Puesto</th>
                                <th class="py-3 px-4">Mesas</th>
                                <th class="py-3 px-4 text-right">Reportadas</th>
                                <th class="py-3 px-4 text-right">Enlace y acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($testigos as $t)
                                @php($enlace = $t->enlace())
                                @php($mensaje = "Hola {$t->nombre}, este es tu enlace para reportar las mesas {$t->mesas} del puesto {$t->puesto?->nombre} el día de elecciones: {$enlace}")
                                <tr wire:key="t-{{ $t->id }}" class="{{ $t->activo ? '' : 'opacity-50' }}">
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-gray-900">{{ $t->nombre }}</div>
                                        <div class="text-xs text-gray-500">{{ $t->telefono ?: 'Sin teléfono' }}{{ $t->activo ? '' : ' · inactivo' }}</div>
                                    </td>
                                    <td class="py-3 px-4"><div>{{ $t->puesto?->nombre }}</div><div class="text-xs text-gray-500">{{ $t->puesto?->municipio?->nombre }}</div></td>
                                    <td class="py-3 px-4 font-mono text-xs">{{ $t->mesas }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums">{{ $t->reportes_count }} / {{ count($t->listaMesas()) }}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex flex-wrap justify-end gap-2" x-data="{ copiado: false }">
                                            <button type="button" class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50"
                                                x-on:click="navigator.clipboard.writeText(@js($enlace)); copiado = true; setTimeout(() => copiado = false, 1500)">
                                                <span x-show="! copiado">Copiar enlace</span><span x-show="copiado" x-cloak>¡Copiado!</span>
                                            </button>
                                            @if ($t->telefono)
                                                <a href="https://wa.me/{{ $t->telefono }}?text={{ rawurlencode($mensaje) }}" target="_blank" rel="noopener"
                                                    class="px-3 py-1.5 rounded-md border border-emerald-200 text-emerald-700 text-xs font-semibold hover:bg-emerald-50">WhatsApp</a>
                                            @endif
                                            @if ($puede)
                                                <button type="button" wire:click="abrirTestigo({{ $t->id }})" class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50">Editar</button>
                                                <button type="button" wire:click="nuevoEnlace({{ $t->id }})" wire:confirm="¿Generar un enlace nuevo? El anterior deja de servir."
                                                    class="px-3 py-1.5 rounded-md border text-xs font-semibold hover:bg-gray-50">Nuevo enlace</button>
                                                <button type="button" wire:click="eliminarTestigo({{ $t->id }})" wire:confirm="¿Quitar a este testigo? Si ya reportó, solo se desactiva."
                                                    class="px-3 py-1.5 rounded-md border border-rose-200 text-rose-700 text-xs font-semibold hover:bg-rose-50">Quitar</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-8 px-4 text-center text-gray-500">Todavía no hay testigos asignados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>

    {{-- ===== Modal: elección ===== --}}
    @if ($modalEleccion)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="modal-eleccion" x-data x-on:keydown.escape.window="$wire.set('modalEleccion', false)">
            <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl space-y-4">
                <div class="text-lg font-bold text-gray-900">{{ $eleccion['id'] ? 'Editar elección' : 'Nueva elección' }}</div>
                <label class="block text-sm font-semibold">Nombre
                    <input wire:model="eleccion.nombre" class="{{ $campo }}" placeholder="Gobernación del Meta 2027">
                    @error('eleccion.nombre') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block text-sm font-semibold">Cargo
                        <select wire:model="eleccion.cargo" class="{{ $campo }}">
                            @foreach ($cargos as $c)
                                <option value="{{ $c->value }}">{{ $c->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">Fecha
                        <input type="date" wire:model="eleccion.fecha" class="{{ $campo }}">
                    </label>
                </div>
                <label class="block text-sm font-semibold">Candidatos o listas del E-14
                    <textarea wire:model="opcionesTexto" rows="7" class="{{ $campo }} font-mono" placeholder="* Henry Palma | Defensores de la Patria&#10;Otro Candidato | Partido X"></textarea>
                    <span class="block text-xs font-normal text-gray-500 mt-1">Uno por línea: "Nombre | Partido". Marca con * al inicio el del candidato. El blanco, los nulos y los no marcados ya van aparte.</span>
                    @error('opcionesTexto') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                </label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="eleccion.abierta" class="rounded border-gray-300"> Abierta: los testigos pueden reportar</label>
                @error('tenant') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" wire:click="$set('modalEleccion', false)" class="px-4 py-2 rounded-md border text-sm font-semibold">Cancelar</button>
                    <button type="button" wire:click="guardarEleccion" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Modal: testigo ===== --}}
    @if ($modalTestigo)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="modal-testigo" x-data x-on:keydown.escape.window="$wire.set('modalTestigo', false)">
            <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl space-y-4">
                <div class="text-lg font-bold text-gray-900">{{ $testigoId ? 'Editar testigo' : 'Asignar testigo' }}</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block text-sm font-semibold">Nombre
                        <input wire:model="testigo.nombre" class="{{ $campo }}">
                        @error('testigo.nombre') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold">Teléfono (para mandarle el enlace)
                        <input wire:model="testigo.telefono" inputmode="tel" class="{{ $campo }}" placeholder="300 123 4567">
                    </label>
                    @if ($departamentos->isNotEmpty())
                        <label class="block text-sm font-semibold">Departamento
                            <select wire:model.live="testigo.departamento_id" class="{{ $campo }}">
                                <option value="">— Elige —</option>
                                @foreach ($departamentos as $d)
                                    <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label class="block text-sm font-semibold">Municipio
                        <select wire:model.live="testigo.municipio_id" class="{{ $campo }}">
                            <option value="">— Elige —</option>
                            @foreach ($municipios as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold sm:col-span-2">Puesto de votación
                        <select wire:model="testigo.puesto_id" class="{{ $campo }}" @disabled($puestos->isEmpty())>
                            <option value="">{{ ($testigo['municipio_id'] ?? null) && $puestos->isEmpty() ? 'No hay puestos cargados para este municipio' : '— Elige —' }}</option>
                            @foreach ($puestos as $pu)
                                <option value="{{ $pu->id }}">Z{{ $pu->zona }} · {{ $pu->nombre }}{{ $pu->mesas ? " ({$pu->mesas} mesas)" : '' }}</option>
                            @endforeach
                        </select>
                        @error('testigo.puesto_id') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold sm:col-span-2">Mesas
                        <input wire:model="testigo.mesas" class="{{ $campo }} font-mono" placeholder="1-5, 8">
                        <span class="block text-xs font-normal text-gray-500 mt-1">Números y rangos separados por coma.</span>
                        @error('testigo.mesas') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                    </label>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="testigo.activo" class="rounded border-gray-300"> Activo (puede reportar)</label>
                @error('tenant') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" wire:click="$set('modalTestigo', false)" class="px-4 py-2 rounded-md border text-sm font-semibold">Cancelar</button>
                    <button type="button" wire:click="guardarTestigo" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
                </div>
            </div>
        </div>
    @endif
</div>
