<div class="space-y-6">
    <div class="rounded-xl border bg-white p-4 space-y-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="text-lg font-semibold">WhatsApp Outbox</div>
                <div class="text-sm text-gray-500">Importa un XLSX y gestiona el estado de envíos (PENDING / SENT / FAILED).</div>
            </div>
        </div>

        <form wire:submit.prevent="import" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Archivo XLSX</label>
                    <input type="file" wire:model="xlsx" class="mt-1 block w-full" accept=".xlsx,.xls">
                    @error('xlsx') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    <div class="text-xs text-gray-500 mt-1">
                        Encabezados sugeridos: <b>phone</b>, <b>message</b> (opcional <b>name</b>).
                    </div>
                </div>

                <div class="flex items-end gap-3">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="useRowMessage">
                        Usar mensaje por fila
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium">Mensaje por defecto (si no usas mensaje por fila)</label>
                    <textarea wire:model="defaultMessage" rows="3" class="mt-1 block w-full rounded-lg border p-2"
                        placeholder="Hola, {name} ... (si tú haces plantillas luego, aquí metemos variables)"></textarea>
                    @error('defaultMessage') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="flex items-end justify-end">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove>Importar a cola</span>
                        <span wire:loading>Importando…</span>
                    </button>
                </div>
            </div>
        </form>

        @if($importSummary['inserted'] || $importSummary['skipped'] || count($importSummary['errors']))
            <div class="rounded-lg bg-slate-50 p-3 text-sm">
                <div class="flex flex-wrap gap-4">
                    <div><b>Insertados:</b> {{ $importSummary['inserted'] }}</div>
                    <div><b>Saltados:</b> {{ $importSummary['skipped'] }}</div>
                </div>

                @if(count($importSummary['errors']))
                    <div class="mt-2 text-red-700">
                        <b>Errores:</b>
                        <ul class="list-disc ml-5">
                            @foreach($importSummary['errors'] as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="rounded-xl border bg-white p-4 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="flex items-center gap-2">
                <input type="text" wire:model.live="search"
                    class="w-full md:w-80 rounded-lg border p-2 text-sm"
                    placeholder="Buscar por teléfono o texto...">
                <select wire:model.live="statusFilter" class="rounded-lg border p-2 text-sm">
                    <option value="ALL">Todos</option>
                    <option value="PENDING">PENDING</option>
                    <option value="RESERVED">RESERVED</option>
                    <option value="SENT">SENT</option>
                    <option value="FAILED">FAILED</option>
                    <option value="CANCELLED">CANCELLED</option>
                </select>
                <select wire:model.live="perPage" class="rounded-lg border p-2 text-sm">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>

            <div class="text-sm text-gray-500">
                Total visibles: <b>{{ $jobs->total() }}</b>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-600">
                    <tr class="border-b">
                        <th class="py-2 pr-3">ID</th>
                        <th class="py-2 pr-3">Teléfono</th>
                        <th class="py-2 pr-3">Mensaje</th>
                        <th class="py-2 pr-3">Estado</th>
                        <th class="py-2 pr-3">Intentos</th>
                        <th class="py-2 pr-3">Enviado</th>
                        <th class="py-2 pr-3">Error</th>
                        <th class="py-2 pr-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                        <tr class="border-b align-top">
                            <td class="py-2 pr-3">{{ $job->id }}</td>
                            <td class="py-2 pr-3 font-medium">{{ $job->phone }}</td>
                            <td class="py-2 pr-3 max-w-xl">
                                <div class="line-clamp-3 text-gray-800">{{ $job->message }}</div>
                            </td>
                            <td class="py-2 pr-3">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold">
                                    {{ $job->status }}
                                </span>
                            </td>
                            <td class="py-2 pr-3">{{ $job->attempts }}</td>
                            <td class="py-2 pr-3">
                                {{ $job->sent_at ? $job->sent_at->format('Y-m-d H:i') : '—' }}
                            </td>
                            <td class="py-2 pr-3 max-w-sm text-red-700">
                                <div class="line-clamp-2">{{ $job->last_error }}</div>
                            </td>
                            <td class="py-2 pr-3">
                                @if($job->status === 'PENDING')
                                    <button wire:click="cancel({{ $job->id }})"
                                        class="rounded-lg border px-3 py-1 text-xs hover:bg-slate-50">
                                        Cancelar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-gray-500">No hay registros para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $jobs->links() }}
        </div>
    </div>
</div>