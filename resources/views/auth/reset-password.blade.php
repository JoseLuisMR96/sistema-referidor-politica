<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        {{-- Token de restablecimiento --}}
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        {{-- Correo electrónico --}}
        <div>
            <x-input-label for="email" value="Correo electrónico" />

            {{-- Visible, no editable --}}
            <x-text-input id="email" class="block mt-1 w-full bg-slate-50 text-slate-600 cursor-not-allowed"
                type="email" name="email" :value="old('email', $request->email)" required readonly autocomplete="username" />

            {{-- Extra: asegura que siempre se envíe aunque el browser haga cosas raras --}}
            <input type="hidden" name="email" value="{{ old('email', $request->email) }}">

            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- Nueva contraseña --}}
        <div x-data="{ show: false }">
            <x-input-label for="password" value="Nueva contraseña" />

            <div class="relative mt-1">
                <x-text-input id="password" class="block w-full pr-12" ::type="show ? 'text' : 'password'" name="password" required
                    autocomplete="new-password" />

                <button type="button"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-500 hover:text-slate-700 focus:outline-none"
                    @click="show = !show" :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                    :title="show ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                    {{-- Ojo abierto --}}
                    <svg x-show="!show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>

                    {{-- Ojo cerrado --}}
                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a21.82 21.82 0 0 1 5.06-6.94" />
                        <path d="M9.9 4.24A10.94 10.94 0 0 1 12 5c6.5 0 10 7 10 7a21.82 21.82 0 0 1-3.17 4.34" />
                        <path d="M14.12 14.12a3 3 0 0 1-4.24-4.24" />
                        <path d="M1 1l22 22" />
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Confirmar contraseña --}}
        <div x-data="{ show: false }">
            <x-input-label for="password_confirmation" value="Confirmar contraseña" />

            <div class="relative mt-1">
                <x-text-input id="password_confirmation" class="block w-full pr-12" ::type="show ? 'text' : 'password'"
                    name="password_confirmation" required autocomplete="new-password" />

                <button type="button"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-500 hover:text-slate-700 focus:outline-none"
                    @click="show = !show" :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                    :title="show ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                    {{-- Ojo abierto --}}
                    <svg x-show="!show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>

                    {{-- Ojo cerrado --}}
                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a21.82 21.82 0 0 1 5.06-6.94" />
                        <path d="M9.9 4.24A10.94 10.94 0 0 1 12 5c6.5 0 10 7 10 7a21.82 21.82 0 0 1-3.17 4.34" />
                        <path d="M14.12 14.12a3 3 0 0 1-4.24-4.24" />
                        <path d="M1 1l22 22" />
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end pt-2">
            <x-primary-button>
                Restablecer contraseña
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
