import Alpine from 'alpinejs';

// Shopping list view logic (Alpine component). Reactive rendering uses x-text
// for every piece of user content (never x-html), and add / edit / mark /
// delete wait for the API response before touching the view (no optimistic
// UI). Each edit sends only the fields that change.

const JSON_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' };

// Shown when a write fails with no network. Writes need a connection and are
// never queued for later, so the wording must not promise a retry.
const OFFLINE_MESSAGE = 'Sin conexión. La acción no se guardó; inténtalo otra vez cuando vuelvas a tener red.';

// Client-side memory. `myShoppingLists` is shared with the home page: an
// array of { slug, name }, most-recently-opened first, capped at MAX_LISTS.
// `myShoppingListAuthor` is the "who is adding" name proposed when creating
// items. Every access is wrapped: storage can be disabled or full.
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

// Not purchased first, then purchased; within each group by creation order.
// ItemResource carries no timestamp, but ids are handed out monotonically,
// so ascending id matches ascending creation.
function orderItems(items) {
    return [...items].sort((a, b) => {
        if (a.is_purchased !== b.is_purchased) {
            return a.is_purchased ? 1 : -1;
        }

        return a.id - b.id;
    });
}

// Empty (or separator-only) means "no price"; anything else gets the comma
// turned into the dot the column expects. Validity itself stays the server's
// call (the Form Request is the boundary of trust): this is only the courtesy
// normalization, so a bad value still travels and still gets rejected with the
// real validation message.
function normalizePriceInput(value) {
    const trimmed = (value ?? '').trim();

    if (!trimmed || /^[.,]+$/.test(trimmed)) {
        return null;
    }

    return trimmed.replace(/,/g, '.');
}

