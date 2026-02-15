<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar usuario</h2>
            <div class="text-xs text-gray-500">Gestión de identidad y accesos</div>
        </div>

        <a href="{{ route('users.index') }}"
           class="inline-flex items-center justify-center rounded-md border px-4 py-2 text-sm font-semibold hover:bg-slate-50">
            ← Volver
        </a>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if(session('success'))
            <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 p-4 rounded-lg">
                ✅ {{ session('success') }}
            </div>
        @endif

        {{-- Resumen --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="text-xs text-gray-500">ID</div>
                    <div class="font-mono text-sm">{{ $user->id }}</div>
                </div>

                <div>
                    <div class="text-xs text-gray-500">Roles actuales</div>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @forelse($userRoleNames as $r)
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                {{ $r }}
                            </span>
                        @empty
                            <span class="text-xs text-slate-500">Sin rol</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Formulario --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">

            {{-- Datos básicos --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold">Nombre</label>
                    <input wire:model.live="name" class="w-full rounded-md border-gray-300" autocomplete="name" />
                    @error('name') <div class="text-sm text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold">Email</label>
                    <input wire:model.live="email" class="w-full rounded-md border-gray-300" autocomplete="username" />
                    @error('email') <div class="text-sm text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            {{-- Roles --}}
            <div>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold">Roles</div>
                        <div class="text-xs text-gray-500">Asigna uno o varios roles al usuario</div>
                    </div>

                    <div class="text-xs text-gray-500">
                        Seleccionados: <span class="font-semibold">{{ count($roleIds) }}</span>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($allRoles as $role)
                        <label class="flex items-center gap-3 rounded-lg border p-3 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" class="rounded border-gray-300"
                                   value="{{ $role->id }}" wire:model.live="roleIds">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-800">{{ $role->name }}</div>
                                <div class="text-xs text-slate-500">Acceso por rol</div>
                            </div>
                        </label>
                    @endforeach
                </div>

                @error('roleIds') <div class="text-sm text-rose-600 mt-2">{{ $message }}</div> @enderror
                @error('roleIds.*') <div class="text-sm text-rose-600 mt-2">{{ $message }}</div> @enderror
            </div>

            {{-- Password opcional --}}
            <div class="rounded-lg border bg-amber-50/40 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">Reset de contraseña (opcional)</div>
                        <div class="text-xs text-slate-600">
                            Si no diligencias, se mantiene la actual.
                        </div>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4" x-data="{ showPass: false, showConfirm: false }">
                    {{-- Nueva contraseña --}}
                    <div>
                        <label class="block text-sm font-semibold">Nueva contraseña</label>

                        <div class="relative mt-1">
                            <input
                                :type="showPass ? 'text' : 'password'"
                                wire:model.live="password"
                                class="w-full rounded-md border-gray-300 pr-11"
                                autocomplete="new-password"
                                placeholder="Mínimo 8 caracteres"
                            />

                            <button
                                type="button"
                                @click="showPass = !showPass"
                                class="absolute inset-y-0 right-2 inline-flex items-center justify-center rounded-md px-2 text-slate-500 hover:text-slate-900"
                                :aria-label="showPass ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            >
                                {{-- Ojo abierto --}}
                                <svg x-show="!showPass" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>

                                {{-- Ojo tachado --}}
                                <svg x-show="showPass" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                                    <path d="M9.9 5.1C10.6 5 11.3 5 12 5c6.5 0 10 7 10 7a17.7 17.7 0 0 1-4.2 5.2"></path>
                                    <path d="M6.3 6.3C3.5 8.3 2 12 2 12s3.5 7 10 7c1.5 0 2.9-.3 4.1-.8"></path>
                                </svg>
                            </button>
                        </div>

                        @error('password') <div class="text-sm text-rose-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Confirmación --}}
                    <div>
                        <label class="block text-sm font-semibold">Confirmación</label>

                        <div class="relative mt-1">
                            <input
                                :type="showConfirm ? 'text' : 'password'"
                                wire:model.live="password_confirmation"
                                class="w-full rounded-md border-gray-300 pr-11"
                                autocomplete="new-password"
                                placeholder="Repite la contraseña"
                            />

                            <button
                                type="button"
                                @click="showConfirm = !showConfirm"
                                class="absolute inset-y-0 right-2 inline-flex items-center justify-center rounded-md px-2 text-slate-500 hover:text-slate-900"
                                :aria-label="showConfirm ? 'Ocultar confirmación' : 'Mostrar confirmación'"
                            >
                                {{-- Ojo abierto --}}
                                <svg x-show="!showConfirm" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>

                                {{-- Ojo tachado --}}
                                <svg x-show="showConfirm" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                                    <path d="M9.9 5.1C10.6 5 11.3 5 12 5c6.5 0 10 7 10 7a17.7 17.7 0 0 1-4.2 5.2"></path>
                                    <path d="M6.3 6.3C3.5 8.3 2 12 2 12s3.5 7 10 7c1.5 0 2.9-.3 4.1-.8"></path>
                                </svg>
                            </button>
                        </div>

                        @error('password_confirmation') <div class="text-sm text-rose-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- Acciones --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2">
                <a href="{{ route('users.index') }}"
                   class="inline-flex items-center justify-center rounded-md border px-4 py-2 text-sm font-semibold hover:bg-slate-50">
                    Cancelar
                </a>

                <button type="button" wire:click="update"
                        class="inline-flex items-center justify-center rounded-md bg-gray-900 px-5 py-2 text-sm font-semibold text-white shadow-sm
                               hover:bg-black focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2">
                    💾 Guardar cambios
                </button>
            </div>

        </div>
    </div>
</div>
