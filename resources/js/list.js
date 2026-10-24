import Alpine from 'alpinejs';
import sort from '@alpinejs/sort';
import { throttledListsMessage } from './throttle.js';

// Shopping list view logic (Alpine component). Reactive rendering uses x-text
// for every piece of user content (never x-html), and add / edit / mark /
// delete wait for the API response before touching the view (no optimistic
// UI). Each edit sends only the fields that change.

const JSON_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' };

// Shown when a write fails with no network. Writes need a connection and are
// never queued for later, so the wording must not promise a retry.
const OFFLINE_MESSAGE = 'Sin conexión. La acción no se guardó; inténtalo otra vez cuando vuelvas a tener red.';

const throttledMessage = (seconds) => seconds > 0
    ? `Demasiadas acciones seguidas. Espera ${seconds} s e inténtalo de nuevo.`
    : 'Demasiadas acciones seguidas. Espera un momento e inténtalo de nuevo.';

// Client-side memory. `myShoppingLists` is shared with the home page: an
// array of { slug, name }, most-recently-opened first, capped at MAX_LISTS.
// `myShoppingListAuthor` is the "who is adding" name proposed when creating
// items. Every access is wrapped: storage can be disabled or full.
const MY_LISTS_KEY = 'myShoppingLists';
const AUTHOR_KEY = 'myShoppingListAuthor';
const MAX_LISTS = 20;

function isValidEntry(entry) {
    return (
        entry !== null &&
        typeof entry === 'object' &&
        typeof entry.slug === 'string' &&
        /^[A-Za-z0-9_-]+$/.test(entry.slug) &&
        typeof entry.name === 'string'
    );
}

