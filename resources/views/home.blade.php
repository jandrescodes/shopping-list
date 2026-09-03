@extends('layout')

@section('title', 'Mis listas de compras')

@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-bold">Listas de compras</h1>
        <p class="mt-1 text-sm text-gray-600">
            Crea una lista y comparte su enlace con tu familia. Sin cuentas ni contraseñas.
        </p>
    </header>

    <section x-data="homeCreate()" class="mb-8">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
            Nueva lista
        </h2>
        <form x-on:submit.prevent="create" class="flex gap-2">
            <label for="list-name" class="sr-only">Nombre de la lista</label>
            <input id="list-name" name="name" type="text" x-model="name" maxlength="60" required autocomplete="off"
                placeholder="Feria del sábado"
                class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-base focus:border-blue-500 focus:outline-none">
            <button type="submit" x-bind:disabled="busy"
                class="shrink-0 rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white disabled:opacity-50">
                Crear
            </button>
        </form>
        <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600" style="display:none"></p>
    </section>

    <section x-data="myLists()" x-init="load()">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
            Mis listas
        </h2>

        <p x-show="entries.length === 0" class="text-sm text-gray-500" style="display:none">
            Todavía no has abierto ninguna lista en este dispositivo.
        </p>

        <ul x-show="entries.length > 0" class="divide-y divide-gray-200 rounded-lg border border-gray-200"
            style="display:none">
            <template x-for="entry in entries" x-bind:key="entry.slug">
                <li class="flex items-center justify-between gap-2 px-3 py-3">
                    <a x-bind:href="'/l/' + entry.slug" class="min-w-0 flex-1 truncate font-medium text-blue-700"
                        x-text="entry.name"></a>
                    <button type="button" x-on:click="remove(entry.slug)"
                        class="shrink-0 text-sm text-gray-400 hover:text-red-600" aria-label="Quitar de mis listas">
                        Quitar
                    </button>
                </li>
            </template>
        </ul>
    </section>

    <script>
        // Home-page glue. The list-page memory (writing entries, name refresh,
        // 404 pruning, 20-entry cap) lives in resources/js/list.js (T29); both
        // sides share the `myShoppingLists` localStorage key.
        const MY_LISTS_KEY = 'myShoppingLists';

        function readMyLists() {
            try {
                return JSON.parse(localStorage.getItem(MY_LISTS_KEY)) || [];
            } catch (e) {
                return [];
            }
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('homeCreate', () => ({
                name: '',
                busy: false,
                error: '',
                async create() {
                    this.busy = true;
                    this.error = '';
                    try {
                        const res = await fetch('/api/lists', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                name: this.name
                            }),
                        });
                        if (!res.ok) {
                            this.error =
                                'No se pudo crear la lista. Revisa el nombre e inténtalo de nuevo.';
                            return;
                        }
                        const data = await res.json();
                        window.location.href = '/l/' + data.slug;
                    } catch (e) {
                        this.error = 'Sin conexión. Inténtalo cuando vuelvas a tener red.';
                    } finally {
                        this.busy = false;
                    }
                },
            }));

            Alpine.data('myLists', () => ({
                entries: [],
                load() {
                    this.entries = readMyLists();
                },
                remove(slug) {
                    this.entries = this.entries.filter((e) => e.slug !== slug);
                    try {
                        localStorage.setItem(MY_LISTS_KEY, JSON.stringify(this.entries));
                    } catch (e) {}
                },
            }));
        });
    </script>
@endsection
