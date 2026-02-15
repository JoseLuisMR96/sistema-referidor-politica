<div>
    @if (!$referrer)
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
            <div class="font-semibold">Código inválido</div>
            <div class="text-sm mt-1">
                El link de referido no es válido. Solicita un enlace correcto.
            </div>
        </div>

    @elseif (!$referrer->is_active)
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
            <div class="font-semibold">Código desactivado</div>
            <div class="text-sm mt-1">
                Este enlace o código se encuentra desactivado actualmente. Por favor, comunícate con
                <span class="font-semibold">MetaTank</span> para solicitar su reactivación.
            </div>
        </div>

    @else
        <div class="w-full max-w-2xl bg-white rounded-2xl shadow p-6 sm:p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Registro</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Completa tus datos. Tu información quedará registrada correctamente.
                    </p>
                </div>

                <div class="text-right">
                    <div class="text-xs text-gray-500">Código</div>
                    <div class="font-mono text-sm">{{ $this->ref ?? '—' }}</div>
                </div>
            </div>

            <div class="mt-6 rounded-xl border bg-gray-50 p-4">
                <div class="text-sm text-gray-600">
                    Referido por: <span class="font-semibold text-gray-900">{{ $referrer->name }}</span>
                </div>
            </div>

            @if ($enviado)
                <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                    <div class="font-semibold">Registro enviado</div>
                    <div class="text-sm mt-1">¡Listo! Tus datos fueron registrados correctamente.</div>
                </div>
            @endif

            <form wire:submit.prevent="submit" class="mt-6 space-y-5">
                <div>
                    <label class="block text-sm font-semibold">Nombre completo</label>
                    <input type="text" wire:model.defer="full_name"
                        class="mt-1 w-full rounded-xl border-gray-300" placeholder="Ej: Juan Pérez">
                    @error('full_name')
                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold">Tipo doc.</label>
                        <select wire:model.defer="document_type" class="mt-1 w-full rounded-xl border-gray-300">
                            <option value="CC">CC</option>
                            <option value="TI">TI</option>
                            <option value="CE">CE</option>
                            <option value="PA">PA</option>
                            <option value="PEP">PEP</option>
                            <option value="NIT">NIT</option>
                        </select>
                        @error('document_type')
                            <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold">Número de documento</label>
                        <input type="text" wire:model.defer="document_number"
                            class="mt-1 w-full rounded-xl border-gray-300" placeholder="Ej: 1234567890">
                        @error('document_number')
                            <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold">Edad</label>
                        <input type="number" wire:model.defer="age" class="mt-1 w-full rounded-xl border-gray-300"
                            min="18" max="120">
                        @error('age')
                            <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Género</label>
                        <select wire:model.defer="gender" class="mt-1 w-full rounded-xl border-gray-300">
                            <option value="">Seleccione…</option>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="O">Otro</option>
                            <option value="NR">Prefiero no responder</option>
                        </select>
                        @error('gender')
                            <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Teléfono</label>
                        <input type="text" wire:model.defer="phone" class="mt-1 w-full rounded-xl border-gray-300"
                            placeholder="Ej: 3001234567">
                        @error('phone')
                            <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold">Departamento de residencia</label>
                        <select wire:model.change="residence_department_id" class="mt-1 w-full rounded-xl border-gray-300">
                            <option value="">Seleccione…</option>
                            @foreach ($departamentos as $d)
                                <option value="{{ $d['id'] }}">{{ $d['nombre'] }}</option>
                            @endforeach
                        </select>
                        @error('residence_department_id')
                            <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Municipio de residencia</label>
                        <select
                            wire:model.change="residence_municipality_id"
                            wire:key="residence_muni_{{ $residence_department_id }}"
                            class="mt-1 w-full rounded-xl border-gray-300"
                            @disabled(!$residence_department_id)
                        >
                            <option value="">
                                {{ $residence_department_id ? 'Seleccione…' : 'Primero elija un departamento' }}
                            </option>

                            @foreach ($this->municipiosResidencia as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                        @error('residence_municipality_id')
                            <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Departamento donde vota</label>
                        <select wire:model.change="voting_department_id" class="mt-1 w-full rounded-xl border-gray-300">
                            <option value="">Seleccione…</option>
                            @foreach ($departamentos as $d)
                                <option value="{{ $d['id'] }}">{{ $d['nombre'] }}</option>
                            @endforeach
                        </select>
                        @error('voting_department_id')
                            <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Municipio donde vota</label>
                        <select
                            wire:model.change="voting_municipality_id"
                            wire:key="voting_muni_{{ $voting_department_id }}"
                            class="mt-1 w-full rounded-xl border-gray-300"
                            @disabled(!$voting_department_id)
                        >
                            <option value="">
                                {{ $voting_department_id ? 'Seleccione…' : 'Primero elija un departamento' }}
                            </option>

                            @foreach ($this->municipiosVoto as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                        @error('voting_municipality_id')
                            <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                @if ($debug)
                    <div class="mt-2 text-xs text-blue-600">{{ $debug }}</div>
                @endif

                @error('ref')
                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-red-700 text-sm">
                        {{ $message }}
                    </div>
                @enderror

                <button type="submit"
                    class="w-full sm:w-auto rounded-xl bg-gray-900 text-white px-5 py-2.5 font-semibold hover:bg-black">
                    Enviar registro
                </button>
            </form>

            <div class="mt-8 pt-6 border-t text-xs text-gray-500">
                {{ config('app.name') }} • Formulario de registro público
            </div>
        </div>
    @endif
</div>
