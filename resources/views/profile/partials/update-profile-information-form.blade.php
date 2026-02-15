<section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    {{-- Header --}}
    <header class="px-6 sm:px-8 py-6 border-b bg-slate-50/70">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-lg font-extrabold tracking-tight text-slate-900">
                    Información del perfil
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    Actualiza la información de tu cuenta y tu dirección de correo electrónico.
                </p>
            </div>

            <div class="hidden sm:flex items-center gap-2 text-xs text-slate-500">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Ajustes de cuenta
            </div>
        </div>
    </header>

    {{-- Forms --}}
    <div class="px-6 sm:px-8 py-6">

        <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
            @csrf
            @method('patch')

            {{-- Name --}}
            <div class="space-y-1">
                <x-input-label for="name" value="Nombre" />
                <x-text-input
                    id="name"
                    name="name"
                    type="text"
                    class="mt-1 block w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                    :value="old('name', $user->name)"
                    required
                    autofocus
                    autocomplete="name"
                />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            {{-- Email --}}
            <div class="space-y-1">
                <x-input-label for="email" value="Correo electrónico" />
                <x-text-input
                    id="email"
                    name="email"
                    type="email"
                    class="mt-1 block w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                    :value="old('email', $user->email)"
                    required
                    autocomplete="username"
                />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 h-8 w-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                                ⚠️
                            </div>

                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-amber-900">
                                    Tu correo electrónico aún no está verificado.
                                </p>
                                <p class="text-sm text-amber-800 mt-1">
                                    Para continuar sin inconvenientes, verifica tu correo o solicita un nuevo enlace.
                                </p>

                                <button
                                    form="send-verification"
                                    class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-amber-900 underline underline-offset-4 hover:text-amber-950 focus:outline-none focus:ring-2 focus:ring-amber-300 rounded-md"
                                >
                                    Reenviar correo de verificación
                                </button>

                                @if (session('status') === 'verification-link-sent')
                                    <p class="mt-2 text-sm font-semibold text-emerald-700">
                                        Se envió un nuevo enlace de verificación a tu correo.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 pt-2">
                <x-primary-button class="w-full sm:w-auto justify-center rounded-xl">
                    Guardar cambios
                </x-primary-button>

                @if (session('status') === 'profile-updated')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition.opacity.duration.200ms
                        x-init="setTimeout(() => show = false, 2200)"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700"
                    >
                        <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                        Cambios guardados.
                    </p>
                @endif

                <div class="sm:ml-auto text-xs text-slate-500">
                    Tip: usa un correo válido para recuperación de acceso 😉
                </div>
            </div>
        </form>
    </div>
</section>
