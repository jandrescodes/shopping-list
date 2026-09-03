@extends('layout')

@section('title', $list->name)

@section('content')
    {{--
        Server-rendered initial state. resources/js/list.js (T28–T31) mounts
        Alpine on #list-app, takes over item rendering, add / edit / mark /
        delete and the 3–4 s polling. All user text is escaped with {{ }} /
        x-text, never {!! !!} nor x-html (RF-32).
    --}}
    <div id="list-app" data-slug="{{ $list->slug }}" data-version="{{ $list->version }}" x-data="{ confirmingDelete: false, confirmingPurge: false, editingName: false }">
        <header class="mb-4">
            <div class="flex items-start justify-between gap-2">
                <h1 class="min-w-0 flex-1 break-words text-xl font-bold" x-show="!editingName">
                    {{ $list->name }}
                </h1>

                <form action="/api/lists/{{ $list->slug }}" method="POST" class="flex min-w-0 flex-1 gap-2"
                    x-show="editingName" style="display:none" x-on:submit.prevent="$refs.rename.submit()" x-ref="rename">
                    @method('PATCH')
                    <label for="rename-input" class="sr-only">Nuevo nombre de la lista</label>
                    <input id="rename-input" name="name" type="text" value="{{ $list->name }}" maxlength="60"
                        required class="min-w-0 flex-1 rounded-lg border border-gray-300 px-2 py-1 text-base">
                    <button type="submit"
                        class="shrink-0 rounded-lg bg-blue-600 px-3 py-1 text-sm font-semibold text-white">
                        Guardar
                    </button>
                </form>

                <div class="flex shrink-0 gap-1" x-show="!editingName">
                    <button type="button" x-on:click="editingName = true"
                        class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:text-blue-700">
                        Renombrar
                    </button>
                    <button type="button" x-on:click="confirmingDelete = true"
                        class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:text-red-600">
                        Eliminar
                    </button>
                </div>
            </div>

            <div x-show="confirmingDelete" style="display:none"
                class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm" role="alertdialog"
                aria-label="Confirmar eliminación de la lista">
                <p class="mb-2 font-medium text-red-800">
                    ¿Eliminar esta lista y todos sus ítems? No se puede deshacer.
                </p>
                <div class="flex gap-2">
                    <form action="/api/lists/{{ $list->slug }}" method="POST">
                        @method('DELETE')
                        <button type="submit" class="rounded-lg bg-red-600 px-3 py-1 font-semibold text-white">
                            Sí, eliminar
                        </button>
                    </form>
                    <button type="button" x-on:click="confirmingDelete = false" class="rounded-lg px-3 py-1 text-gray-600">
                        Cancelar
                    </button>
                </div>
            </div>
        </header>

        <form action="/api/lists/{{ $list->slug }}/items" method="POST" class="mb-4 flex gap-2">
            <label for="new-item" class="sr-only">Nombre del ítem</label>
            <input id="new-item" name="name" type="text" maxlength="100" required autocomplete="off"
                placeholder="Agregar ítem…"
                class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-base focus:border-blue-500 focus:outline-none">
            <button type="submit" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white">
                Agregar
            </button>
        </form>

        <ul id="item-list" class="divide-y divide-gray-200 rounded-lg border border-gray-200">
            @forelse ($items as $item)
                <li class="flex items-center gap-3 px-3 py-3 {{ $item->is_purchased ? 'text-gray-400 line-through' : '' }}"
                    data-item-id="{{ $item->id }}">
                    <span class="min-w-0 flex-1 break-words">{{ $item->name }}</span>
                    @if ($item->quantity)
                        <span class="shrink-0 text-sm text-gray-500">{{ $item->quantity }}</span>
                    @endif
                    @if ($item->added_by)
                        <span class="shrink-0 text-xs text-gray-400">{{ $item->added_by }}</span>
                    @endif
                </li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-gray-500">
                    Esta lista está vacía. Agrega el primer ítem arriba.
                </li>
            @endforelse
        </ul>

        @if ($items->contains('is_purchased', true))
            <div class="mt-4">
                <button type="button" x-on:click="confirmingPurge = true"
                    class="text-sm text-gray-500 underline hover:text-red-600">
                    Limpiar comprados
                </button>

                <div x-show="confirmingPurge" style="display:none"
                    class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm" role="alertdialog"
                    aria-label="Confirmar limpieza de comprados">
                    <p class="mb-2 font-medium text-gray-800">
                        ¿Quitar todos los ítems ya comprados de la lista?
                    </p>
                    <div class="flex gap-2">
                        <form action="/api/lists/{{ $list->slug }}/items/purge-purchased" method="POST">
                            <button type="submit" class="rounded-lg bg-gray-800 px-3 py-1 font-semibold text-white">
                                Sí, limpiar
                            </button>
                        </form>
                        <button type="button" x-on:click="confirmingPurge = false"
                            class="rounded-lg px-3 py-1 text-gray-600">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
