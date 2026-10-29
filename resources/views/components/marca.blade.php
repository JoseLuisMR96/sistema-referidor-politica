{{--
    Colores del candidato: redefine las variables --marca-* (ver
    App\Support\Marca y tailwind.config.js). Sin candidato o sin colores no
    imprime nada y rigen los valores por defecto de app.css.
--}}
@props(['tenant' => null])

@php($cssMarca = \App\Support\Marca::css($tenant))

@if ($cssMarca !== '')
    <style>{!! $cssMarca !!}</style>
@endif

@if ($tenant?->logoUrl())
    <link rel="icon" href="{{ $tenant->logoUrl() }}">
@endif
