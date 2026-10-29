<x-slot name="header">
    <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Redes sociales</h2>
        <div class="text-sm text-gray-500">
            Credenciales de Meta y cuentas de Facebook / Instagram de cada candidato. Se sincronizan a diario.
        </div>
    </div>
</x-slot>

<div>
    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-4">{{ session('error') }}</div>
            @endif

            {{-- ===== Credenciales de Meta (globales) ===== --}}
            @if ($esGlobal)
                <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <div>
                        <div class="text-lg font-semibold text-gray-900">Meta (Facebook e Instagram)</div>
                        <div class="text-sm text-gray-500">
                            Se configuran una sola vez para todos los candidatos. Los secretos se guardan cifrados.
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold">App ID</label>
                            <input wire:model="app_id" class="mt-1 w-full rounded-md border-gray-300 font-mono" />
                            <div class="text-xs text-gray-500 mt-1">developers.facebook.com → tu app → Configuración básica.</div>
                            @error('app_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">App Secret</label>
                            <input type="password" wire:model="app_secret" autocomplete="new-password"
                                class="mt-1 w-full rounded-md border-gray-300 font-mono"
                                placeholder="{{ $secretoGuardado ? "Guardado: {$secretoGuardado} (vacío = conservar)" : '' }}" />
                            @error('app_secret') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">URL de la Graph API</label>
                            <input wire:model="graph_url" class="mt-1 w-full rounded-md border-gray-300 font-mono" />
                            @error('graph_url') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">Versión de la API</label>
                            <input wire:model="graph_version" class="mt-1 w-full rounded-md border-gray-300 font-mono" placeholder="v26.0" />
                            <div class="text-xs text-gray-500 mt-1">Meta retira versiones viejas: si empieza a fallar, súbela.</div>
                            @error('graph_version') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">ID de TU cuenta de Instagram profesional</label>
                            <input wire:model="ig_user_id" class="mt-1 w-full rounded-md border-gray-300 font-mono" placeholder="1784..." />
                            <div class="text-xs text-gray-500 mt-1">Con ella se consultan los datos públicos de candidatos que aún no te dan acceso.</div>
                            @error('ig_user_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">Access token (larga duración)</label>
                            <input type="password" wire:model="access_token" autocomplete="new-password"
                                class="mt-1 w-full rounded-md border-gray-300 font-mono"
                                placeholder="{{ $tokenGuardado ? "Guardado: {$tokenGuardado} (vacío = conservar)" : '' }}" />
                            <div class="text-xs text-gray-500 mt-1">Token de usuario (ampliado a 60 días) con: instagram_basic, instagram_manage_insights, pages_show_list, pages_read_engagement y business_management. Sin instagram_manage_insights, Meta rechaza la consulta de otras cuentas (error #10).</div>
                            @error('access_token') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if ($resultadoPrueba)
                            <div class="mr-auto text-sm">{{ $resultadoPrueba }}</div>
                        @endif
                        <button type="button" wire:click="probarConexion" class="px-4 py-2 rounded-md border text-sm">Probar conexión</button>
                        <button type="button" wire:click="guardarCredenciales" class="px-4 py-2 rounded-md bg-gray-800 text-white text-sm">Guardar</button>
                    </div>
                </div>

                {{-- ===== IA para el tono de los comentarios (global) ===== --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <div>
                        <div class="text-lg font-semibold text-gray-900">IA para el tono de los comentarios (Claude)</div>
                        <div class="text-sm text-gray-500">
                            Clasifica los comentarios de las cuentas del candidato en a favor, en contra o neutro.
                            Solo se guardan los conteos y los temas: nunca el texto ni quién comentó.
                            La API key se crea en console.anthropic.com y se guarda cifrada.
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">API key de Anthropic</label>
                        <input type="password" wire:model="anthropic_key" autocomplete="new-password"
                            class="mt-1 w-full rounded-md border-gray-300 font-mono"
                            placeholder="{{ $iaGuardada ? "Guardada: {$iaGuardada} (escribe otra para cambiarla)" : 'sk-ant-...' }}" />
                        @error('anthropic_key') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if ($resultadoIa)
                            <div class="mr-auto text-sm">{{ $resultadoIa }}</div>
                        @endif
                        @if ($iaGuardada)
                            <button type="button" wire:click="probarIa" class="px-4 py-2 rounded-md border text-sm">Probar IA</button>
                        @endif
                        <button type="button" wire:click="guardarAnthropic" class="px-4 py-2 rounded-md bg-gray-800 text-white text-sm">Guardar</button>
                    </div>
                </div>
            @endif

            {{-- ===== Cuentas del candidato ===== --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div>
                    <div class="text-lg font-semibold text-gray-900">
                        Cuentas {{ $tenant ? "de {$tenant->nombre}" : '' }}
                    </div>
                    <div class="text-sm text-gray-500">
                        Instagram sin acceso del candidato: solo datos públicos y solo si su cuenta es profesional (empresa o creador).
                        Facebook: hace falta el token de su página.
                        Los <strong>rivales</strong> (otros candidatos al mismo cargo) se miden solo en Instagram, para comparar en el Dashboard de redes.
                    </div>
                </div>

                @if (! $tenant)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        Elige un candidato en el menú lateral para ver y registrar sus cuentas.
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold">Tipo de cuenta</label>
                            <select wire:model.live="tipo" class="mt-1 w-full rounded-md border-gray-300">
                                <option value="propia">Del candidato</option>
                                <option value="rival">Rival (otro candidato)</option>
                            </select>
                            @error('tipo') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            @if ($tipo === 'rival')
                                <label class="block text-sm font-semibold">Nombre del rival</label>
                                <input wire:model="etiqueta" class="mt-1 w-full rounded-md border-gray-300" placeholder="Juan Pérez" />
                                @error('etiqueta') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">Red</label>
                            <select wire:model.live="red" class="mt-1 w-full rounded-md border-gray-300">
                                @foreach ($redes as $r)
                                    <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                                @endforeach
                            </select>
                            @error('red') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">
                                {{ $red === 'instagram' ? 'Usuario de Instagram' : 'ID o alias de la página' }}
                            </label>
                            <input wire:model="usuario" class="mt-1 w-full rounded-md border-gray-300"
                                placeholder="{{ $red === 'instagram' ? 'camilocfer' : 'CamiloCferOficial' }}" />
                            @error('usuario') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">URL del perfil</label>
                            <input wire:model="url" class="mt-1 w-full rounded-md border-gray-300"
                                placeholder="https://www.{{ $red }}.com/..." />
                            @error('url') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold">ID en Meta (opcional)</label>
                            <input wire:model="external_id" class="mt-1 w-full rounded-md border-gray-300 font-mono" />
                            <div class="text-xs text-gray-500 mt-1">Se completa solo en la primera sincronización de Instagram.</div>
                            @error('external_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="sm:col-span-2 {{ $tipo === 'rival' ? 'hidden' : '' }}">
                            <label class="block text-sm font-semibold">Token de acceso del candidato (opcional)</label>
                            <input type="password" wire:model="cuenta_token" autocomplete="new-password"
                                class="mt-1 w-full rounded-md border-gray-300 font-mono"
                                placeholder="{{ $cuentaId ? 'Vacío = conservar el actual' : 'Cuando el candidato te dé acceso a su página o cuenta' }}" />
                            @error('cuenta_token') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        @if ($cuentaId)
                            <button type="button" wire:click="cancelarCuenta" class="px-4 py-2 rounded-md border text-sm">Cancelar</button>
                        @endif
                        <button type="button" wire:click="guardarCuenta" class="px-4 py-2 rounded-md bg-gray-800 text-white text-sm">
                            {{ $cuentaId ? 'Guardar cambios' : 'Agregar cuenta' }}
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-lg border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th class="py-3 px-4">Cuenta</th>
                                    <th class="py-3 px-4">Acceso</th>
                                    <th class="py-3 px-4">Última sincronización</th>
                                    <th class="py-3 px-4"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @forelse ($cuentas as $c)
                                    <tr wire:key="cuenta-{{ $c->id }}" class="{{ $c->activo ? '' : 'opacity-50' }}">
                                        <td class="py-3 px-4">
                                            <div class="font-semibold">
                                                {{ ucfirst($c->red) }} · {{ $c->usuario }}
                                                @if ($c->esRival())
                                                    <span class="ml-1 rounded-full bg-orange-100 text-orange-800 px-2 py-0.5 text-xs font-semibold">Rival: {{ $c->etiqueta }}</span>
                                                @endif
                                            </div>
                                            @if ($c->url)
                                                <a href="{{ $c->url }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 underline break-all">{{ $c->url }}</a>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-xs">
                                            @if ($c->tieneAccesoPropio())
                                                <span class="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 font-semibold">Token del candidato</span>
                                                <button type="button" wire:click="quitarToken({{ $c->id }})" class="ml-1 underline text-gray-500">quitar</button>
                                            @else
                                                <span class="rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 font-semibold">Solo datos públicos</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-xs">
                                            {{ $c->ultimo_sync_at?->diffForHumans() ?? 'Nunca' }}
                                            @if ($c->ultimo_error)
                                                <div class="text-rose-600 mt-1">{{ $c->ultimo_error }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-right whitespace-nowrap">
                                            <button type="button" wire:click="sincronizar({{ $c->id }})" class="px-3 py-1.5 rounded-md border text-xs font-semibold">Sincronizar</button>
                                            @if (! $c->esRival() && $c->tieneAccesoPropio())
                                                <button type="button" wire:click="analizarComentarios({{ $c->id }})" wire:loading.attr="disabled"
                                                    class="px-3 py-1.5 rounded-md border text-xs font-semibold">Analizar comentarios</button>
                                            @endif
                                            <button type="button" wire:click="editarCuenta({{ $c->id }})" class="px-3 py-1.5 rounded-md border text-xs font-semibold">Editar</button>
                                            <button type="button" wire:click="alternarCuenta({{ $c->id }})" class="px-3 py-1.5 rounded-md border text-xs font-semibold">
                                                {{ $c->activo ? 'Pausar' : 'Activar' }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-6 px-4 text-center text-gray-500">Este candidato no tiene cuentas registradas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
