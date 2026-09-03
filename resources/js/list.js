import Alpine from 'alpinejs';

// Shopping list view logic (Alpine component). T28 covers the core: initial
// load through `show`, reactive rendering with x-text for every piece of user
// content (never x-html, RF-32), and add / edit / mark / delete that wait for
// the API response before touching the view (no optimistic UI). Each edit sends
// only the fields that change (RF-25). Local memory (T29), polling (T30) and
// the offline behaviour (T31) build on top of this component.

const JSON_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' };

// Shown when a write fails with no network (RF-26). Writes need a connection and
// are never queued for later, so the wording must not promise a retry.
const OFFLINE_MESSAGE = 'Sin conexión. La acción no se guardó; inténtalo otra vez cuando vuelvas a tener red.';

// Client-side memory (T29, RF-6 / RF-21). `myShoppingLists` is shared with the
// home page: an array of { slug, name }, most-recently-opened first, capped at
// MAX_LISTS. `myShoppingListAuthor` is the "who is adding" name proposed when
// creating items. Every access is wrapped: storage can be disabled or full.
const MY_LISTS_KEY = 'myShoppingLists';
const AUTHOR_KEY = 'myShoppingListAuthor';
const MAX_LISTS = 20;

function readMyLists() {
    try {
        return JSON.parse(localStorage.getItem(MY_LISTS_KEY)) || [];
    } catch (e) {
        return [];
    }
}

function writeMyLists(entries) {
    try {
        localStorage.setItem(MY_LISTS_KEY, JSON.stringify(entries));
    } catch (e) {
        // storage unavailable: the directory link is a convenience, not core
    }
}

function readAuthor() {
    try {
        return localStorage.getItem(AUTHOR_KEY) || '';
    } catch (e) {
        return '';
    }
}

function writeAuthor(name) {
    try {
        localStorage.setItem(AUTHOR_KEY, name);
    } catch (e) {
        // ignore: proposing the name again next time is best-effort
    }
}

// RF-18 order: not purchased first, then purchased; within each group by
// creation order. ItemResource carries no timestamp, but ids are handed out
// monotonically, so ascending id matches ascending creation.
function orderItems(items) {
    return [...items].sort((a, b) => {
        if (a.is_purchased !== b.is_purchased) {
            return a.is_purchased ? 1 : -1;
        }

        return a.id - b.id;
    });
}

