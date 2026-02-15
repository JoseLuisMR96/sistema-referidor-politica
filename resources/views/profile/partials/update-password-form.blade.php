<section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    {{-- Header --}}
    <header class="px-6 sm:px-8 py-6 border-b bg-slate-50/70">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-lg font-extrabold tracking-tight text-slate-900">
                    Actualizar contraseña
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    Asegúrate de usar una contraseña larga y difícil de adivinar para mantener tu cuenta segura.
                </p>
            </div>

            <div class="hidden sm:flex items-center gap-2 text-xs text-slate-500">
                <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
                Seguridad
            </div>
        </div>
    </header>

    {{-- Form --}}
    <div class="px-6 sm:px-8 py-6">
        <form method="post" action="{{ route('password.update') }}" class="space-y-6">
            @csrf
            @method('put')

            {{-- Current Password --}}
            <div class="space-y-1">
                <x-input-label for="update_password_current_password" value="Contraseña actual" />

                <div class="relative">
                    <x-text-input id="update_password_current_password" name="current_password" type="password"
                        class="mt-1 block w-full rounded-xl border-slate-300 pr-12 focus:border-indigo-500 focus:ring-indigo-500"
                        autocomplete="current-password" />

                    <button type="button"
                        class="absolute inset-y-0 right-0 mt-1 flex items-center justify-center w-12 text-slate-500 hover:text-slate-900 focus:outline-none"
                        aria-label="Mostrar u ocultar contraseña" aria-pressed="false"
                        onclick="togglePassword('update_password_current_password', this)">
                        {{-- Eye (show) --}}
                        <svg data-eye class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor"
                                stroke-width="2" stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                        </svg>

                        {{-- Eye off (hide) --}}
                        <svg data-eye-off class="h-5 w-5 hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" />
                            <path d="M6.5 6.5C4 8.7 2 12 2 12s3.5 7 10 7c2 0 3.7-.6 5.1-1.5" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9.9 4.2A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a18.4 18.4 0 0 1-3.2 4.4"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            {{-- New Password --}}
            <div class="space-y-1">
                <x-input-label for="update_password_password" value="Nueva contraseña" />

                <div class="relative">
                    <x-text-input id="update_password_password" name="password" type="password"
                        class="mt-1 block w-full rounded-xl border-slate-300 pr-12 focus:border-indigo-500 focus:ring-indigo-500"
                        autocomplete="new-password" />

                    <button type="button"
                        class="absolute inset-y-0 right-0 mt-1 flex items-center justify-center w-12 text-slate-500 hover:text-slate-900 focus:outline-none"
                        aria-label="Mostrar u ocultar contraseña" aria-pressed="false"
                        onclick="togglePassword('update_password_password', this)">
                        <svg data-eye class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor"
                                stroke-width="2" stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                        </svg>
                        <svg data-eye-off class="h-5 w-5 hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" />
                            <path d="M6.5 6.5C4 8.7 2 12 2 12s3.5 7 10 7c2 0 3.7-.6 5.1-1.5" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9.9 4.2A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a18.4 18.4 0 0 1-3.2 4.4"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                <p class="text-xs text-slate-500 mt-2">
                    Recomendación: mínimo 12 caracteres, mezcla letras, números y símbolos.
                </p>
            </div>

            {{-- Confirm Password --}}
            <div class="space-y-1">
                <x-input-label for="update_password_password_confirmation" value="Confirmar contraseña" />

                <div class="relative">
                    <x-text-input id="update_password_password_confirmation" name="password_confirmation"
                        type="password"
                        class="mt-1 block w-full rounded-xl border-slate-300 pr-12 focus:border-indigo-500 focus:ring-indigo-500"
                        autocomplete="new-password" />

                    <button type="button"
                        class="absolute inset-y-0 right-0 mt-1 flex items-center justify-center w-12 text-slate-500 hover:text-slate-900 focus:outline-none"
                        aria-label="Mostrar u ocultar contraseña" aria-pressed="false"
                        onclick="togglePassword('update_password_password_confirmation', this)">
                        <svg data-eye class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor"
                                stroke-width="2" stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                        </svg>
                        <svg data-eye-off class="h-5 w-5 hidden" viewBox="0 0 24 24" fill="none"
                            aria-hidden="true">
                            <path d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" />
                            <path d="M6.5 6.5C4 8.7 2 12 2 12s3.5 7 10 7c2 0 3.7-.6 5.1-1.5" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9.9 4.2A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a18.4 18.4 0 0 1-3.2 4.4"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 pt-2">
                <x-primary-button class="w-full sm:w-auto justify-center rounded-xl">
                    Guardar cambios
                </x-primary-button>

                @if (session('status') === 'password-updated')
                    <p x-data="{ show: true }" x-show="show" x-transition.opacity.duration.200ms
                        x-init="setTimeout(() => show = false, 2200)"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                        Contraseña actualizada.
                    </p>
                @endif

                <div class="sm:ml-auto text-xs text-slate-500">
                    Consejo: no la reutilices en otros sistemas 😄
                </div>
            </div>
        </form>
    </div>
</section>

{{-- Script (puedes moverlo a tu app.js si prefieres) --}}
<script>
    function togglePassword(inputId, buttonEl) {
        const input = document.getElementById(inputId);
        if (!input) return;

        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';

        const eye = buttonEl.querySelector('[data-eye]');
        const eyeOff = buttonEl.querySelector('[data-eye-off]');

        if (eye && eyeOff) {
            eye.classList.toggle('hidden', isHidden);
            eyeOff.classList.toggle('hidden', !isHidden);
        }

        buttonEl.setAttribute('aria-pressed', String(isHidden));
    }
</script>