function readMyLists() {
    try {
        const parsed = JSON.parse(localStorage.getItem(MY_LISTS_KEY));

        return Array.isArray(parsed) ? parsed.filter(isValidEntry) : [];
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

const MOVE_DEBOUNCE_MS = 400;
const MIN_SUGGEST_CHARS = 2;
const MAX_SUGGESTIONS = 5;

// Comparison key for names: no case and no accents, so "Café" and "cafe" are
// the same name.
function nameKey(text) {
    return (text ?? '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase().trim();
}
// Same gap the server leaves between positions.
const POSITION_STEP = 1024;

// Not purchased first, then purchased; within each group by the server's
// manual position, with id as the tie-breaker the server also uses.
function orderItems(items) {
    return [...items].sort((a, b) => {
        if (a.is_purchased !== b.is_purchased) {
            return a.is_purchased ? 1 : -1;
        }

        return a.position - b.position || a.id - b.id;
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
        moveAnnouncement: '',
        newName: '',
        suggestionsOpen: false,
        activeSuggestion: -1,
        moveTimers: {},
        moveQueue: [],
        movingIds: new Set(),
        draining: false,
        error: '',
        editingName: false,
        editingCurrency: false,
        editingBudget: false,
        budget: null,
        confirmingDelete: false,
        menuOpen: false,
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
        resetUndo: null,
        shareNoticeTimer: null,
        exportNotice: '',
        exportFallback: '',
        exportNoticeTimer: null,

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
            if (e && e.status === 429) {
                return throttledMessage(e.retryAfter);
            }

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

        // Plain-text copy of the list, in the order the view shows it. Prices
        // go bare (the symbol only rides on the total) and use the same
        // formatter as the screen so both always agree.
        exportText() {
            const oneLine = (value) => String(value).replace(/\s*[\r\n]+\s*/g, ' ');
            const lines = [oneLine(this.listName)];

            for (const item of orderItems(this.items)) {
                let line = `- [${item.is_purchased ? 'x' : ' '}] ${oneLine(item.name)}`;
                if (item.quantity != null && item.quantity !== '') {
                    line += ` (${oneLine(item.quantity)})`;
                }
                if (item.price != null) {
                    line += ` — ${this.formatPrice(item.price)}`;
                }
                lines.push(line);
            }

            if (this.hasPendingPrice) {
                lines.push(`Total pendiente: ${this.currency} ${this.formattedPendingTotal}`);
            }

            return lines.join('\n');
        },

        // Clipboard first; when the browser refuses (or has none), show the
        // text in a readonly field, already selected, to copy by hand.
        async copyAsText() {
            const text = this.exportText();
            clearTimeout(this.exportNoticeTimer);
            this.exportNotice = '';
            this.exportFallback = '';

            try {
                await navigator.clipboard.writeText(text);
                this.exportNotice = 'Lista copiada. Pégala donde quieras compartirla.';
                this.exportNoticeTimer = setTimeout(() => { this.exportNotice = ''; }, 5000);
            } catch (e) {
                this.exportFallback = text;
                this.$nextTick(() => this.$refs.exportFallback?.select());
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

                if (this.movingIds.has(incoming.id)) {
                    const local = merged.find((item) => item.id === incoming.id);

                    if (local) {
                        incoming.position = local.position;
                    }
                }
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
                this.budget = data.list.budget ?? null;

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

        // A failed offline write already says "Sin conexión" in the error
        // banner; showing the amber one too would stack two near-identical
        // notices, so it only appears while the error is something else.
        get showOfflineNotice() {
            return this.offline && this.error !== OFFLINE_MESSAGE;
        },

        get hasPurchased() {
            return this.items.some((item) => item.is_purchased);
        },

        // Names the list already holds that contain what is being typed.
        // Purchased items count (a thing bought last week is what gets added
        // again), deleted ones are gone from `items`. One entry per name, from
        // the newest item, and never the text already typed in full.
        get suggestions() {
            const typed = nameKey(this.newName);

            if (typed.length < MIN_SUGGEST_CHARS) {
                return [];
            }

            const byKey = new Map();

            for (const item of this.items) {
                const key = nameKey(item.name);
                const known = byKey.get(key);

                if (key.includes(typed) && key !== typed && (!known || item.id > known.id)) {
                    byKey.set(key, item);
                }
            }

            return [...byKey.entries()]
                .sort(([a], [b]) => (b.startsWith(typed) - a.startsWith(typed)) || a.localeCompare(b, 'es'))
                .slice(0, MAX_SUGGESTIONS)
                .map(([, item]) => item.name);
        },

        get showSuggestions() {
            return this.suggestionsOpen && this.suggestions.length > 0;
        },

        get activeSuggestionId() {
            return this.showSuggestions && this.activeSuggestion >= 0 ? `suggestion-${this.activeSuggestion}` : null;
        },

        openSuggestions() {
            this.suggestionsOpen = true;
            this.activeSuggestion = -1;
        },

        closeSuggestions() {
            this.suggestionsOpen = false;
            this.activeSuggestion = -1;
        },

        moveSuggestion(step) {
            if (!this.showSuggestions) {
                this.openSuggestions();

                return;
            }

            // -1 is "none highlighted": the arrows cycle through it, so the
            // typed text can always be reached again.
            const next = this.activeSuggestion + step;
            this.activeSuggestion = next >= this.suggestions.length ? -1 : (next < -1 ? this.suggestions.length - 1 : next);
        },

        // Enter only picks when a row is highlighted; otherwise the form
        // submits what was typed.
        pickActiveSuggestion(event) {
            if (this.showSuggestions && this.activeSuggestion >= 0) {
                event.preventDefault();
                this.pickSuggestion(this.suggestions[this.activeSuggestion]);
            }
        },

        // Only the name travels: quantity and price of the old item are not
        // copied, they belong to that purchase.
        pickSuggestion(name) {
            this.newName = name;
            this.closeSuggestions();
            this.$refs.newItem.focus();
        },

        pendingIndex(item) {
            return this.items.filter((current) => !current.is_purchased).indexOf(item);
        },

        get pendingCount() {
            return this.items.filter((item) => !item.is_purchased).length;
        },

        // Drag-and-drop callback: `position` is the index among the rows, which
        // equals the index among the pending ones because pending rows come
        // first; a drop onto the purchased zone is clamped to the last pending
        // slot.
        onSort(id, position) {
            if (this.placeLocally(id, position)) {
                this.queueMove(id, 0);
            }
        },

        // Up/down buttons: each press moves one slot at once, but presses on
        // the same row travel as a single request with the final destination.
        moveItem(item, direction) {
            const pending = this.items.filter((current) => !current.is_purchased);
            const target = pending.indexOf(item) + direction;

            if (target < 0 || target >= pending.length || !this.placeLocally(item.id, target)) {
                return;
            }

            this.queueMove(item.id, MOVE_DEBOUNCE_MS);
            // Moving the node drops the focus in some browsers; at the end of
            // the list the button that just got disabled hands it to its twin.
            this.$nextTick(() => {
                const row = document.querySelector(`#client-item-list [data-item-id="${item.id}"]`);
                const button = row && (row.querySelector(`[data-move="${direction}"]:not(:disabled)`)
                    ?? row.querySelector('[data-move]:not(:disabled)'));

                button?.focus();
            });
        },

        // Applies the new order before the server answers: a row that snaps
        // back while the request is out would feel broken. Returns whether
        // anything moved.
        placeLocally(id, index) {
            const moving = this.items.find((item) => item.id === id);
            const pending = this.items.filter((item) => !item.is_purchased);

            if (!moving || moving.is_purchased) {
                return false;
            }

            const others = pending.filter((item) => item.id !== id);
            const slot = Math.max(0, Math.min(index, others.length));

            if (pending.indexOf(moving) === slot) {
                return false;
            }

            // Provisional position between the new neighbours, so a poll that
            // lands before the answer cannot sort the row back to its old place.
            const previous = others[slot - 1];
            const next = others[slot];

            if (previous && next) {
                moving.position = Math.floor((previous.position + next.position) / 2);
            } else if (next) {
                moving.position = next.position - POSITION_STEP;
            } else if (previous) {
                moving.position = previous.position + POSITION_STEP;
            }

            this.items = orderItems(this.items);
            this.moveAnnouncement = `${moving.name}: posición ${slot + 1} de ${pending.length}`;

            return true;
        },

        // One request per row and moment: a new press restarts the row's
        // timer, and the requests leave one at a time so they cannot arrive
        // out of order. While a row has a move waiting or in flight, polling
        // leaves its position alone.
        queueMove(id, delay) {
            this.movingIds.add(id);
            clearTimeout(this.moveTimers[id]);
            this.moveTimers[id] = setTimeout(() => {
                delete this.moveTimers[id];
                this.moveQueue.push(id);
                this.drainMoves();
            }, delay);
        },

        async drainMoves() {
            if (this.draining) {
                return;
            }

            this.draining = true;

            while (this.moveQueue.length > 0) {
                const id = this.moveQueue.shift();
                const pending = this.items.filter((item) => !item.is_purchased);
                const moving = pending.find((item) => item.id === id);

                if (!moving) {
                    this.movingIds.delete(id);

                    continue;
                }

                const next = pending[pending.indexOf(moving) + 1];

                try {
                    const updated = await this.request(`/api/lists/${this.slug}/items/${id}/position`, {
                        method: 'PATCH',
                        body: JSON.stringify({ before_id: next ? next.id : null }),
                    });
                    moving.position = updated.position;
                    this.error = '';

                    if (!(id in this.moveTimers)) {
                        this.movingIds.delete(id);
                    }
                } catch (e) {
                    this.abandonMoves();
                    await this.resync();

                    // An item gone elsewhere is not an error for whoever moved it.
                    if (e.status !== 404) {
                        this.error = this.writeError(e, 'No se pudo mover el ítem.');
                    }
                }
            }

            this.draining = false;

            if (this.movingIds.size === 0) {
                await this.syncOnce(); // brings the rows a renumbering shifted
            }
        },

        abandonMoves() {
            Object.values(this.moveTimers).forEach(clearTimeout);
            this.moveTimers = {};
            this.moveQueue = [];
            this.movingIds.clear();
        },

        // Back to the server's order after a failed move.
        async resync() {
            try {
                const data = await this.request(`/api/lists/${this.slug}`);
                this.version = data.version;
                this.items = orderItems(data.items.map(normalize));
            } catch (e) {
                // the next poll deals with a deleted list or a network blip
            }
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
                error.retryAfter = Number.parseInt(res.headers.get('Retry-After'), 10) || 0;
                error.errors = (await res.json().catch(() => null))?.errors ?? null;
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
                this.budget = data.budget ?? null;
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
                this.newName = '';
                this.closeSuggestions();

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

                // Whoever followed it in its own group; the other group's
                // order says nothing about where it sat in the sequence.
                const group = this.items.filter((current) => current.is_purchased === item.is_purchased);
                const next = group[group.indexOf(item) + 1];

                this.items = this.items.filter((current) => current.id !== item.id);
                this.error = '';
                this.offerUndo(item, next ? next.id : null);
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
        offerUndo(item, nextId = null) {
            clearTimeout(this.undoTimer);
            this.resetUndo = null;
            this.pendingUndo = {
                name: item.name,
                quantity: item.quantity,
                added_by: item.added_by,
                is_purchased: item.is_purchased,
                price: item.price,
                nextId,
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

            // An id that no longer exists leaves the item last, which is the
            // server's answer to a neighbour it cannot find.
            if (snapshot.nextId) {
                payload.before_id = snapshot.nextId;
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

        // The budget never blocks anything: it only tells how far over the
        // pending total is. Equal to the cap is not over it, and a list with no
        // priced pending item has no total to compare.
        get excessCents() {
            if (this.budget === null || !this.hasPendingPrice) {
                return 0;
            }

            return Math.max(0, this.pendingTotalCents - toCents(this.budget));
        },

        get formattedExcess() {
            return AMOUNT_FORMAT.format(this.excessCents / 100);
        },

        get budgetLabel() {
            return this.budget === null ? 'Sin definir' : `${this.currency} ${AMOUNT_FORMAT.format(Number(this.budget))}`;
        },

        // Blank removes the budget; the server owns what counts as a valid
        // amount, and its message is the one shown.
        async saveBudget(value) {
            const trimmed = value.trim();

            if (trimmed === '' && this.budget === null) {
                this.editingBudget = false;

                return;
            }

            try {
                const data = await this.request(`/api/lists/${this.slug}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ budget: trimmed === '' ? null : trimmed }),
                });
                this.budget = data.budget ?? null;
                this.version = data.version;
                this.editingBudget = false;
                this.error = '';
            } catch (e) {
                this.error = e.status === 422 && e.errors?.budget
                    ? e.errors.budget[0]
                    : this.writeError(e, 'No se pudo guardar el presupuesto.');
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

        // Creating a list has its own hourly quota, so a 429 here speaks in
        // minutes like the home does, not in the seconds of item writes.
        async duplicateList() {
            try {
                const data = await this.request(`/api/lists/${this.slug}/duplicate`, { method: 'POST' });
                window.location.assign(`/l/${data.slug}`);
            } catch (e) {
                this.error = e && e.status === 429
                    ? throttledListsMessage(e.retryAfter)
                    : this.writeError(e, 'No se pudo duplicar la lista.');
            }
        },

        // One undo slot shared with item deletion: the newest notice replaces
        // the previous one, so "Deshacer" always means the last thing done.
        // The server answers with the ids it unmarked; those are what undo
        // would send back.
        async resetPurchased() {
            try {
                const data = await this.request(`/api/lists/${this.slug}/items/reset-purchased`, {
                    method: 'POST',
                });
                const reset = new Set(data.reset_ids);
                this.items.forEach((item) => {
                    if (reset.has(item.id)) {
                        item.is_purchased = false;
                    }
                });
                this.error = '';

                clearTimeout(this.undoTimer);
                this.pendingUndo = null;
                if (data.reset_ids.length > 0) {
                    this.resetUndo = { ids: data.reset_ids };
                    this.undoTimer = setTimeout(() => { this.resetUndo = null; }, 5000);
                }
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo reiniciar las compras.');
            }
        },

        // Only the ids the server confirms come back as purchased: an item
        // another device deleted or re-marked meanwhile is left as it is, and
        // polling brings whatever else changed.
        async undoReset() {
            const snapshot = this.resetUndo;

            if (!snapshot) {
                return;
            }

            clearTimeout(this.undoTimer);
            this.resetUndo = null;

            try {
                const data = await this.request(`/api/lists/${this.slug}/items/restore-purchased`, {
                    method: 'POST',
                    body: JSON.stringify({ ids: snapshot.ids }),
                });
                const restored = new Set(data.restored_ids);
                this.items.forEach((item) => {
                    if (restored.has(item.id)) {
                        item.is_purchased = true;
                    }
                });
                this.error = '';
            } catch (e) {
                this.error = this.writeError(e, 'No se pudo deshacer el reinicio.');
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

Alpine.plugin(sort);

window.Alpine = Alpine;

// Deferred so page-specific modules loaded after this one (e.g. home.js,
// rendered via @yield('scripts') at the bottom of layout.blade.php) get a
// chance to register their own Alpine.data() components on 'alpine:init'
// before Alpine.start() fires that event. Module scripts all finish running
// before 'DOMContentLoaded', so this still starts Alpine as soon as possible.
document.addEventListener('DOMContentLoaded', () => Alpine.start());
