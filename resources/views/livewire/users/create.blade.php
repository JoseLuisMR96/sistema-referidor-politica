<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear usuario</h2>
        <a href="{{ route('users.index') }}" class="px-4 py-2 rounded-md border text-sm hover:bg-gray-50">
            Volver
        </a>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-lg font-semibold text-gray-800">Nuevo usuario</div>
                    <div class="text-xs text-gray-500">Acceso a la plataforma + roles</div>
                </div>
            </div>

            <form wire:submit.prevent="save" class="mt-6 space-y-5">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Nombre</label>
                        <input wire:model.live="name" type="text" autocomplete="name"
                            class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Ej: Juan Pérez" />
                        @error('name')
                            <div class="text-sm text-rose-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Email</label>
                        <input wire:model.live="email" type="email" autocomplete="username"
                            class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="correo@dominio.com" />
                        @error('email')
                            <div class="text-sm text-rose-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Password --}}
                    <div x-data="{ show: false }" class="relative">
                        <label class="block text-sm font-semibold text-gray-700">Contraseña</label>

                        <input :type="show ? 'text' : 'password'" wire:model.live="password" autocomplete="new-password"
                            class="mt-1 w-full rounded-md border-gray-300 pr-10
               focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Mínimo 8 caracteres" />

                        <button type="button" @click="show = !show"
                            class="absolute right-3 top-[38px] text-gray-500 hover:text-gray-700 focus:outline-none"
                            tabindex="-1">
                            {{-- Ojo abierto --}}
                            <svg x-show="!show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5
                     c4.478 0 8.268 2.943 9.542 7
                     -1.274 4.057-5.064 7-9.542 7
                     -4.477 0-8.268-2.943-9.542-7z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>

                            {{-- Ojo cerrado --}}
                            <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.584 10.587A2 2 0 0012 14
                     a2 2 0 001.414-.586" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.363 5.365A9.77 9.77 0 0112 5
                     c4.478 0 8.268 2.943 9.542 7
                     a9.77 9.77 0 01-1.249 2.592" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.227 6.227A9.77 9.77 0 002.458 12
                     c1.274 4.057 5.064 7 9.542 7
                     a9.77 9.77 0 002.773-.401" />
                            </svg>
                        </button>

                        @error('password')
                            <div class="text-sm text-rose-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Confirm password --}}
                    <div x-data="{ show: false }">
                        <label class="block text-sm font-semibold text-gray-700">Confirmar contraseña</label>

                        <div class="mt-1 relative">
                            <input :type="show ? 'text' : 'password'" wire:model.live="password_confirmation"
                                class="w-full rounded-md border-gray-300 pr-12 focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Repite la contraseña" autocomplete="new-password" />

                            <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700"
                                :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                                <svg x-show="!show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5
                     c4.478 0 8.268 2.943 9.542 7
                     -1.274 4.057-5.064 7-9.542 7
                     -4.477 0-8.268-2.943-9.542-7z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>

                                {{-- Ojo cerrado --}}
                                <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.584 10.587A2 2 0 0012 14
                     a2 2 0 001.414-.586" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.363 5.365A9.77 9.77 0 0112 5
                     c4.478 0 8.268 2.943 9.542 7
                     a9.77 9.77 0 01-1.249 2.592" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.227 6.227A9.77 9.77 0 002.458 12
                     c1.274 4.057 5.064 7 9.542 7
                     a9.77 9.77 0 002.773-.401" />
                                </svg>
                            </button>
                        </div>
                    </div>

                </div>


                <div class="rounded-lg border bg-gray-50 p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-800">Roles</div>
                        <div class="text-xs text-gray-500">Selecciona uno o varios</div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($availableRoles as $role)
                            <label
                                class="flex items-center gap-2 rounded-md border bg-white px-3 py-2 hover:bg-gray-50">
                                <input type="checkbox" value="{{ $role->name }}" wire:model.live="roles"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">{{ $role->name }}</span>
                            </label>
                        @endforeach
                    </div>

                    @error('roles')
                        <div class="text-sm text-rose-600 mt-2">{{ $message }}</div>
                    @enderror
                    @error('roles.*')
                        <div class="text-sm text-rose-600 mt-2">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ route('users.index') }}" class="px-4 py-2 rounded-md border text-sm hover:bg-gray-50">
                        Cancelar
                    </a>

                    <button type="submit"
                        class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                   hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                        ✅ Crear usuario
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
