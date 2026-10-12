{{--
    Aviso para pantallas que CREAN datos (trait RequiereTenant): en
    "Todos los tenants" no se sabe a qué candidato pertenece lo que se crea,
    así que se pide elegir uno aquí mismo, sin sacar al usuario de la página.
--}}
@props(['accion' => 'crear datos'])

@php($ctxTenant = app(\App\Support\TenantContext::class))

@if ($ctxTenant->esTodos())
    <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4">
        <div class="font-semibold text-amber-900">Elige un candidato para {{ $accion }}</div>
        <div class="text-sm text-amber-800 mt-1">
            Estás en "Todos los tenants", que es solo para consultar. Lo que se cree aquí debe pertenecer a un candidato.
        </div>

        @if (auth()->user()?->esGlobal())
            <form method="POST" action="{{ route('tenant.seleccionar') }}" class="mt-3 flex flex-wrap items-center gap-2">
                @csrf
                <select name="tenant_id" required class="rounded-lg border-amber-300 text-sm font-semibold">
                    <option value="">— Elige el candidato —</option>
                    @foreach (\App\Models\Tenant::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']) as $t)
                        <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                    Usar este candidato
                </button>
            </form>
        @endif
    </div>
@elseif ($ctxTenant->tenant())
    <div class="mb-4 text-sm text-slate-500">
        Candidato: <span class="font-semibold text-slate-800">{{ $ctxTenant->tenant()->nombre }}</span>
    </div>
@endif
