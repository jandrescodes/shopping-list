// The list-page memory (writing entries, name refresh, 404 pruning, 20-entry
// cap) lives in resources/js/list.js; both sides share the `myShoppingLists`
// localStorage key.
const MY_LISTS_KEY = 'myShoppingLists';

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
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ name: this.name }),
                });
                if (!res.ok) {
                    this.error = 'No se pudo crear la lista. Revisa el nombre e inténtalo de nuevo.';
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
            } catch (e) { }
        },
    }));
});
