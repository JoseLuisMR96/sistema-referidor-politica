@props(['header' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- SweetAlert (opcional) --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @livewireStyles
</head>

<body class="font-sans antialiased bg-gray-50 text-gray-800">
    <div x-data="{ open: false }" class="min-h-screen flex">

        {{-- Overlay mobile --}}
        <div x-show="open" x-cloak @click="open=false" class="fixed inset-0 bg-black/40 z-30 lg:hidden"
            x-transition.opacity></div>

        {{-- SIDEBAR --}}
        <aside :class="open ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-40 w-72 bg-white border-r shadow-sm
               transform transition-transform duration-200
               lg:static lg:translate-x-0 lg:flex lg:flex-col">
            {{-- Brand --}}
            <div class="h-16 flex items-center px-6 border-b">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">

                    {{-- Logo --}}
                    <img src="{{ asset('assets/brand/logo.png') }}" alt="{{ config('app.name') }} logo"
                        class="h-9 w-9 rounded-lg object-contain"
                        onerror="
                this.style.display='none';
                this.closest('a').querySelector('[data-fallback]').classList.remove('hidden');
                this.closest('a').querySelector('[data-brand]').classList.add('hidden');
            ">

                    {{-- Marca normal (cuando el logo carga) --}}
                    <div data-brand class="min-w-0">
                        <div class="text-lg font-extrabold tracking-tight text-slate-900 truncate">
                            {{ config('app.name') }} <span class="text-indigo-600">Politic</span>
                        </div>
                        <div class="text-xs text-slate-500 truncate">
                            Panel administrativo
                        </div>
                    </div>

                    {{-- Fallback si el logo no existe --}}
                    <div data-fallback class="hidden min-w-0">
                        <div class="text-lg font-extrabold tracking-tight text-slate-900 truncate">
                            {{ config('app.name') }} <span class="text-indigo-600">Politic</span>
                        </div>
                        <div class="text-xs text-slate-500 truncate">
                            Panel administrativo (sin logo)
                        </div>
                    </div>
                </a>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 p-4 space-y-1 text-sm">

                <div class="text-xs uppercase tracking-wider text-gray-400 px-3 mb-2">
                    Principal
                </div>

                @can('dashboard.ver')
                    <x-sidebar-link route="dashboard" icon="home">
                        Dashboard
                    </x-sidebar-link>
                @endcan

                @can('whatsapp.enviar')
                    <x-sidebar-link route="whatsapp.campaigns.create" icon="whatsapp">
                        WhatsApp Masivo
                    </x-sidebar-link>
                @endcan

                @can('registros.ver_todos')
                    <x-sidebar-link route="registrations.index" icon="document-text">
                        Registros
                    </x-sidebar-link>
                @endcan

                @can('importados.ver')
                    <x-sidebar-link route="imported-people.index" icon="arrow-down-tray">
                        Importados
                    </x-sidebar-link>
                @endcan

                <div class="mt-4 pt-4 border-t"></div>

                <div class="text-xs uppercase tracking-wider text-gray-400 px-3 mb-2">
                    Administración
                </div>

                @can('referidores.ver')
                    <x-sidebar-link route="referrers.index" icon="link">
                        Referidores
                    </x-sidebar-link>
                @endcan

                @can('usuarios.ver')
                    <x-sidebar-link route="users.index" icon="users">
                        Usuarios
                    </x-sidebar-link>
                @endcan

            </nav>

            {{-- Usuario (desktop) --}}
            <div class="border-t p-4 hidden lg:block">
                <div class="flex items-center gap-3">
                    <div
                        class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center
                           font-bold text-indigo-700">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold truncate">
                            {{ Auth::user()->name }}
                        </div>
                        <div class="text-xs text-gray-500 truncate">
                            {{ Auth::user()->roles->pluck('name')->join(', ') ?: '—' }}
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        {{-- MAIN --}}
        <div class="flex-1 flex flex-col min-w-0">

            {{-- TOPBAR --}}
            <header class="h-16 bg-white border-b flex items-center px-6 gap-4">
                <button type="button" @click="open=!open"
                    class="lg:hidden h-10 w-10 rounded-lg border hover:bg-gray-50">
                    ☰
                </button>

                <div class="font-semibold lg:hidden">
                    {{ config('app.name') }}
                </div>

                <div class="ml-auto">
                    <x-dropdown align="right">
                        <x-slot name="trigger">
                            <button type="button"
                                class="flex items-center gap-3 rounded-lg border px-3 py-2 hover:bg-gray-50">
                                <div
                                    class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center
                                       text-indigo-700 text-sm font-bold">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>

                                <span class="hidden sm:block text-sm font-semibold">
                                    {{ Auth::user()->name }}
                                </span>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 py-2 text-xs text-gray-500 border-b">
                                {{ Auth::user()->roles->pluck('name')->join(', ') ?: '—' }}
                            </div>

                            <x-dropdown-link :href="route('profile.edit')">
                                Perfil
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();"
                                    class="text-rose-600">
                                    Cerrar sesión
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </header>

            {{-- PAGE HEADER --}}
            @if ($header)
                <div class="bg-white border-b px-6 py-4">
                    {{ $header }}
                </div>
            @endif

            {{-- CONTENT --}}
            <main class="flex-1 px-4 sm:px-6 py-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>

</html>
