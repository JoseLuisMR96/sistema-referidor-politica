<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle del registro</h2>
            <div class="text-sm text-gray-500">
                ID: <span class="font-mono">{{ $publicRegistration->id }}</span>
                <span class="mx-2">•</span>
                {{ optional($publicRegistration->created_at)->format('Y-m-d H:i') }}
            </div>
        </div>

        <a href="{{ route('registrations.index') }}"
            class="inline-flex items-center justify-center rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-sm
                   hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
            ← Volver
        </a>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- Card principal --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">Nombre</div>
                    <div class="text-xl font-bold text-gray-900 truncate">
                        {{ $publicRegistration->full_name }}
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        {{-- Badge estado --}}
                        {{-- @php
                            $status = strtolower($publicRegistration->status ?? '');
                            $statusClass = match ($status) {
                                'validado' => 'bg-emerald-100 text-emerald-800',
                                'contactado' => 'bg-blue-100 text-blue-800',
                                'rechazado' => 'bg-rose-100 text-rose-800',
                                default => 'bg-amber-100 text-amber-800', // pendiente u otro
                            };
                        @endphp --}}

                        {{-- <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                            {{ ucfirst($publicRegistration->status) }}
                        </span> --}}

                        @if ($publicRegistration->referrer)
                            <span
                                class="inline-flex items-center rounded-full bg-gray-100 text-gray-700 px-3 py-1 text-xs font-semibold">
                                Referidor: {{ $publicRegistration->referrer->name }}
                            </span>
                        @endif

                        @if ($publicRegistration->ref_code_used)
                            <span
                                class="inline-flex items-center rounded-full bg-gray-100 text-gray-700 px-3 py-1 text-xs font-semibold font-mono">
                                ref={{ $publicRegistration->ref_code_used }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Acciones rápidas --}}
                <div class="flex flex-col sm:flex-row gap-2">
                    @if ($publicRegistration->phone)
                        <a href="tel:{{ $publicRegistration->phone }}"
                            class="inline-flex items-center justify-center rounded-md border px-4 py-2 text-sm font-semibold hover:bg-gray-50">
                            📞 Llamar
                        </a>

                        <a target="_blank"
                            href="https://wa.me/{{ preg_replace('/\D+/', '', $publicRegistration->phone) }}"
                            class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                                   hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">
                            💬 WhatsApp
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Datos en grid --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="text-lg font-semibold text-gray-900">Información del ciudadano</div>
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-lg border p-4">
                    <div class="text-xs text-gray-500">Documento</div>
                    <div class="font-semibold text-gray-900">
                        {{ $publicRegistration->document_type }} {{ $publicRegistration->document_number }}
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <div class="text-xs text-gray-500">Teléfono</div>
                    <div class="font-semibold text-gray-900">
                        {{ $publicRegistration->phone ?? '—' }}
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <div class="text-xs text-gray-500">Edad</div>
                    <div class="font-semibold text-gray-900">
                        {{ $publicRegistration->age ?? '—' }}
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <div class="text-xs text-gray-500">Género</div>
                    <div class="font-semibold text-gray-900">
                        {{ $publicRegistration->gender ?? '—' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Ubicación y votación --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="text-lg font-semibold text-gray-900">Ubicación</div>

            @php
                $res =
                    $publicRegistration->residenceMunicipality?->nombre ??
                    ($publicRegistration->residence_municipality ?? null);

                $vot =
                    $publicRegistration->votingMunicipality?->nombre ??
                    ($publicRegistration->voting_municipality ?? null);
            @endphp

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-lg border p-4">
                    <div class="text-xs text-gray-500">Municipio donde reside</div>
                    <div class="font-semibold text-gray-900">
                        {{ $res ?: '—' }}
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <div class="text-xs text-gray-500">Municipio donde vota</div>
                    <div class="font-semibold text-gray-900">
                        {{ $vot ?: '—' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Meta del registro --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="text-lg font-semibold text-gray-900">Meta del registro</div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div class="rounded-lg border p-4">
                    <div class="text-xs text-gray-500">Referidor</div>
                    <div class="font-semibold text-gray-900">
                        {{ $publicRegistration->referrer?->name ?? '—' }}
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <div class="text-xs text-gray-500">Código usado</div>
                    <div class="font-semibold text-gray-900 font-mono">
                        {{ $publicRegistration->ref_code_used ?? '—' }}
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <div class="text-xs text-gray-500">Fecha de registro</div>
                    <div class="font-semibold text-gray-900">
                        {{ optional($publicRegistration->created_at)->format('Y-m-d H:i') }}
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
