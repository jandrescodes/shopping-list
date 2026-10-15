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
        <p x-show="showOfflineNotice" x-cloak role="status" class="mb-3 rounded-lg bg-amber-50 p-2 text-sm text-amber-800"
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
            <div class="flex flex-wrap items-start justify-between gap-x-2 gap-y-3">
                <h1 class="min-w-0 flex-1 wrap-break-word text-2xl font-bold tracking-tight md:text-3xl"
                    x-show="!editingName" x-text="listName">
                    {{ $list->name }}
                </h1>

                <form class="flex min-w-0 gap-2 sm:flex-1" x-show="editingName" style="display:none"
                    x-on:submit.prevent="renameList($refs.renameInput.value).then(() => { if (!editingName) $nextTick(() => $refs.menuButton.focus()) })">
                    <label for="rename-input" class="sr-only">Nuevo nombre de la lista</label>
                    <input id="rename-input" x-ref="renameInput" name="name" type="text" value="{{ $list->name }}"
                        :value="listName"
                        x-on:keydown.escape="editingName = false; $nextTick(() => $refs.menuButton.focus())" maxlength="60"
                        required class="min-w-0 flex-1 rounded-lg border border-gray-300 px-2 py-1 text-base">
                    <button type="submit"
                        class="shrink-0 rounded-lg bg-blue-600 min-h-11 px-3 text-sm font-semibold text-white">
                        Guardar
                    </button>
                </form>

                {{-- Opciones de la lista: un solo botón en el encabezado. Los
                     ítems son botones normales (patrón disclosure, sin
                     role="menu", que exigiría navegación con flechas). --}}
                <div class="relative shrink-0" x-show="!editingName && !editingCurrency"
                    x-on:click.outside="menuOpen = false" x-on:keydown.escape="menuOpen = false; $refs.menuButton.focus()">
                    <button id="list-menu-button" x-ref="menuButton" type="button" x-on:click="menuOpen = !menuOpen"
                        :aria-expanded="menuOpen" aria-controls="list-menu" aria-label="Opciones de la lista"
                        class="inline-flex size-11 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-blue-500">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" class="size-5">
                            <circle cx="5" cy="12" r="2" />
                            <circle cx="12" cy="12" r="2" />
                            <circle cx="19" cy="12" r="2" />
                        </svg>
                    </button>
                    <div id="list-menu" x-show="menuOpen" x-cloak style="display:none"
                        class="absolute right-0 z-10 mt-1 w-56 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                        <button type="button"
                            x-on:click="menuOpen = false; editingName = true; $nextTick(() => $refs.renameInput.focus())"
                            class="flex min-h-11 w-full items-center px-4 text-left text-sm text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                            Renombrar
                        </button>
                        <button type="button"
                            x-on:click="menuOpen = false; editingCurrency = true; $nextTick(() => $refs.currencyInput.focus())"
                            :aria-label="'Cambiar la moneda de la lista (actual: ' + currency + ')'"
                            class="flex min-h-11 w-full items-center justify-between gap-2 px-4 text-left text-sm text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                            <span>Moneda</span>
                            <span id="currency-chip" class="font-semibold" x-text="currency">{{ $list->currency }}</span>
                        </button>
                        <button type="button"
                            x-on:click="menuOpen = false; confirmingDelete = true; $nextTick(() => $refs.cancelDelete.focus())"
                            class="flex min-h-11 w-full items-center px-4 text-left text-sm text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                            Eliminar lista
                        </button>
                    </div>
                </div>

                {{-- Currency: same inline-edit pattern as the list name. The
                     buttons above stay hidden while it is open, so name and
                     currency are never edited at the same time. --}}
                <form class="flex min-w-0 basis-full flex-wrap items-center gap-2 sm:basis-auto" x-show="editingCurrency"
                    style="display:none"
                    x-on:submit.prevent="renameCurrency($refs.currencyInput.value).then(() => { if (!editingCurrency) $nextTick(() => $refs.menuButton.focus()) })">
                    <label for="currency-input" class="sr-only">Moneda de la lista</label>
                    <input id="currency-input" x-ref="currencyInput" name="currency" type="text" maxlength="5"
                        :value="currency" required autocomplete="off"
                        class="w-24 min-w-0 rounded-lg border border-gray-300 px-2 py-1 text-base focus:border-blue-500 focus:outline-2 focus:outline-blue-500">
                    <button type="submit"
                        class="shrink-0 rounded-lg bg-blue-600 min-h-11 px-3 text-sm font-semibold text-white">
                        Guardar
                    </button>
                    <button type="button" x-on:click="editingCurrency = false; $nextTick(() => $refs.menuButton.focus())"
                        class="min-h-11 shrink-0 rounded-lg px-3 text-sm text-gray-500 hover:text-red-600">
                        Cancelar
                    </button>
                </form>
            </div>

            <p class="flex flex-wrap gap-x-4 text-sm">
                <button type="button" x-on:click="shareList()"
                    class="inline-flex min-h-11 items-center font-medium text-blue-700 underline focus-visible:outline-2 focus-visible:outline-blue-500">
                    Compartir enlace
                </button>
                <button type="button" x-show="inMyLists" x-on:click="forgetList()"
                    class="inline-flex min-h-11 items-center text-gray-500 underline hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-500">
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
                        <button type="submit" class="min-h-11 rounded-lg bg-red-600 px-3 font-semibold text-white">
                            Sí, eliminar
                        </button>
                    </form>
                    <button type="button" x-ref="cancelDelete"
                        x-on:click="confirmingDelete = false; $nextTick(() => $refs.menuButton.focus())"
                        class="min-h-11 rounded-lg px-3 text-gray-600">
                        Cancelar
                    </button>
                </div>
            </div>
        </header>

        <form class="mb-4 space-y-2" x-on:submit.prevent="addItem($event)">
            <div class="flex gap-2">
                <label for="new-item" class="sr-only">Nombre del ítem</label>
                <input id="new-item" x-ref="newItem" name="name" type="text" maxlength="100" required
                    autocomplete="off" placeholder="Agregar ítem…"
                    class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-base focus:border-blue-500 focus:outline-2 focus:outline-blue-500">
                <button type="submit" class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white">
                    Agregar
                </button>
            </div>
            {{-- Second row: three controls do not fit at 320 px, so the optional
                 quantity and price sit below name + button. --}}
            <div class="flex gap-2">
                <label for="new-quantity" class="sr-only">Cantidad (opcional)</label>
                <input id="new-quantity" name="quantity" type="text" maxlength="50" autocomplete="off"
                    placeholder="Cantidad"
                    class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-base focus:border-blue-500 focus:outline-2 focus:outline-blue-500">
                <label for="new-price" class="sr-only">Precio (opcional)</label>
                <input id="new-price" name="price" type="text" inputmode="decimal" maxlength="11"
                    autocomplete="off" placeholder="Precio ({{ $list->currency }})"
                    :placeholder="'Precio (' + currency + ')'"
                    class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-base focus:border-blue-500 focus:outline-2 focus:outline-blue-500">
            </div>
            <label for="added-by" class="sr-only">Tu nombre (quién agrega)</label>
            <input id="added-by" name="added_by" type="text" maxlength="50" autocomplete="off" x-model="author"
                x-on:change="saveAuthor()" placeholder="Tu nombre (opcional)"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-base focus:border-blue-500 focus:outline-2 focus:outline-blue-500">
        </form>

        {{-- Pre-hydration list: server-ordered, shown until list.js has loaded
             the authoritative state via `show`. Prices are accumulated as whole
             cents so the pending total never drifts through float addition —
             the same technique the client uses once it takes over. --}}
        @php
            $displayPrices = [];
            $pendingCents = 0;
            $hasPendingPrice = false;

            foreach ($items as $item) {
                if ($item->price === null) {
                    continue;
                }

                $cents = (int) round($item->price * 100);
                $displayPrices[$item->id] = number_format($cents / 100, 2, ',', '.');

                if (!$item->is_purchased) {
                    $pendingCents += $cents;
                    $hasPendingPrice = true;
                }
            }
        @endphp

        <ul id="item-list" class="divide-y divide-gray-200 rounded-lg border border-gray-200" x-show="!ready">
            @forelse ($items as $item)
                <li class="grid grid-cols-[2rem_minmax(0,1fr)_auto_2rem] gap-x-2 px-3 py-2 transition-colors duration-300 {{ $item->is_purchased ? 'text-gray-500' : '' }}"
                    data-item-id="{{ $item->id }}">
                    <span
                        class="col-start-2 row-start-1 min-w-0 wrap-break-word py-2.5 {{ $item->is_purchased ? 'line-through' : '' }}">{{ $item->name }}</span>
                    @if (isset($displayPrices[$item->id]))
                        <span
                            class="item-price col-start-3 row-start-1 self-center px-1.5 text-sm font-medium tabular-nums text-gray-800">{{ $displayPrices[$item->id] }}</span>
                    @endif
                    @if ($item->quantity || $item->added_by)
                        <div
                            class="col-span-2 col-start-2 row-start-2 -mt-1 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 pb-1 text-xs">
                            @if ($item->quantity)
                                <span
                                    class="rounded-lg bg-gray-100 px-2 py-1 font-medium text-gray-600">{{ $item->quantity }}</span>
                            @endif
                            @if ($item->added_by)
                                <span class="text-gray-500">{{ $item->added_by }}</span>
                            @endif
                        </div>
                    @endif
                </li>
            @empty
                <li class="flex flex-col items-center gap-2 px-3 py-8 text-center text-sm text-gray-600">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                        class="size-9 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                    Esta lista está vacía. Agrega el primer ítem arriba.
                </li>
            @endforelse
        </ul>

        {{-- Pending total, pre-hydration only: the symbol goes here and in the
             header, never on each row. Hidden as soon as list.js is ready, so
             the client's own total is the only one on screen afterwards. --}}
        @if ($hasPendingPrice)
            <div class="pending-total mt-4 flex items-baseline justify-end gap-2 text-sm text-gray-600" x-show="!ready">
                <span>Total</span>
                <span
                    class="text-lg font-semibold text-gray-900 tabular-nums">{{ $list->currency . ' ' . number_format($pendingCents / 100, 2, ',', '.') }}</span>
            </div>
        @endif

        {{-- Client-rendered list. All user content bound with x-text. --}}
        <ul id="client-item-list" class="divide-y divide-gray-200 rounded-lg border border-gray-200" x-show="ready"
            x-cloak style="display:none">
            <template x-for="item in items" :key="item.id">
                <li class="grid grid-cols-[2rem_minmax(0,1fr)_auto_2rem] gap-x-2 px-3 py-2 transition-colors duration-300"
                    :data-item-id="item.id" :class="item.is_purchased ? 'text-gray-500' : ''">
                    <label
                        class="col-start-1 row-span-2 row-start-1 -my-2 -ml-3 flex w-11 shrink-0 cursor-pointer justify-center">
                        <input type="checkbox" class="mt-4.5 size-6 rounded border-gray-300 accent-blue-600"
                            :checked="item.is_purchased" x-on:change="togglePurchased(item)"
                            :aria-label="(item.is_purchased ? 'Desmarcar ' : 'Marcar ') + item.name">
                    </label>
                    <span x-show="!item.editing" x-text="item.name" x-on:click="startEdit(item)"
                        x-on:keydown.enter.prevent="startEdit(item)" x-on:keydown.space.prevent="startEdit(item)"
                        tabindex="0" role="button" :aria-label="'Editar ' + item.name"
                        :class="item.is_purchased ? 'line-through' : ''"
                        class="col-start-2 row-start-1 block min-w-0 cursor-pointer wrap-break-word py-2.5 focus:underline focus:outline-none focus:ring-2 focus:ring-blue-500"></span>
                    {{-- Editing keeps the name and the quantity in one box:
                         moving the focus between them stays inside the wrapper,
                         so only leaving the row (checkbox, ×, another row,
                         outside the page) commits. --}}
                    <div x-show="item.editing" x-on:focusout="commitEditOnFocusOut($event, item)"
                        class="col-span-2 col-start-2 row-start-1 flex min-w-0 flex-col gap-2 py-1 sm:flex-row">
                        <input data-edit-field="name" x-model="item.draftName" type="text" maxlength="100"
                            x-on:keydown.enter.prevent="commitEdit(item)" x-on:keydown.escape="item.editing = false"
                            class="min-w-0 flex-1 rounded border border-gray-300 px-2 py-1 text-base">
                        <label :for="'quantity-' + item.id" class="sr-only" x-text="'Cantidad de ' + item.name"></label>
                        <input data-edit-field="quantity" :id="'quantity-' + item.id" x-model="item.draftQuantity"
                            type="text" maxlength="50" autocomplete="off"
                            x-on:keydown.enter.prevent="commitEdit(item)" x-on:keydown.escape="item.editing = false"
                            class="w-full rounded border border-gray-300 px-2 py-1 text-base sm:w-24">
                    </div>
                    {{-- Precio en la fila: el monto cuando existe, un "+" discreto
                         cuando no (es la única entrada para ponerle precio a un
                         ítem creado sin él), y el editor mientras está abierto.
                         El símbolo de la moneda no aparece aquí: solo en el
                         encabezado y en el total. --}}
                    <div x-show="!item.editing" class="col-start-3 row-start-1 flex min-w-0 items-center justify-end">
                        <button type="button" x-show="!item.editingPrice && item.price != null"
                            x-text="formatPrice(item.price)" x-on:click="startEditPrice(item)"
                            :aria-label="'Editar el precio de ' + item.name"
                            class="item-price inline-flex min-h-11 items-center rounded-lg px-1.5 text-sm font-medium tabular-nums text-gray-800 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-blue-500">
                        </button>
                        <button type="button" x-show="!item.editingPrice && item.price == null"
                            x-on:click="startEditPrice(item)" :aria-label="'Agregar precio a ' + item.name"
                            class="inline-flex min-h-11 items-center px-1.5 text-xs font-semibold text-gray-600 hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-500">
                            + precio
                        </button>
                        <div x-show="item.editingPrice" x-on:focusout="commitPriceOnFocusOut($event, item)"
                            class="flex min-w-0 items-center gap-1">
                            <label :for="'price-' + item.id" class="sr-only" x-text="'Precio de ' + item.name"></label>
                            <input :id="'price-' + item.id" x-model="item.draftPrice" type="text" inputmode="decimal"
                                maxlength="11" autocomplete="off" x-on:keydown.enter.prevent="commitEditPrice(item)"
                                x-on:keydown.escape="item.editingPrice = false"
                                class="w-24 min-w-0 rounded border border-gray-300 px-2 py-1 text-right text-sm tabular-nums focus:border-blue-500 focus:outline-2 focus:outline-blue-500">
                            <button type="button" x-show="item.price != null" x-on:mousedown.prevent
                                x-on:click="clearPrice(item)"
                                class="min-h-11 shrink-0 px-2 text-xs text-gray-500 hover:text-red-600">
                                Borrar
                            </button>
                        </div>
                    </div>
                    {{-- The quantity chip is the way into the editor for an item
                         that already has one (and the only way to give a
                         quantity to an item created without it — there is no
                         separate marker for that case). --}}
                    <div x-show="!item.editing && (item.quantity || item.added_by)"
                        class="col-span-2 col-start-2 row-start-2 -mt-1 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 pb-1 text-xs">
                        <span x-show="item.quantity" x-text="item.quantity" x-on:click="startEdit(item)"
                            x-on:keydown.enter.prevent="startEdit(item)" x-on:keydown.space.prevent="startEdit(item)"
                            tabindex="0" role="button" :aria-label="'Editar la cantidad de ' + item.name"
                            class="item-quantity relative rounded-lg bg-gray-100 px-2 py-1 font-medium text-gray-600 before:absolute before:inset-x-0 before:-inset-y-2.5 before:content-[''] hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-500"></span>
                        <span x-show="item.added_by" x-text="item.added_by" class="text-gray-500"></span>
                    </div>
                    <button type="button" x-on:click="removeItem(item)"
                        class="col-start-4 row-start-1 -mr-3 flex min-h-11 min-w-11 items-center justify-center text-lg leading-none text-gray-500 hover:text-red-600"
                        :aria-label="'Eliminar ' + item.name">
                        &times;
                    </button>
                </li>
            </template>
            <li x-show="items.length === 0"
                class="flex flex-col items-center gap-2 px-3 py-8 text-center text-sm text-gray-600">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                    class="size-9 text-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                </svg>
                Esta lista está vacía. Agrega el primer ítem arriba.
            </li>
        </ul>

        {{-- Client-side twin of the pre-hydration total above: same place, same
             wording, but driven by state — it appears once list.js owns the
             list and at least one pending item has a price, and it follows the
             prices as they change. Only one of the two is ever on screen. --}}
        <div id="live-total" class="mt-4 flex items-baseline justify-end gap-2 text-sm text-gray-600"
            x-show="ready && hasPendingPrice" x-cloak style="display:none">
            <span>Total</span>
            <span class="text-lg font-semibold text-gray-900 tabular-nums"
                x-text="currency + ' ' + formattedPendingTotal"></span>
        </div>

        @if ($items->contains('is_purchased', true))
            <div class="mt-4" x-show="hasPurchased">
                <button type="button" x-ref="purgeButton"
                    x-on:click="confirmingPurge = true; $nextTick(() => $refs.cancelPurge.focus())"
                    class="inline-flex min-h-11 items-center text-sm text-gray-500 underline hover:text-red-600 focus-visible:outline-2 focus-visible:outline-blue-500">
                    Limpiar comprados
                </button>

                <div x-show="confirmingPurge" style="display:none"
                    class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm" role="alertdialog"
                    aria-label="Confirmar limpieza de comprados">
                    <p class="mb-2 font-medium text-gray-800">
                        ¿Quitar todos los ítems ya comprados de la lista?
                    </p>
                    <div class="flex gap-2">
                        <form
                            x-on:submit.prevent="purgePurchased().then(() => { if (!confirmingPurge) $nextTick(() => $refs.newItem.focus()) })">
                            <button type="submit" class="min-h-11 rounded-lg bg-gray-800 px-3 font-semibold text-white">
                                Sí, limpiar
                            </button>
                        </form>
                        <button type="button" x-ref="cancelPurge"
                            x-on:click="confirmingPurge = false; $nextTick(() => $refs.purgeButton.focus())"
                            class="min-h-11 rounded-lg px-3 text-gray-600">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