function normalize(item) {
    return { ...item, editing: false, draftName: item.name };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('listApp', () => ({
        slug: '',
        version: 0,
        listName: '',
        items: [],
        ready: false,
        error: '',
        editingName: false,
        confirmingDelete: false,
        confirmingPurge: false,
        author: '',
        inMyLists: true,
        shareNotice: '',
        shareUrl: '',
        pollTimer: null,
        pollStopped: false,
        offline: false,

        init() {
            this.slug = this.$el.dataset.slug;
            this.version = Number(this.$el.dataset.version) || 0;
            this.listName = (this.$el.querySelector('h1')?.textContent || '').trim();
            this.author = readAuthor();
            this.offline = navigator.onLine === false;
            window.addEventListener('online', () => this.goOnline());
            window.addEventListener('offline', () => { this.offline = true; });
            this.load();
        },

        // Back online (RF-26): the last known list stayed on screen; pull the
        // real state right away instead of waiting for the next poll tick.
        goOnline() {
            this.offline = false;

            if (this.error === OFFLINE_MESSAGE) {
                this.error = '';
            }

            if (!this.pollStopped) {
                clearTimeout(this.pollTimer);
                this.poll();
            }
        },

        // Message for a failed write: keep the server's own error, but name a
        // dropped connection for what it is (a fetch network failure has no
        // .status). Writes are never queued (RF-26).
        writeError(e, fallback) {
            return e && e.status ? fallback : OFFLINE_MESSAGE;
        },

        // Record this list in the local directory (RF-6), newest first, name
        // kept in sync with whatever the server last returned, capped at 20.
        rememberList() {
            const entries = readMyLists().filter((entry) => entry.slug !== this.slug);
            entries.unshift({ slug: this.slug, name: this.listName });
            writeMyLists(entries.slice(0, MAX_LISTS));
            this.inMyLists = true;
        },

        forgetList() {
            writeMyLists(readMyLists().filter((entry) => entry.slug !== this.slug));
            this.inMyLists = false;
        },

        saveAuthor() {
            writeAuthor(this.author.trim());
        },

        // Share the list's own URL (RF-34). Native share sheet on mobile; on a
        // browser without it, copy to the clipboard; with neither, show the URL
        // to copy by hand. All client-side — no API call, works offline.
        async shareList() {
            const url = window.location.href;
            this.shareNotice = '';
            this.shareUrl = '';

            if (navigator.share) {
                try {
                    await navigator.share({ title: this.listName, url });
                } catch (e) {
                    // cancelled sheet (AbortError) or a share that fell through:
                    // nothing useful to tell the user (RF-34)
                }

                return;
            }

            try {
                await navigator.clipboard.writeText(url);
                this.shareNotice = 'Enlace copiado. Pégalo donde quieras compartirlo.';
            } catch (e) {
                this.shareUrl = url; // last resort: let them select and copy it
            }
        },

        // --- Cross-device sync by polling (T30, RF-22, RF-23, RF-27) ---

        startPolling() {
            this.pollStopped = false;
            this.visibilityHandler = () => this.onVisibilityChange();
            document.addEventListener('visibilitychange', this.visibilityHandler);
            this.schedulePoll();
        },

        stopPolling() {
            this.pollStopped = true;
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
            document.removeEventListener('visibilitychange', this.visibilityHandler);
        },

        schedulePoll() {
            if (this.pollStopped || document.hidden) {
                return;
            }

            // 3-4 s cadence (RF-22); the jitter spreads out several open tabs.
            this.pollTimer = setTimeout(() => this.poll(), 3000 + Math.random() * 1000);
        },

        onVisibilityChange() {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;

            // Hidden: stop. Visible again: sync right away, then resume (RF-22).
            if (!document.hidden && !this.pollStopped) {
                this.poll();
            }
        },

        async poll() {
            this.pollTimer = null;

            if (document.hidden || this.pollStopped) {
                return;
            }

            await this.syncOnce();
            this.schedulePoll();
        },

        async syncOnce() {
            let data;

            try {
                data = await this.request(`/api/lists/${this.slug}/items?cursor=${this.version}`);
            } catch (e) {
                if (e.status === 404) {
                    // Deleted from another device (RF-27); the server can't tell
                    // us that directly, the open client infers it.
                    this.stopPolling();
                    this.forgetList();
                    this.error = 'Esta lista ya no existe.';
                }

                return; // a network blip keeps the last known list visible (RF-26)
            }

            const removed = new Set(data.deleted_ids);
            const merged = this.items.filter((item) => !removed.has(item.id));

            data.items.forEach((raw) => {
                const incoming = normalize(raw);
                const index = merged.findIndex((item) => item.id === incoming.id);

                if (index === -1) {
                    merged.push(incoming);

                    return;
                }

                // Don't stomp an inline edit the user is still typing.
                const current = merged[index];
                merged.splice(index, 1, current.editing
                    ? { ...incoming, editing: true, draftName: current.draftName }
                    : incoming);
            });

            this.items = orderItems(merged);
            this.version = data.cursor;
            this.offline = false;

            if (this.error === OFFLINE_MESSAGE) {
                this.error = ''; // a clean sync means the connection is back
            }
        },

        get hasPurchased() {
            return this.items.some((item) => item.is_purchased);
        },

        async request(path, options = {}) {
            const res = await fetch(path, { headers: JSON_HEADERS, ...options });

            if (!res.ok) {
                const error = new Error('request failed');
                error.status = res.status;
                throw error;
            }

            return res.status === 204 ? null : res.json();
        },

        // Authoritative initial load (RF-3). The server-rendered list stays
        // visible until this resolves.
        async load() {
            try {
                const data = await this.request(`/api/lists/${this.slug}`);
                this.listName = data.name;
                this.version = data.version;
                this.items = orderItems(data.items.map(normalize));
                this.ready = true;
                this.error = '';
                this.rememberList(); // refreshes the stored name too (RF-6)
                this.startPolling();
            } catch (e) {
                if (e.status === 404) {
                    this.forgetList(); // drop a list the server no longer has
                    this.error = 'Esta lista ya no existe.';
                } else {
                    this.error = 'No se pudo cargar la lista.';
                }
            }
        },

        upsert(raw) {
            const item = normalize(raw);
            const index = this.items.findIndex((current) => current.id === item.id);

            if (index === -1) {
                this.items.push(item);
            } else {
                this.items.splice(index, 1, item);
            }

            this.items = orderItems(this.items);
        },

        async addItem(event) {
            const input = event.target.elements.name;
            const name = input.value.trim();

            if (!name) {
                return;
            }

            const addedBy = this.author.trim();
            const payload = addedBy ? { name, added_by: addedBy } : { name };

            try {
                const item = await this.request(`/api/lists/${this.slug}/items`, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                this.upsert(item); // view only changes once the API has answered
                input.value = '';
                this.error = '';

                if (addedBy) {
                    writeAuthor(addedBy); // remember "who is adding" (RF-21)
                }
            } catch (e) {
                this.error = e.status === 422
                    ? 'No se pudo agregar el ítem. Revisa el nombre o el límite de la lista.'
                    : this.writeError(e, 'No se pudo agregar el ítem.');
            }
        },

        async togglePurchased(item) {
            try {
                const updated = await this.request(`/api/lists/${this.slug}/items/${item.id}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ is_purchased: !item.is_purchased }),
                });
                this.upsert(updated);
                this.error = '';
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo actualizar el ítem.');
            }
        },

        startEdit(item) {
            item.draftName = item.name;
            item.editing = true;
        },

        async commitEdit(item) {
            if (!item.editing) {
                return;
            }

            const name = item.draftName.trim();
            item.editing = false;

            if (!name || name === item.name) {
                return;
            }

            try {
                // Only the changed field goes in the payload (RF-25).
                const updated = await this.request(`/api/lists/${this.slug}/items/${item.id}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ name }),
                });
                this.upsert(updated);
                this.error = '';
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo guardar el cambio.');
            }
        },

        async removeItem(item) {
            try {
                await this.request(`/api/lists/${this.slug}/items/${item.id}`, { method: 'DELETE' });
                this.items = this.items.filter((current) => current.id !== item.id);
                this.error = '';
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo eliminar el ítem.');
            }
        },

        async renameList(name) {
            const trimmed = name.trim();

            if (!trimmed || trimmed === this.listName) {
                this.editingName = false;

                return;
            }

            try {
                const data = await this.request(`/api/lists/${this.slug}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ name: trimmed }),
                });
                this.listName = data.name;
                this.version = data.version;
                this.editingName = false;
                this.error = '';
                this.rememberList(); // keep the stored name in sync (RF-6)
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo renombrar la lista.');
            }
        },

        async deleteList() {
            try {
                await this.request(`/api/lists/${this.slug}`, { method: 'DELETE' });
                window.location.assign('/');
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo eliminar la lista.');
            }
        },

        async purgePurchased() {
            try {
                const data = await this.request(`/api/lists/${this.slug}/items/purge-purchased`, {
                    method: 'POST',
                });
                const removed = new Set(data.deleted_ids);
                this.items = this.items.filter((item) => !removed.has(item.id));
                this.confirmingPurge = false;
                this.error = '';
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo limpiar los comprados.');
            }
        },
    }));
});

window.Alpine = Alpine;
Alpine.start();
