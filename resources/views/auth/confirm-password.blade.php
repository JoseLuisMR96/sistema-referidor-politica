<x-guest-layout>
    <div class="mb-5 text-sm text-slate-600">
        Esta es un área segura de la aplicación. Por favor confirma tu contraseña para continuar.
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        {{-- Contraseña --}}
        <div class="space-y-1">
            <x-input-label for="password" value="Contraseña" />

            <div class="relative">
                <x-text-input
                    id="password"
                    class="block mt-1 w-full pr-12 rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                />

                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-500 hover:text-slate-700 focus:outline-none"
                    aria-label="Mostrar u ocultar contraseña"
                    onclick="
                        const input = document.getElementById('password');
                        const openEye = this.querySelector('[data-eye=open]');
                        const closedEye = this.querySelector('[data-eye=closed]');
                        const isPassword = input.type === 'password';
                        input.type = isPassword ? 'text' : 'password';
                        openEye.classList.toggle('hidden', !isPassword);
                        closedEye.classList.toggle('hidden', isPassword);
                    "
                >
                    {{-- Ojo abierto --}}
                    <svg data-eye="open" class="h-5 w-5 hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M2.25 12s3.75-7.5 9.75-7.5S21.75 12 21.75 12 18 19.5 12 19.5 2.25 12 2.25 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 15.375A3.375 3.375 0 1 0 12 8.625a3.375 3.375 0 0 0 0 6.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>

                    {{-- Ojo tachado --}}
                    <svg data-eye="closed" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M10.477 10.477a2.25 2.25 0 0 0 3.182 3.182" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M6.228 6.228C3.934 7.86 2.25 12 2.25 12s3.75 7.5 9.75 7.5c1.517 0 2.9-.322 4.122-.86" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9.88 4.73A9.53 9.53 0 0 1 12 4.5c6 0 9.75 7.5 9.75 7.5s-1.354 2.71-3.767 4.862" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end pt-1">
            <x-primary-button class="rounded-xl">
                Confirmar
            </x-primary-button>
        </div>
    </form>

    <script>
        // Estado inicial correcto de iconos (password => mostrar ojo tachado)
        (function(){
            const input = document.getElementById('password');
            const btn = input?.parentElement?.querySelector('button');
            if (!input || !btn) return;

            const openEye = btn.querySelector('[data-eye=open]');
            const closedEye = btn.querySelector('[data-eye=closed]');
            const isPassword = input.type === 'password';

            openEye?.classList.toggle('hidden', !isPassword);
            closedEye?.classList.toggle('hidden', isPassword);
        })();
    </script>
</x-guest-layout>
