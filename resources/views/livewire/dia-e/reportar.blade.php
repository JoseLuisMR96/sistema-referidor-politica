<div class="w-full py-2">
    <div class="mx-auto max-w-md space-y-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200">
            <div class="text-xs uppercase tracking-wider text-slate-400">Testigo electoral</div>
            <div class="text-xl font-extrabold text-slate-900">{{ $t->nombre }}</div>
            <div class="text-sm text-slate-600">{{ $t->eleccion->nombre }}</div>
            <div class="mt-2 text-sm"><b>Puesto:</b> {{ $t->puesto?->nombre }} · {{ $t->puesto?->municipio?->nombre }}</div>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach ($t->listaMesas() as $m)
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ in_array($m, $reportadas, true) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                        Mesa {{ $m }}{{ in_array($m, $reportadas, true) ? ' ✓' : '' }}
                    </span>
                @endforeach
            </div>
        </div>

        @if (! $t->eleccion->abierta)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">La campaña cerró los reportes de esta elección.</div>
        @else
            @if ($enviado)
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    ✅ Mesa {{ $mesa }} reportada. Puedes elegir otra mesa, o la misma para corregir.
                </div>
            @endif

            <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200 space-y-4">
                <label class="block text-sm font-semibold">Mesa
                    <select wire:model.live="mesa" class="mt-1 w-full rounded-lg border-gray-300 text-base">
                        <option value="">— Elige la mesa —</option>
                        @foreach ($t->listaMesas() as $m)
                            <option value="{{ $m }}">Mesa {{ $m }}{{ in_array($m, $reportadas, true) ? ' (ya reportada: corregir)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('mesa') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                </label>

                @if ($mesa)
                    <div class="text-xs text-slate-500">Copia los votos tal como están en el formulario E-14 de la mesa.</div>
                    <div class="divide-y rounded-lg border">
                        @foreach ($opciones as $o)
                            <label class="flex items-center justify-between gap-3 px-3 py-2" wire:key="o-{{ $o->id }}">
                                <span class="text-sm">
                                    <span class="font-semibold {{ $o->propia ? 'text-indigo-700' : 'text-slate-900' }}">{{ $o->nombre }}</span>
                                    @if ($o->partido)<span class="block text-xs text-slate-500">{{ $o->partido }}</span>@endif
                                </span>
                                <input type="number" min="0" max="1000" inputmode="numeric" wire:model="votos.{{ $o->id }}"
                                    class="w-24 rounded-lg border-gray-300 text-right text-base tabular-nums" placeholder="0">
                            </label>
                        @endforeach
                        @foreach (['blanco' => 'Voto en blanco', 'nulos' => 'Votos nulos', 'no_marcados' => 'Tarjetas no marcadas'] as $campo => $texto)
                            <label class="flex items-center justify-between gap-3 bg-slate-50 px-3 py-2">
                                <span class="text-sm text-slate-700">{{ $texto }}</span>
                                <input type="number" min="0" max="1000" inputmode="numeric" wire:model="{{ $campo }}"
                                    class="w-24 rounded-lg border-gray-300 text-right text-base tabular-nums" placeholder="0">
                            </label>
                        @endforeach
                    </div>
                    @foreach ($errors->get('votos.*') as $m)
                        <div class="text-sm text-red-600">{{ $m[0] }}</div>
                    @endforeach

                    <label class="block text-sm font-semibold">Foto del E-14
                        <input type="file" accept="image/*" capture="environment" wire:model="foto" class="mt-1 block w-full text-sm">
                        <span wire:loading wire:target="foto" class="text-xs text-slate-500">Subiendo foto…</span>
                        @error('foto') <span class="block text-sm font-normal text-red-600 mt-1">{{ $message }}</span> @enderror
                    </label>

                    <label class="block text-sm font-semibold">Observaciones (opcional)
                        <textarea wire:model="observaciones" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="Reclamaciones, tachones, recuento…"></textarea>
                    </label>

                    <button type="button" wire:click="enviar" wire:loading.attr="disabled" wire:target="enviar,foto"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-base font-bold text-white hover:bg-indigo-700 disabled:opacity-50">
                        Enviar mesa {{ $mesa }}
                    </button>
                @endif
            </div>
        @endif

        <div class="text-center text-xs text-slate-400">Este enlace es personal: no lo compartas.</div>
    </div>
</div>
