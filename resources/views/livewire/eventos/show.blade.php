@php
    use App\Models\EventoCampana;

    $e = $evento;
    $pesos = fn ($v) => $v === null ? '—' : '$ '.number_format((float) $v, 0, ',', '.');
    $estado = EventoCampana::ESTADOS[$e->estado] ?? [$e->estado, 'bg-gray-100 text-gray-700'];
    $fotos = $evidencias->where('tipo', 'foto');
    $documentos = $evidencias->where('tipo', '!=', 'foto');
@endphp

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $e->titulo }}</h2>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $estado[1] }}">{{ $estado[0] }}</span>
            </div>
            <div class="text-sm text-gray-500">{{ $e->tipoTexto() }} · {{ $e->fecha->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY, h:mm a') }}</div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('eventos.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">← Eventos</a>
            @can('eventos.editar')
                <a href="{{ route('eventos.edit', $e) }}" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Editar</a>
            @endcan
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Dónde --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5 space-y-2">
                <div class="text-xs uppercase tracking-wider text-gray-400">Dónde</div>
                <div class="text-lg font-bold text-gray-900">{{ $e->municipio?->nombre }}</div>
                <div class="text-sm text-gray-500">{{ $e->departamento?->nombre }}</div>
                @if ($e->lugar)
                    <div class="text-sm"><span class="font-semibold">Sitio:</span> {{ $e->lugar }}</div>
                @endif
                @if ($e->direccion)
                    <div class="text-sm"><span class="font-semibold">Dirección:</span> {{ $e->direccion }}</div>
                @endif
                @if ($e->tieneUbicacion())
                    <a href="https://www.google.com/maps?q={{ $e->latitud }},{{ $e->longitud }}" target="_blank" rel="noopener"
                        class="inline-block text-sm font-semibold text-indigo-700 underline">📍 Ver en Google Maps</a>
                @endif
            </div>

            {{-- Quién --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5 space-y-2">
                <div class="text-xs uppercase tracking-wider text-gray-400">Quién fue</div>
                @if ($e->asistio_candidato)
                    <div class="inline-flex rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-800">El candidato estuvo en persona</div>
                @else
                    <div class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Fue el equipo en su nombre</div>
                @endif
                <div class="text-sm">
                    <span class="font-semibold">Responsable:</span>
                    {{ $e->responsable ? "{$e->responsable->nombre} ({$e->responsable->rolTexto()})" : ($e->responsableTexto() ?? '—') }}
                </div>
                @if ($e->integrantes->isNotEmpty())
                    <div class="text-sm"><span class="font-semibold">También fueron:</span> {{ $e->integrantes->pluck('nombre')->join(', ') }}</div>
                @endif
                <div class="text-sm"><span class="font-semibold">Asistentes:</span> {{ $e->asistentes !== null ? number_format($e->asistentes, 0, ',', '.') : '—' }}</div>
            </div>

            {{-- Dinero --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5 space-y-2">
                <div class="text-xs uppercase tracking-wider text-gray-400">Dinero</div>
                <div class="text-sm"><span class="font-semibold">Asignado:</span> {{ $pesos($e->monto_asignado) }}</div>
                <div class="text-sm"><span class="font-semibold">Gastado:</span> {{ $e->monto_gastado !== null ? $pesos($e->monto_gastado) : 'Sin legalizar' }}</div>
                @if ($e->monto_gastado !== null && $e->monto_asignado > 0)
                    @php($diferencia = (float) $e->monto_asignado - (float) $e->monto_gastado)
                    <div class="text-sm {{ $diferencia < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                        {{ $diferencia < 0 ? 'Se pasó por '.$pesos(-$diferencia) : 'Sobró '.$pesos($diferencia) }}
                    </div>
                @endif
                @if ($e->responsableTexto() && $e->monto_asignado > 0)
                    <div class="text-xs text-gray-500">A nombre de {{ $e->responsable?->nombre ?? 'el candidato' }}.</div>
                @endif
            </div>
        </div>

        @if ($e->descripcion || $e->resultado)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-xs uppercase tracking-wider text-gray-400 mb-1">Qué se habló o propuso</div>
                    <div class="text-sm text-gray-700 whitespace-pre-line">{{ $e->descripcion ?: '—' }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-xs uppercase tracking-wider text-gray-400 mb-1">Resultado</div>
                    <div class="text-sm text-gray-700 whitespace-pre-line">{{ $e->resultado ?: '—' }}</div>
                </div>
            </div>
        @endif

        {{-- Evidencias --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-5 space-y-4" x-data="{ grande: null }">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div class="text-lg font-semibold text-gray-900">Evidencias ({{ $evidencias->count() }})</div>
                @can('eventos.editar')
                    <a href="{{ route('eventos.edit', $e) }}" class="text-sm font-semibold text-indigo-700 hover:underline">Agregar evidencias</a>
                @endcan
            </div>

            @if ($evidencias->isEmpty())
                <div class="text-sm text-gray-500">Este evento no tiene fotos ni soportes.</div>
            @endif

            @if ($fotos->isNotEmpty())
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach ($fotos as $f)
                        <div class="group relative" wire:key="ev-{{ $f->id }}">
                            <button type="button" x-on:click="grande = @js($f->url())" class="block w-full">
                                <img src="{{ $f->url() }}" alt="{{ $f->nombre }}" loading="lazy" class="h-32 w-full rounded-lg object-cover border">
                            </button>
                            @can('eventos.editar')
                                <button type="button" wire:click="quitarEvidencia({{ $f->id }})" wire:confirm="¿Quitar esta foto?"
                                    class="absolute right-1 top-1 hidden rounded bg-white/90 px-1.5 text-xs font-bold text-rose-600 shadow group-hover:block">✕</button>
                            @endcan
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($documentos->isNotEmpty())
                <ul class="divide-y rounded-lg border">
                    @foreach ($documentos as $d)
                        <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm" wire:key="ev-{{ $d->id }}">
                            <a href="{{ $d->url() }}" target="_blank" rel="noopener" class="truncate font-semibold text-indigo-700 hover:underline">📄 {{ $d->nombre }}</a>
                            <span class="flex items-center gap-3 shrink-0 text-xs text-gray-400">
                                {{ $d->created_at->format('d/m/Y') }}
                                @can('eventos.editar')
                                    <button type="button" wire:click="quitarEvidencia({{ $d->id }})" wire:confirm="¿Quitar este soporte?" class="text-rose-600 underline">quitar</button>
                                @endcan
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Foto en grande --}}
            <div x-show="grande" x-cloak x-on:click="grande = null" x-on:keydown.escape.window="grande = null"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
                <img :src="grande" alt="" class="max-h-full max-w-full rounded-lg shadow-xl">
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-gray-400">
            <span>Registrado {{ $e->created_at->locale('es')->diffForHumans() }}{{ $e->creador ? ' por '.$e->creador->name : '' }}.</span>
            @can('eventos.eliminar')
                <button type="button" wire:click="eliminar" wire:confirm="¿Eliminar este evento y todas sus evidencias? No se puede deshacer."
                    class="rounded-md border border-rose-200 px-3 py-1.5 font-semibold text-rose-700 hover:bg-rose-50">Eliminar evento</button>
            @endcan
        </div>
    </div>
</div>
