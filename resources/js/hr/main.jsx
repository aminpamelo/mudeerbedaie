import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import { reloadOnceForStaleChunk } from './lib/lazyWithRetry';

// Recover from stale chunks left behind by a newer deploy: Vite fires
// `vite:preloadError` when a module preload 404s. Reload once to pull the
// current build instead of crashing into the error boundary.
window.addEventListener('vite:preloadError', (event) => {
    event.preventDefault();
    reloadOnceForStaleChunk();
});

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('hr-app');
    if (container) {
        const root = createRoot(container);
        root.render(
            <React.StrictMode>
                <App />
            </React.StrictMode>
        );
    }
});

// Register service worker for PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('SW registered:', registration.scope);
            })
            .catch((error) => {
                console.log('SW registration failed:', error);
            });
    });
}
