<x-slot name="header">
    <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Integraciones</h2>
        <div class="text-sm text-gray-500">Credenciales de servicios externos, iguales para todos los candidatos. Se guardan cifradas.</div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">{{ session('success') }}</div>
        @endif

        {{-- ===== Meta ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
            <div>
                <div class="text-lg font-semibold text-gray-900">Meta (Facebook e Instagram)</div>
                <div class="text-sm text-gray-500">Con esto se sincronizan las cuentas de redes de todos los candidatos.</div>
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
                    <div class="text-xs text-gray-500 mt-1">
                        Pega el token que genera el Graph API Explorer: al guardar, el sistema lo cambia solo por uno de 60 días.
                        Permisos: instagram_basic, instagram_manage_insights, pages_show_list, pages_read_engagement y business_management.
                        Sin instagram_manage_insights, Meta rechaza la consulta de otras cuentas (error #10).
                    </div>
                    @if ($tokenVence)
                        <div class="mt-1 text-xs font-semibold {{ $tokenVence->isPast() ? 'text-rose-600' : ($tokenVence->lte(now()->addDays(10)) ? 'text-amber-600' : 'text-emerald-700') }}">
                            {{ $tokenVence->isPast() ? 'El token venció el' : 'El token vence el' }}
                            {{ $tokenVence->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
                        </div>
                    @elseif ($tokenGuardado)
                        <div class="mt-1 text-xs text-amber-600">No se sabe cuándo vence el token guardado: pega uno nuevo para extenderlo a 60 días.</div>
                    @endif
                    @error('access_token') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t pt-4">
                @if ($resultadoPrueba)
                    <div class="mr-auto text-sm">{{ $resultadoPrueba }}</div>
                @endif
                <button type="button" wire:click="probarConexion" class="px-4 py-2 rounded-md border text-sm">Probar conexión</button>
                <button type="button" wire:click="guardarCredenciales" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
            </div>
        </div>

        {{-- ===== IA para el tono de los comentarios ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
            <div>
                <div class="text-lg font-semibold text-gray-900">IA para el tono de los comentarios (Claude)</div>
                <div class="text-sm text-gray-500">
                    Clasifica los comentarios de las cuentas del candidato en a favor, en contra o neutro.
                    Solo se guardan los conteos y los temas: nunca el texto ni quién comentó.
                    La API key se crea en console.anthropic.com.
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold">API key de Anthropic</label>
                <input type="password" wire:model="anthropic_key" autocomplete="new-password"
                    class="mt-1 w-full rounded-md border-gray-300 font-mono"
                    placeholder="{{ $iaGuardada ? "Guardada: {$iaGuardada} (escribe otra para cambiarla)" : 'sk-ant-...' }}" />
                @error('anthropic_key') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t pt-4">
                @if ($resultadoIa)
                    <div class="mr-auto text-sm">{{ $resultadoIa }}</div>
                @endif
                @if ($iaGuardada)
                    <button type="button" wire:click="probarIa" class="px-4 py-2 rounded-md border text-sm">Probar IA</button>
                @endif
                <button type="button" wire:click="guardarAnthropic" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
            </div>
        </div>
    </div>
</div>
