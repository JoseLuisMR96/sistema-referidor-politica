    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle • Persona importada</h2>
                <p class="text-xs text-gray-500 mt-1">ID #{{ $row->id }} • Batch: {{ $row->batch_id ?? '—' }}</p>
            </div>

            <a href="{{ route('imported-people.index') }}"
               class="inline-flex items-center justify-center rounded-md border px-4 py-2 text-sm font-semibold hover:bg-gray-50">
                ← Volver
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">

                    <div class="rounded-lg border p-4 sm:col-span-2">
                        <div class="text-xs font-semibold text-gray-500">Nombre completo</div>
                        <div class="mt-1 text-base font-semibold text-slate-800">{{ $row->full_name }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs font-semibold text-gray-500">Cédula</div>
                        <div class="mt-1 inline-flex rounded-md bg-indigo-50 px-2.5 py-1 text-sm font-semibold text-indigo-700">
                            {{ $row->document_number }}
                        </div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs font-semibold text-gray-500">Teléfono</div>
                        <div class="mt-1">
                            @if($row->phone)
                                <span class="inline-flex rounded-md bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                                    {{ $row->phone }}
                                </span>
                            @else
                                <span class="inline-flex rounded-md bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-800">
                                    Sin teléfono
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-lg border p-4 sm:col-span-2">
                        <div class="text-xs font-semibold text-gray-500">Puesto de votación</div>
                        <div class="mt-1 text-slate-800">{{ $row->voting_place ?? '—' }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs font-semibold text-gray-500">Municipio de votación</div>
                        <div class="mt-1 text-slate-800">{{ $row->voting_municipality ?? '—' }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs font-semibold text-gray-500">Creado por</div>
                        <div class="mt-1 text-slate-800">{{ $row->creator?->name ?? '—' }}</div>
                    </div>

                </div>
            </div>

        </div>
    </div>
    