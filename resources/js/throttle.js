// Create-list quota is hourly, so the wait is told in minutes (item writes
// use seconds, see list.js).
export function throttledListsMessage(retryAfter) {
    const minutes = Math.ceil((Number(retryAfter) || 0) / 60);

    return minutes > 0
        ? `Creaste demasiadas listas seguidas. Inténtalo de nuevo en ${minutes} min.`
        : 'Creaste demasiadas listas seguidas. Inténtalo de nuevo más tarde.';
}
