<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <!-- Correo -->
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input
                id="email"
                class="block mt-1 w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900/20"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
                placeholder="tu@correo.com"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Contraseña -->
        <div>
            <x-input-label for="password" value="Contraseña" />

            <div class="mt-1 relative">
                <x-text-input
                    id="password"
                    class="block w-full pr-12 rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900/20"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />

                <button
                    type="button"
                    id="togglePassword"
                    class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-500 hover:text-slate-900"
                    aria-label="Mostrar/ocultar contraseña"
                >
                    <!-- Ojo abierto -->
                    <svg id="iconEye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="w-5 h-5">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>

                    <!-- Ojo cerrado -->
                    <svg id="iconEyeOff" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="w-5 h-5 hidden">
                        <path d="M3 3l18 18"></path>
                        <path d="M10.58 10.58A2 2 0 0 0 12 14a2 2 0 0 0 1.42-.58"></path>
                        <path d="M9.88 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7a18.5 18.5 0 0 1-4.32 5.28"></path>
                        <path d="M6.61 6.61A18.5 18.5 0 0 0 2 12s3.5 7 10 7a10.45 10.45 0 0 0 4.2-.86"></path>
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Recordarme / Olvidé contraseña -->
        <div class="flex items-center justify-between pt-1">
            <label for="remember_me" class="inline-flex items-center gap-2">
                <input
                    id="remember_me"
                    type="checkbox"
                    class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-900/20"
                    name="remember"
                >
                <span class="text-sm text-slate-600">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a
                    class="text-sm text-slate-600 hover:text-slate-900 underline underline-offset-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-900/20"
                    href="{{ route('password.request') }}"
                >
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <!-- Botón -->
        <div class="pt-2">
            <x-primary-button class="w-full justify-center rounded-xl bg-slate-900 hover:bg-slate-800 focus:bg-slate-900 active:bg-slate-900">
                Ingresar
            </x-primary-button>
        </div>

        <div class="pt-3 text-xs text-slate-500 text-center">
            Acceso exclusivo para usuarios autorizados.
        </div>
    </form>

    <script>
        (function () {
            const pass = document.getElementById('password');
            const btn  = document.getElementById('togglePassword');
            const eye  = document.getElementById('iconEye');
            const off  = document.getElementById('iconEyeOff');

            if (!pass || !btn) return;

            btn.addEventListener('click', () => {
                const show = pass.type === 'password';
                pass.type = show ? 'text' : 'password';

                if (eye && off) {
                    eye.classList.toggle('hidden', show);
                    off.classList.toggle('hidden', !show);
                }

                btn.setAttribute('aria-pressed', String(show));
            });
        })();
    </script>
</x-guest-layout>
