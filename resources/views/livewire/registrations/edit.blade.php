<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar registro</h2>
            <div class="text-sm text-gray-500">
                ID: <span class="font-mono">{{ $publicRegistration->id }}</span>
                <span class="mx-2">•</span>
                {{ optional($publicRegistration->created_at)->format('Y-m-d H:i') }}
            </div>
        </div>

        <div class="flex gap-2">
            <a
                href="{{ route('registrations.show', $publicRegistration->id) }}"
                class="inline-flex items-center justify-center rounded-md border px-4 py-2 text-sm font-semibold hover:bg-gray-50"
            >
                👁️ Ver
            </a>

            <a
                href="{{ route('registrations.index') }}"
                class="inline-flex items-center justify-center rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-sm
                       hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
            >
                ← Volver
            </a>
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4">
                {{ session('success') }}
            </div>
        @endif

        <form wire:submit.prevent="update" class="space-y-6">

            {{-- Card principal --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="text-xs text-gray-500">Nombre</div>

                <input
                    type="text"
                    wire:model.defer="full_name"
                    class="mt-2 w-full rounded-md border-gray-300"
                    placeholder="Nombre completo"
                >
                @error('full_name') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror

                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    @if($publicRegistration->ref_code_used)
                        <span class="inline-flex items-center rounded-full bg-gray-100 text-gray-700 px-3 py-1 text-xs font-semibold font-mono">
                            ref={{ $publicRegistration->ref_code_used }}
                        </span>
                    @endif

                    <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-800 px-3 py-1 text-xs font-semibold">
                        Modo edición (solo Admin)
                    </span>
                </div>
            </div>

            {{-- Información del ciudadano --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="text-lg font-semibold text-gray-900">Información del ciudadano</div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-gray-500">Tipo de documento</div>
                        <input type="text" wire:model.defer="document_type" class="mt-2 w-full rounded-md border-gray-300" placeholder="CC, TI, CE...">
                        @error('document_type') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-gray-500">Número de documento</div>
                        <input type="text" wire:model.defer="document_number" class="mt-2 w-full rounded-md border-gray-300">
                        @error('document_number') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-gray-500">Teléfono</div>
                        <input type="text" wire:model.defer="phone" class="mt-2 w-full rounded-md border-gray-300" placeholder="3xx...">
                        @error('phone') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-gray-500">Edad</div>
                        <input type="number" wire:model.defer="age" class="mt-2 w-full rounded-md border-gray-300" min="0" max="120">
                        @error('age') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="rounded-lg border p-4 sm:col-span-2">
                        <div class="text-xs text-gray-500">Género</div>
                        <input type="text" wire:model.defer="gender" class="mt-2 w-full rounded-md border-gray-300" placeholder="F, M, Otro...">
                        @error('gender') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- Ubicación --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="text-lg font-semibold text-gray-900">Ubicación</div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-gray-500">Municipio donde reside</div>
                        <input type="text" wire:model.defer="residence_municipality" class="mt-2 w-full rounded-md border-gray-300">
                        @error('residence_municipality') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-gray-500">Municipio donde vota</div>
                        <input type="text" wire:model.defer="voting_municipality" class="mt-2 w-full rounded-md border-gray-300">
                        @error('voting_municipality') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- Meta del registro --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="text-lg font-semibold text-gray-900">Meta del registro</div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div class="rounded-lg border p-4 sm:col-span-2">
                        <div class="text-xs text-gray-500">Referidor</div>
                        <select wire:model.defer="referrer_id" class="mt-2 w-full rounded-md border-gray-300">
                            <option value="">— Sin referidor —</option>
                            @foreach($referrers as $r)
                                <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->code }})</option>
                            @endforeach
                        </select>
                        @error('referrer_id') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-gray-500">Código usado</div>
                        <div class="mt-2 font-semibold text-gray-900 font-mono">
                            {{ $publicRegistration->ref_code_used ?? '—' }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Botones --}}
            <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
                <a
                    href="{{ route('registrations.show', $publicRegistration->id) }}"
                    class="inline-flex items-center justify-center rounded-md border px-4 py-2 text-sm font-semibold hover:bg-gray-50"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-md bg-slate-800 px-6 py-2 text-sm font-semibold text-white shadow-sm
                           hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                >
                    💾 Guardar cambios
                </button>
            </div>

        </form>
    </div>
</div>
