@props(['header' => null])

@php
    $tenantCtx = app(\App\Support\TenantContext::class);
    $tenantActual = $tenantCtx->tenant();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['tema-marca' => \App\Support\Marca::temaCompleto($tenantActual)])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $tenantActual ? $tenantActual->nombre.' · ' : '' }}{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- SweetAlert (opcional) --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @livewireStyles

    {{-- Colores, favicon y título del candidato elegido --}}
    <x-marca :tenant="$tenantActual" />
</head>

<body class="font-sans antialiased bg-gray-50 text-gray-800 border-t-4 border-secundario">
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
                @if ($tenantActual?->logoUrl())
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">
                        <img src="{{ $tenantActual->logoUrl() }}" alt="Logo {{ $tenantActual->partido ?: $tenantActual->nombre }}"
                            class="h-10 w-10 rounded-lg object-contain">
                        <div class="min-w-0">
                            <div class="text-base font-extrabold tracking-tight text-indigo-700 truncate">{{ $tenantActual->nombre }}</div>
                            <div class="text-xs text-slate-500 truncate">{{ $tenantActual->slogan ?: $tenantActual->partido }}</div>
                        </div>
                    </a>
                @else
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">

                    {{-- Logo completo (urna + nombre); si no carga, el nombre en texto --}}
                    <div class="min-w-0">
                        <img src="{{ asset('assets/brand/logo-completo.png') }}" alt="{{ config('app.name') }}"
                            class="h-9 w-auto max-w-full object-contain"
                            onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">
                        <div class="hidden text-lg font-extrabold tracking-tight text-slate-900 truncate">
                            {{ config('app.name') }}
                        </div>
                        <div class="text-xs text-slate-500 truncate">Panel administrativo</div>
                    </div>
                </a>
                @endif
            </div>

            {{-- Tenant (candidato) con el que se trabaja --}}
            <div class="px-4 pt-4">
                @if (Auth::user()->esGlobal())
                    <form method="POST" action="{{ route('tenant.seleccionar') }}">
                        @csrf
                        <label for="tenant-selector" class="block text-xs uppercase tracking-wider text-gray-400 mb-1">
                            Candidato
                        </label>
                        <select id="tenant-selector" name="tenant_id" onchange="this.form.submit()"
                            class="w-full rounded-lg border-gray-300 text-sm font-semibold">
                            <option value="">Todos los tenants (solo consulta)</option>
                            @foreach (\App\Models\Tenant::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']) as $t)
                                <option value="{{ $t->id }}" @selected($tenantActual?->id === $t->id)>{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                        <noscript><button class="mt-1 text-xs underline">Cambiar</button></noscript>
                    </form>
                @else
                    <div class="rounded-lg border bg-indigo-50 px-3 py-2">
                        <div class="text-xs uppercase tracking-wider text-indigo-400">Candidato</div>
                        <div class="text-sm font-semibold text-indigo-900 truncate">{{ $tenantActual?->nombre }}</div>
                    </div>
                @endif
                @if ($tenantActual?->fotoUrl() || $tenantActual?->partido)
                    <div class="mt-3 flex items-center gap-3 rounded-xl bg-indigo-50 p-3">
                        @if ($tenantActual->fotoUrl())
                            <img src="{{ $tenantActual->fotoUrl() }}" alt="Foto de {{ $tenantActual->nombre }}"
                                class="h-12 w-12 rounded-full object-cover ring-2 ring-indigo-600">
                        @endif
                        <div class="min-w-0">
                            @if ($tenantActual->partido)
                                <div class="text-xs font-semibold text-indigo-800 truncate">{{ $tenantActual->partido }}</div>
                            @endif
                            <div class="text-xs text-gray-600 truncate">{{ $tenantActual->cargo ? $tenantActual->circunscripcionTexto() : $tenantActual->descripcion }}</div>
                        </div>
                    </div>
                @elseif ($tenantActual?->descripcion)
                    <div class="mt-1 text-xs text-gray-500 truncate">{{ $tenantActual->descripcion }}</div>
                @endif
            </div>

            {{-- Navigation: agrupado por uso; una sección solo se ve si el usuario
                 tiene acceso a algo de ella --}}
            @php
                $usuarioMenu = Auth::user();
                $verCandidatos = $usuarioMenu->esGlobal() && $usuarioMenu->can('tenants.gestionar');
                $tituloSeccion = 'text-xs uppercase tracking-wider text-gray-400 px-3 mb-2 mt-5 first:mt-0';
            @endphp
            <nav class="flex-1 p-4 space-y-1 text-sm">

                @canany(['dashboard.ver', 'redes.ver', 'historico.ver'])
                    <div class="{{ $tituloSeccion }}">Análisis</div>

                    @can('dashboard.ver')
                        <x-sidebar-link route="dashboard" icon="chart-bar">
                            Dashboard de registros
                        </x-sidebar-link>
                    @endcan

                    @can('redes.ver')
                        <x-sidebar-link route="dashboard.redes" icon="share">
                            Dashboard de redes
                        </x-sidebar-link>
                    @endcan

                    @can('historico.ver')
                        <x-sidebar-link route="dashboard.historico" icon="map">
                            Histórico electoral
                        </x-sidebar-link>
                    @endcan
                @endcanany

                @canany(['registros.ver_todos', 'importados.ver'])
                    <div class="{{ $tituloSeccion }}">Votantes</div>

                    @can('registros.ver_todos')
                        <x-sidebar-link route="registrations.index" pattern="registrations.*" icon="document-text">
                            Registros
                        </x-sidebar-link>
                    @endcan

                    @can('importados.ver')
                        <x-sidebar-link route="imported-people.index" pattern="imported-people.*" icon="arrow-down-tray">
                            Importados
                        </x-sidebar-link>
                    @endcan
                @endcanany

                @canany(['referidores.ver', 'pregoneros_referidores.ver'])
                    <div class="{{ $tituloSeccion }}">Equipo de campo</div>

                    @can('referidores.ver')
                        <x-sidebar-link route="referrers.index" pattern="referrers.*" icon="user-add">
                            Referidores
                        </x-sidebar-link>
                    @endcan

                    @can('pregoneros_referidores.ver')
                        <x-sidebar-link route="pregoneros.referidores.index" pattern="pregoneros.referidores.*" icon="megaphone">
                            Referidores pregoneros
                        </x-sidebar-link>
                    @endcan
                @endcanany

                @can('whatsapp.enviar')
                    <div class="{{ $tituloSeccion }}">Comunicación</div>

                    <x-sidebar-link route="whatsapp.campaigns.create" icon="whatsapp">
                        WhatsApp Masivo
                    </x-sidebar-link>

                    {{-- <x-sidebar-link route="wpp.campaigns.create" icon="chat-bubble-left-right">
                        WhatsApp WPP
                    </x-sidebar-link> --}}

                    {{-- Oculto del menú (la ruta /whatsapp/whatsapp-outbox sigue funcionando)
                    <x-sidebar-link route="whatsapp.whatsapp.outbox" icon="whatsapp">
                        WhatsApp Outbox
                    </x-sidebar-link>
                    --}}
                @endcan

                @if ($verCandidatos || $usuarioMenu->canAny(['redes.configurar', 'usuarios.ver']))
                    <div class="{{ $tituloSeccion }}">Configuración</div>

                    @if ($verCandidatos)
                        <x-sidebar-link route="tenants.index" icon="flag">
                            Candidatos
                        </x-sidebar-link>
                    @endif

                    @can('redes.configurar')
                        <x-sidebar-link route="redes.index" icon="cog">
                            Redes sociales
                        </x-sidebar-link>
                    @endcan

                    @can('usuarios.ver')
                        <x-sidebar-link route="users.index" pattern="users.*" icon="users">
                            Usuarios
                        </x-sidebar-link>
                    @endcan
                @endif

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

                <div class="lg:hidden">
                    <img src="{{ asset('assets/brand/logo-completo.png') }}" alt="{{ config('app.name') }}" class="h-7 w-auto">
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

            {{-- Imagen de fondo del candidato (bandera, tigre...): decorativa, detrás de las tarjetas --}}
            @if ($tenantActual?->fondoUrl())
                <div aria-hidden="true"
                    class="pointer-events-none fixed bottom-0 right-0 left-0 lg:left-72 h-[55vh] z-0 opacity-[0.18] bg-no-repeat"
                    style="background-image: url('{{ $tenantActual->fondoUrl() }}'); background-position: left bottom; background-size: 100% auto;"></div>
            @endif

            {{-- CONTENT --}}
            <main class="relative z-10 flex-1 px-4 sm:px-6 py-6">
                @if (session('tenant_aviso'))
                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800">
                        {{ session('tenant_aviso') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')

    {{-- Tema completo: textos y líneas de las gráficas con los colores del tema
         (corre después de cargar Chart.js y antes de que se dibujen) --}}
    @if (\App\Support\Marca::temaCompleto($tenantActual))
        <script>
            (function () {
                if (!window.Chart) return;
                const css = getComputedStyle(document.documentElement);
                const color = (v, a = 1) => `rgb(${css.getPropertyValue(v).trim()} / ${a})`;
                Chart.defaults.color = color('--tema-texto-2');
                // Ojo: no cambiar Chart.defaults.borderColor/backgroundColor: eso
                // apaga la paleta automática de Chart.js y las gráficas quedan sin color.
                Chart.defaults.scale.grid.color = color('--tema-borde', 0.6);
                Chart.defaults.elements.arc.borderColor = color('--tema-superficie');
            })();
        </script>
    @endif
</body>

</html>
