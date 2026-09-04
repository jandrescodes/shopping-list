@extends('layout')

@section('title', $list->name)

@section('content')
    {{--
        Server-rendered initial state. resources/js/list.js mounts Alpine on
        #list-app, loads the authoritative state through `show`, then takes
        over item rendering, add / edit / mark / delete and the 3–4 s polling.
        Every piece of user content is escaped with {{ }} on the server and
        bound with x-text on the client, never {!! !!} nor x-html.
    --}}
    <div id="list-app" data-slug="{{ $list->slug }}" data-version="{{ $list->version }}" x-data="listApp()">
        <p x-show="error" x-cloak x-text="error" role="alert" class="mb-3 rounded-lg bg-red-50 p-2 text-sm text-red-700"
            style="display:none"></p>

        {{-- Sin conexión: la última lectura sigue visible; las escrituras
             fallan con aviso y no se encolan. --}}
        <p x-show="offline" x-cloak role="status" class="mb-3 rounded-lg bg-amber-50 p-2 text-sm text-amber-800"
            style="display:none">
            Sin conexión. Ves la última versión conocida; los cambios necesitan red.
        </p>

        {{-- Compartir: la hoja nativa, o portapapeles + este aviso, o la URL en
             claro si el navegador no da ninguna de las dos. --}}
        <p x-show="shareNotice" x-cloak x-text="shareNotice" role="status"
            class="mb-3 rounded-lg bg-green-50 p-2 text-sm text-green-800" style="display:none"></p>
        <p x-show="shareUrl" x-cloak role="status" class="mb-3 rounded-lg bg-gray-100 p-2 text-sm" style="display:none">
            Copia este enlace: <span x-text="shareUrl" class="break-all font-mono"></span>
        </p>

        {{-- Deshacer borrado de ítem: ventana de gracia de 5 s tras un delete
             confirmado por la API (sin UI optimista ni escrituras encoladas);
             "deshacer" recrea el ítem con el mismo contenido, no revierte el id. --}}
        <div x-show="pendingUndo" x-cloak role="status" style="display:none"
            class="mb-3 flex items-center justify-between gap-3 rounded-lg bg-blue-50 p-2 text-sm">
            <span x-text="pendingUndo ? ('Ítem eliminado — ' + pendingUndo.name) : 'Ítem eliminado'"></span>
            <button type="button" x-on:click="undoRemove()"
                class="relative shrink-0 font-semibold text-blue-700 underline before:absolute before:left-1/2 before:top-1/2 before:h-11 before:w-11 before:-translate-x-1/2 before:-translate-y-1/2 before:content-[''] focus-visible:outline-2 focus-visible:outline-blue-500">
                Deshacer
            </button>
        </div>

        <header class="mb-4">
            <div class="flex items-start justify-between gap-2">
                <h1 class="min-w-0 flex-1 break-words text-2xl font-bold tracking-tight md:text-3xl" x-show="!editingName"
                    x-text="listName">
                    {{ $list->name }}
                </h1>

                <form class="flex min-w-0 flex-1 gap-2" x-show="editingName" style="display:none"
                    x-on:submit.prevent="renameList($refs.renameInput.value)">
                    <label for="rename-input" class="sr-only">Nuevo nombre de la lista</label>
                    <input id="rename-input" x-ref="renameInput" name="name" type="text" value="{{ $list->name }}"
                        maxlength="60" required
                        class="min-w-0 flex-1 rounded-lg border border-gray-300 px-2 py-1 text-base">
                    <button type="submit"
                        class="shrink-0 rounded-lg bg-blue-600 px-3 py-1 text-sm font-semibold text-white">
                        Guardar
                    </button>
                </form>

                <div class="flex shrink-0 gap-1" x-show="!editingName">
                    <button type="button" x-on:click="editingName = true"
                        class="relative rounded-lg px-2 py-1 text-sm text-gray-500 before:absolute before:left-1/2 before:top-1/2 before:h-11 before:w-11 before:-translate-x-1/2 before:-translate-y-1/2 before:content-[''] hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-500">
                        Renombrar
                    </button>
                    <button type="button" x-on:click="confirmingDelete = true"
                        class="relative rounded-lg px-2 py-1 text-sm text-gray-500 before:absolute before:left-1/2 before:top-1/2 before:h-11 before:w-11 before:-translate-x-1/2 before:-translate-y-1/2 before:content-[''] hover:text-red-600 focus-visible:outline-2 focus-visible:outline-blue-500">
                        Eliminar
                    </button>
                </div>
            </div>

            <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                <button type="button" x-on:click="shareList()"
                    class="relative font-medium text-blue-700 underline before:absolute before:left-1/2 before:top-1/2 before:h-11 before:w-11 before:-translate-x-1/2 before:-translate-y-1/2 before:content-[''] focus-visible:outline-2 focus-visible:outline-blue-500">
                    Compartir enlace
                </button>
                <button type="button" x-show="inMyLists" x-on:click="forgetList()"
                    class="relative text-gray-500 underline before:absolute before:left-1/2 before:top-1/2 before:h-11 before:w-11 before:-translate-x-1/2 before:-translate-y-1/2 before:content-[''] hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-500">
                    Quitar de mis listas
                </button>
                <span x-show="!inMyLists" x-cloak style="display:none" class="text-gray-500">
                    Quitada de tus listas en este dispositivo.
                </span>
            </p>

            <div x-show="confirmingDelete" style="display:none"
                class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm" role="alertdialog"
                aria-label="Confirmar eliminación de la lista">
                <p class="mb-2 font-medium text-red-800">
                    ¿Eliminar esta lista y todos sus ítems? No se puede deshacer.
                </p>
                <div class="flex gap-2">
                    <form x-on:submit.prevent="deleteList()">
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

        <form class="mb-4 space-y-2" x-on:submit.prevent="addItem($event)">
            <label for="added-by" class="sr-only">Tu nombre (quién agrega)</label>
            <input id="added-by" name="added_by" type="text" maxlength="50" autocomplete="off" x-model="author"
                x-on:change="saveAuthor()" placeholder="Tu nombre (opcional)"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-2 focus:outline-blue-500">
            <div class="flex gap-2">
                <label for="new-item" class="sr-only">Nombre del ítem</label>
                <input id="new-item" name="name" type="text" maxlength="100" required autocomplete="off"
                    placeholder="Agregar ítem…"
                    class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-base focus:border-blue-500 focus:outline-2 focus:outline-blue-500">
                <button type="submit" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white">
                    Agregar
                </button>
            </div>
        </form>

        {{-- Pre-hydration list: server-ordered, shown until list.js has loaded
             the authoritative state via `show`. --}}
        <ul id="item-list" class="divide-y divide-gray-200 rounded-lg border border-gray-200" x-show="!ready">
            @forelse ($items as $item)
                <li class="flex items-center gap-3 px-3 py-3 transition-colors duration-300 {{ $item->is_purchased ? 'text-gray-400 line-through' : '' }}"
                    data-item-id="{{ $item->id }}">
                    <span class="min-w-0 flex-1 break-words">{{ $item->name }}</span>
                    @if ($item->quantity)
                        <span
                            class="shrink-0 rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $item->quantity }}</span>
                    @endif
                    @if ($item->added_by)
                        <span class="shrink-0 text-xs text-gray-500">{{ $item->added_by }}</span>
                    @endif
                </li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-gray-500">
                    Esta lista está vacía. Agrega el primer ítem arriba.
                </li>
            @endforelse
        </ul>

        {{-- Client-rendered list. All user content bound with x-text. --}}
        <ul id="client-item-list" class="divide-y divide-gray-200 rounded-lg border border-gray-200" x-show="ready"
            x-cloak style="display:none">
            <template x-for="item in items" :key="item.id">
                <li class="flex items-center gap-3 px-3 py-3 transition-colors duration-300" :data-item-id="item.id"
                    :class="item.is_purchased ? 'text-gray-400 line-through' : ''">
                    <label class="-m-3 flex shrink-0 cursor-pointer items-center justify-center p-3">
                        <input type="checkbox" class="h-5 w-5 rounded border-gray-300" :checked="item.is_purchased"
                            x-on:change="togglePurchased(item)"
                            :aria-label="(item.is_purchased ? 'Desmarcar ' : 'Marcar ') + item.name">
                    </label>
                    <span x-show="!item.editing" x-text="item.name" x-on:click="startEdit(item)"
                        x-on:keydown.enter.prevent="startEdit(item)" x-on:keydown.space.prevent="startEdit(item)"
                        tabindex="0" role="button" :aria-label="'Editar ' + item.name"
                        class="min-w-0 flex-1 cursor-pointer break-words focus:underline focus:outline-none focus:ring-2 focus:ring-blue-500"></span>
                    <input x-show="item.editing" x-model="item.draftName" type="text" maxlength="100"
                        x-on:keydown.enter.prevent="commitEdit(item)" x-on:blur="commitEdit(item)"
                        x-on:keydown.escape="item.editing = false"
                        class="min-w-0 flex-1 rounded border border-gray-300 px-2 py-1 text-base">
                    <span x-show="item.quantity && !item.editing" x-text="item.quantity"
                        class="shrink-0 rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"></span>
                    <span x-show="item.added_by && !item.editing" x-text="item.added_by"
                        class="shrink-0 text-xs text-gray-500"></span>
                    <button type="button" x-on:click="removeItem(item)"
                        class="-m-3 shrink-0 p-3 text-sm text-gray-500 hover:text-red-600"
                        :aria-label="'Eliminar ' + item.name">
                        &times;
                    </button>
                </li>
            </template>
            <li x-show="items.length === 0" class="px-3 py-6 text-center text-sm text-gray-500">
                Esta lista está vacía. Agrega el primer ítem arriba.
            </li>
        </ul>

        @if ($items->contains('is_purchased', true))
            <div class="mt-4" x-show="hasPurchased">
                <button type="button" x-on:click="confirmingPurge = true"
                    class="relative text-sm text-gray-500 underline before:absolute before:left-1/2 before:top-1/2 before:h-11 before:w-11 before:-translate-x-1/2 before:-translate-y-1/2 before:content-[''] hover:text-red-600 focus-visible:outline-2 focus-visible:outline-blue-500">
                    Limpiar comprados
                </button>

                <div x-show="confirmingPurge" style="display:none"
                    class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm" role="alertdialog"
                    aria-label="Confirmar limpieza de comprados">
                    <p class="mb-2 font-medium text-gray-800">
                        ¿Quitar todos los ítems ya comprados de la lista?
                    </p>
                    <div class="flex gap-2">
                        <form x-on:submit.prevent="purgePurchased()">
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
