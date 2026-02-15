@props([
    'route',
    'icon' => null,   // nombre del icono
    'pattern' => null
])

@php
    $isActive = request()->routeIs($pattern ?: $route);
@endphp

<a href="{{ route($route) }}"
   {{ $attributes->merge([
       'class' =>
           'flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-semibold transition ' .
           ($isActive
               ? 'bg-slate-900 text-white shadow-sm'
               : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900')
   ]) }}>

    @if($icon)
        <x-icon :name="$icon"
                class="w-5 h-5 shrink-0 {{ $isActive ? 'text-white' : 'text-slate-500' }}" />
    @endif

    <span class="truncate">{{ $slot }}</span>
</a>
