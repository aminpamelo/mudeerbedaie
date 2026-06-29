import { lazy } from 'react';

const STORAGE_KEY = 'hr:chunk-reload';

function safeSession() {
    try {
        return window.sessionStorage;
    } catch {
        return null;
    }
}

/**
 * Force a single full-page reload to recover from a stale chunk.
 *
 * After a new deploy, `npm run build` emits freshly hashed asset filenames and
 * removes the old ones. A long-lived SPA session still references the old hash,
 * so its `import()` 404s ("Failed to fetch dynamically imported module").
 * Reloading re-renders the Blade shell through `@vite`, which serves the current
 * manifest and resolves the new chunk URLs.
 *
 * The sessionStorage guard prevents reload loops when the fetch fails for a real
 * reason (offline, server down) rather than a stale build.
 *
 * @returns {boolean} whether a reload was triggered
 */
export function reloadOnceForStaleChunk() {
    const store = safeSession();

    if (store?.getItem(STORAGE_KEY)) {
        return false;
    }

    store?.setItem(STORAGE_KEY, '1');
    window.location.reload();

    return true;
}

/** Clear the guard once a chunk has loaded successfully again. */
export function clearStaleChunkGuard() {
    safeSession()?.removeItem(STORAGE_KEY);
}

/**
 * Drop-in replacement for `React.lazy` that auto-recovers from stale chunks by
 * reloading the page once. Genuine load failures (after the one reload) bubble
 * up to the ErrorBoundary as usual.
 *
 * @param {() => Promise<{ default: import('react').ComponentType<any> }>} importer
 * @returns {import('react').LazyExoticComponent<import('react').ComponentType<any>>}
 */
export function lazyWithRetry(importer) {
    return lazy(async () => {
        try {
            const module = await importer();
            clearStaleChunkGuard();

            return module;
        } catch (error) {
            if (reloadOnceForStaleChunk()) {
                // Keep the Suspense fallback on screen until the reload kicks in.
                return new Promise(() => {});
            }

            throw error;
        }
    });
}
