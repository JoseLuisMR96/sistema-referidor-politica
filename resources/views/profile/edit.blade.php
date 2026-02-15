<x-app-sidebar-layout>
    <x-slot name="header">
        <div class="bg-white/80 backdrop-blur">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 truncate">
                        Perfil
                    </h1>
                    <p class="text-sm text-slate-600">
                        Administra tu información personal y la seguridad de tu cuenta.
                    </p>
                </div>

                <div class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Configuración de cuenta
                </div>
            </div>
        </div>
    </x-slot>
    <div class="py-6">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Contenido --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
                {{-- Info de perfil --}}
                <div>
                    @include('profile.partials.update-profile-information-form')
                </div>

                {{-- Seguridad / contraseña --}}
                <div>
                    @include('profile.partials.update-password-form')
                </div>
            </div>

        </div>
    </div>
</x-app-sidebar-layout>
