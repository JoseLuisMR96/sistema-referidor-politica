{{--
    Cabecera de los formularios públicos con la identidad del candidato:
    foto, logo del partido, nombre, cargo y slogan, en sus colores.
--}}
@props(['tenant' => null])

@if ($tenant && ($tenant->fotoUrl() || $tenant->logoUrl() || $tenant->slogan))
    <div class="-mx-6 -mt-6 sm:-mx-8 sm:-mt-8 mb-6 overflow-hidden rounded-t-2xl">
        <div class="bg-indigo-600 px-6 py-5 sm:px-8 text-white">
            <div class="flex items-center gap-4">
                @if ($tenant->fotoUrl())
                    <img src="{{ $tenant->fotoUrl() }}" alt="Foto de {{ $tenant->nombre }}"
                        class="h-20 w-20 shrink-0 rounded-full object-cover ring-4 ring-white/80">
                @endif

                <div class="min-w-0 flex-1">
                    <div class="text-2xl font-extrabold leading-tight">{{ $tenant->nombre }}</div>
                    @if ($tenant->cargo)
                        <div class="text-sm text-white/85">{{ $tenant->circunscripcionTexto() }}</div>
                    @endif
                    @if ($tenant->partido)
                        <div class="text-xs font-semibold uppercase tracking-wider text-white/80 mt-1">{{ $tenant->partido }}</div>
                    @endif
                </div>

                @if ($tenant->logoUrl())
                    <img src="{{ $tenant->logoUrl() }}" alt="Logo {{ $tenant->partido ?: $tenant->nombre }}"
                        class="hidden sm:block h-16 w-16 shrink-0 rounded-xl bg-white object-contain p-1">
                @endif
            </div>
        </div>

        @if ($tenant->slogan)
            <div class="bg-secundario px-6 py-2 sm:px-8 text-center text-sm font-bold uppercase tracking-wide text-secundario-texto">
                {{ $tenant->slogan }}
            </div>
        @endif
    </div>
@endif