// Amounts always read the same way regardless of the browser locale: comma as
// decimal separator, two decimals (the format the app shows everywhere).
const AMOUNT_FORMAT = new Intl.NumberFormat('es', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

// Whole cents, because 0.1 + 0.2 !== 0.3 in IEEE-754: adding in integers keeps
// the rounding to a single division at the end. An absent price adds nothing.
function toCents(value) {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    const amount = Number.parseFloat(value);

    return Number.isFinite(amount) ? Math.round(amount * 100) : 0;
}

function normalize(item) {
    return {
        ...item,
        editing: false,
        draftName: item.name,
        draftQuantity: item.quantity ?? '',
        editingPrice: false,
        draftPrice: item.price ?? '',
    };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('listApp', () => ({
        slug: '',
        version: 0,
        listName: '',
        currency: '',
        items: [],
        ready: false,
        error: '',
        editingName: false,
        editingCurrency: false,
        confirmingDelete: false,
        confirmingPurge: false,
        author: '',
        inMyLists: true,
        shareNotice: '',
        shareUrl: '',
        pollTimer: null,
        pollStopped: false,
        offline: false,
        pendingUndo: null,
        undoTimer: null,
        shareNoticeTimer: null,

        init() {
            this.slug = this.$el.dataset.slug;
            this.version = Number(this.$el.dataset.version) || 0;
            this.listName = (this.$el.querySelector('h1')?.textContent || '').trim();
            this.currency = (this.$el.querySelector('#currency-chip')?.textContent || 'Bs').trim();
            this.author = readAuthor();
            this.offline = navigator.onLine === false;
            window.addEventListener('online', () => this.goOnline());
            window.addEventListener('offline', () => { this.offline = true; });
            this.load();
        },

        // Back online: the last known list stayed on screen; pull the real
        // state right away instead of waiting for the next poll tick.
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
        // .status). Writes are never queued.
        writeError(e, fallback) {
            return e && e.status ? fallback : OFFLINE_MESSAGE;
        },

        // Record this list in the local directory, newest first, name kept
        // in sync with whatever the server last returned, capped at 20.
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

        // Native share sheet on mobile; on a browser without it, copy to the
        // clipboard; with neither, show the URL to copy by hand. All
        // client-side — no API call, works offline.
        async shareList() {
            const url = window.location.href;
            clearTimeout(this.shareNoticeTimer);
            this.shareNotice = '';
            this.shareUrl = '';

            if (navigator.share) {
                try {
                    await navigator.share({ title: this.listName, url });
                } catch (e) {
                    // cancelled sheet (AbortError) or a share that fell through:
                    // nothing useful to tell the user
                }

                return;
            }

            try {
                await navigator.clipboard.writeText(url);
                this.shareNotice = 'Enlace copiado. Pégalo donde quieras compartirlo.';
                // Auto-dismiss: an unclosed "copied" confirmation would otherwise sit on
                // screen through later, unrelated actions and push the list down.
                this.shareNoticeTimer = setTimeout(() => { this.shareNotice = ''; }, 5000);
            } catch (e) {
                this.shareUrl = url; // last resort: let them select and copy it
            }
        },

        // --- Cross-device sync by polling ---

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

            // 3-4 s cadence; the jitter spreads out several open tabs.
            this.pollTimer = setTimeout(() => this.poll(), 3000 + Math.random() * 1000);
        },

        onVisibilityChange() {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;

            // Hidden: stop. Visible again: sync right away, then resume.
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
                    // Deleted from another device; the server can't tell us
                    // that directly, the open client infers it.
                    this.stopPolling();
                    this.forgetList();
                    this.error = 'Esta lista ya no existe.';
                }

                return; // a network blip keeps the last known list visible
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

                // Don't stomp an inline edit the user is still typing. The
                // name/quantity edit and the price edit each carry their own
                // flag, so the merge has to preserve both (a price typed from
                // its chip is lost otherwise: the incoming row wins).
                const current = merged[index];
                const editing = current.editing;
                const editingPrice = current.editingPrice;

                merged.splice(index, 1, (editing || editingPrice)
                    ? {
                        ...incoming,
                        editing,
                        draftName: editing ? current.draftName : incoming.draftName,
                        draftQuantity: editing ? current.draftQuantity : incoming.draftQuantity,
                        editingPrice,
                        draftPrice: editingPrice ? current.draftPrice : incoming.draftPrice,
                    }
                    : incoming);
            });

            this.items = orderItems(merged);
            this.version = data.cursor;
            this.offline = false;

            // List metadata (name + currency) rides along on every sync. The
            // guard matters: a cached client talking to an older server that
            // has no `list` block would throw a TypeError here and kill the
            // poll forever.
            if (data.list) {
                const renamed = this.listName !== data.list.name;

                this.listName = data.list.name;
                this.currency = data.list.currency;

                // Refresh the stored name only while the list is still in "my
                // lists": re-running rememberList() on every tick would
                // resurrect an entry the user just removed.
                if (renamed && this.inMyLists) {
                    this.rememberList();
                }
            }

            if (this.error === OFFLINE_MESSAGE) {
                this.error = ''; // a clean sync means the connection is back
            }
        },

        get hasPurchased() {
            return this.items.some((item) => item.is_purchased);
        },

        // The total is derived, never stored: it is recomputed from the items
        // already in memory, so it costs no round-trip and needs no version.
        // Purchased items drop out of it, and it disappears altogether while
        // no pending item carries a price (a total of 0,00 over an unpriced
        // list would look like an answer to a question nobody asked).
        get pendingTotalCents() {
            return this.items
                .filter((item) => !item.is_purchased)
                .reduce((total, item) => total + toCents(item.price), 0);
        },

        get hasPendingPrice() {
            return this.items.some((item) => !item.is_purchased && item.price != null);
        },

        get formattedPendingTotal() {
            return AMOUNT_FORMAT.format(this.pendingTotalCents / 100);
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

        // Authoritative initial load. The server-rendered list stays visible
        // until this resolves.
        async load() {
            try {
                const data = await this.request(`/api/lists/${this.slug}`);
                this.listName = data.name;
                this.currency = data.currency;
                this.version = data.version;
                this.items = orderItems(data.items.map(normalize));
                this.ready = true;
                this.error = '';
                this.rememberList(); // refreshes the stored name too
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
            const priceInput = event.target.elements.price;
            const quantityInput = event.target.elements.quantity;
            const name = input.value.trim();

            if (!name) {
                return;
            }

            const addedBy = this.author.trim();
            const price = normalizePriceInput(priceInput ? priceInput.value : '');
            const quantity = quantityInput ? quantityInput.value.trim() : '';
            const payload = addedBy ? { name, added_by: addedBy } : { name };

            // Absent stays absent: the field is omitted, never sent as null,
            // so an item without a price keeps having none.
            if (price !== null) {
                payload.price = price;
            }

            // Same for quantity: blank after trimming means "no quantity".
            if (quantity) {
                payload.quantity = quantity;
            }

            try {
                const item = await this.request(`/api/lists/${this.slug}/items`, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                this.upsert(item); // view only changes once the API has answered
                input.value = '';

                if (priceInput) {
                    priceInput.value = '';
                }

                if (quantityInput) {
                    quantityInput.value = '';
                }

                this.error = '';

                if (addedBy) {
                    writeAuthor(addedBy); // remember "who is adding"
                }
            } catch (e) {
                this.error = e.status === 422
                    ? 'No se pudo agregar el ítem. Revisa el nombre, la cantidad, el precio o el límite de la lista.'
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
            // `?? ''` — x-model on null would write the literal "null".
            item.draftQuantity = item.quantity ?? '';
            item.editing = true;
        },

        // Clicking from one edit field to its sibling must not close the row.
        commitEditOnFocusOut(event, item) {
            if (event.currentTarget.contains(event.relatedTarget)) {
                return;
            }

            // Some browsers report a null relatedTarget during a synchronous
            // focus transfer. Defer the decision until the new active element
            // is available, so moving between the two editors stays in place.
            setTimeout(() => {
                if (event.currentTarget.contains(document.activeElement)) {
                    return;
                }

                this.commitEdit(item);
            }, 0);
        },

        async commitEdit(item) {
            if (!item.editing) {
                return;
            }

            const name = item.draftName.trim();
            const quantity = (item.draftQuantity ?? '').trim();
            item.editing = false;

            const payload = {};

            // A blank name is never sent (the row keeps the one it has); a
            // blank quantity is — the server stores it as "no quantity".
            if (name && name !== item.name) {
                payload.name = name;
            }

            if (quantity !== (item.quantity ?? '')) {
                payload.quantity = quantity;
            }

            if (Object.keys(payload).length === 0) {
                return; // nothing changed: close the editor and write nothing
            }

            try {
                // Only the changed fields go in the payload.
                const updated = await this.request(`/api/lists/${this.slug}/items/${item.id}`, {
                    method: 'PATCH',
                    body: JSON.stringify(payload),
                });
                this.upsert(updated);
                this.error = '';
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo guardar el cambio.');
            }
        },

        // --- Price: edited from its own chip (or from the "+" an item without
        // one offers), with its own flag, never through the name edit, so one
        // field ends up as one PATCH ---

        formatPrice(value) {
            const amount = Number(value);

            return Number.isFinite(amount) ? AMOUNT_FORMAT.format(amount) : '';
        },

        startEditPrice(item) {
            // Open the editor with the same comma form the row displays, so
            // tapping the chip never changes the number under the finger.
            item.draftPrice = item.price == null ? '' : String(item.price).replace('.', ',');
            item.editingPrice = true;
        },

        async commitEditPrice(item) {
            if (!item.editingPrice) {
                return;
            }

            const price = normalizePriceInput(item.draftPrice);
            item.editingPrice = false;

            if (price === null) {
                await this.clearPrice(item);

                return;
            }

            if (item.price != null && Number(price) === Number(item.price)) {
                return; // unchanged: no write (same idea as commitEdit)
            }

            await this.patchPrice(item, price, 'No se pudo guardar el precio.');
        },

        async clearPrice(item) {
            item.editingPrice = false;

            if (item.price == null) {
                return; // nothing to clear
            }

            // `null`, never `""`: an absent price is not a zero price.
            await this.patchPrice(item, null, 'No se pudo borrar el precio.');
        },

        // Commit when focus leaves the whole editor (input + "Borrar"), not the
        // input alone: moving on to "Borrar" must not send a value that is
        // about to be cleared anyway.
        commitPriceOnFocusOut(event, item) {
            if (event.currentTarget.contains(event.relatedTarget)) {
                return;
            }

            this.commitEditPrice(item);
        },

        async patchPrice(item, price, failure) {
            try {
                const updated = await this.request(`/api/lists/${this.slug}/items/${item.id}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ price }),
                });
                this.upsert(updated);
                this.error = '';
            } catch (e) {
                this.error = this.writeError(e, failure);
            }
        },

        async removeItem(item) {
            try {
                await this.request(`/api/lists/${this.slug}/items/${item.id}`, { method: 'DELETE' });
                this.items = this.items.filter((current) => current.id !== item.id);
                this.error = '';
                this.offerUndo(item);
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo eliminar el ítem.');
            }
        },

        // Delete is the single riskiest tap in the list (sits next to the checkbox,
        // the most-repeated gesture) with no way back otherwise. The item
        // is already gone server-side by the time this runs (no optimistic UI,
        // no queued writes anywhere else in this app), so "undo" re-creates it
        // with the same content instead of rolling back the DELETE — a new id,
        // same name/quantity/added_by/purchased state/price. 5 s grace window.
        offerUndo(item) {
            clearTimeout(this.undoTimer);
            this.pendingUndo = {
                name: item.name,
                quantity: item.quantity,
                added_by: item.added_by,
                is_purchased: item.is_purchased,
                price: item.price,
            };
            this.undoTimer = setTimeout(() => { this.pendingUndo = null; }, 5000);
        },

        async undoRemove() {
            const snapshot = this.pendingUndo;

            if (!snapshot) {
                return;
            }

            clearTimeout(this.undoTimer);
            this.pendingUndo = null;

            const payload = { name: snapshot.name };

            if (snapshot.quantity) {
                payload.quantity = snapshot.quantity;
            }

            if (snapshot.added_by) {
                payload.added_by = snapshot.added_by;
            }

            // Present, not truthy: a price of 0 is still a price, and only an
            // absent one is left out.
            if (snapshot.price != null) {
                payload.price = snapshot.price;
            }

            try {
                const created = await this.request(`/api/lists/${this.slug}/items`, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                if (snapshot.is_purchased) {
                    const updated = await this.request(`/api/lists/${this.slug}/items/${created.id}`, {
                        method: 'PATCH',
                        body: JSON.stringify({ is_purchased: true }),
                    });
                    this.upsert(updated);
                } else {
                    this.upsert(created);
                }

                this.error = '';
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo deshacer la eliminación.');
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
                this.rememberList(); // keep the stored name in sync
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo renombrar la lista.');
            }
        },

        async renameCurrency(value) {
            const trimmed = value.trim();

            if (!trimmed || trimmed === this.currency) {
                this.editingCurrency = false;

                return;
            }

            try {
                const data = await this.request(`/api/lists/${this.slug}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ currency: trimmed }),
                });
                this.currency = data.currency;
                this.version = data.version;
                this.editingCurrency = false;
                this.error = '';
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo cambiar la moneda.');
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

// Deferred so page-specific modules loaded after this one (e.g. home.js,
// rendered via @yield('scripts') at the bottom of layout.blade.php) get a
// chance to register their own Alpine.data() components on 'alpine:init'
// before Alpine.start() fires that event. Module scripts all finish running
// before 'DOMContentLoaded', so this still starts Alpine as soon as possible.
document.addEventListener('DOMContentLoaded', () => Alpine.start());
