<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['tema-marca' => \App\Support\Marca::temaCompleto($tenantMarca ?? null)])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- $tenantMarca: el candidato del link (lo pasan los formularios públicos) --}}
    <title>{{ isset($tenantMarca) && $tenantMarca ? $tenantMarca->nombre : config('app.name') }} - Registro</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <x-marca :tenant="$tenantMarca ?? null" />
</head>

<body class="min-h-screen bg-gray-100 border-t-4 border-secundario">
    @if (($tenantMarca ?? null)?->fondoUrl())
        <div aria-hidden="true" class="pointer-events-none fixed inset-x-0 bottom-0 z-0 opacity-25 bg-no-repeat"
            style="aspect-ratio: {{ $tenantMarca->fondoProporcion() }}; max-height: 100vh; background-image: url('{{ $tenantMarca->fondoUrl() }}'); background-position: left bottom; background-size: cover;"></div>
    @endif
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
