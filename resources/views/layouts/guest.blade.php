<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }} - Acceso</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-100 text-slate-900">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            <!-- Card -->
            <div class="bg-white/90 backdrop-blur rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <!-- Header -->
                <div class="h-16 flex items-center px-6 border-b">
                    <a href="{{ route('login') }}" class="flex items-center gap-3 min-w-0">

                        <img id="brandLogo" src="{{ asset('assets/brand/logo.png') }}"
                            alt="Logo {{ config('app.name') }}" class="h-9 w-9 rounded-lg object-contain bg-white"
                            onerror="this.style.display='none'; document.getElementById('brandFallbackIcon').classList.remove('hidden');">

                        <div id="brandFallbackIcon"
                            class="hidden flex h-9 w-9 rounded-lg bg-slate-900 text-white items-center justify-center font-extrabold">
                            {{ strtoupper(substr(config('app.name'), 0, 1)) }}
                        </div>

                        <div class="min-w-0">
                            <div class="text-lg font-extrabold tracking-tight text-slate-900 truncate">
                                {{ config('app.name') }} <span class="text-indigo-600">Politic</span>
                            </div>
                            <div class="text-xs text-slate-500 truncate">
                                Acceso administrativo
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Body -->
                <div class="p-6 sm:p-8">
                    {{ $slot }}
                </div>
            </div>

            <!-- Footer -->
            <div class="text-xs text-slate-500 text-center mt-6">
                © {{ date('Y') }} {{ config('app.name') }} • Todos los derechos reservados
            </div>
        </div>
    </div>

    @livewireScripts
</body>

</html>
