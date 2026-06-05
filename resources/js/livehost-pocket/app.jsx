import './styles/pocket.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

// Register the Live Host Pocket PWA service worker (scope /live-host). Served
// from the site root so the /live-host scope is allowed without a
// Service-Worker-Allowed header (same trick as the CEO worker).
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/pocket-sw.js', { scope: '/live-host' }).catch(() => {});
  });
}

// Expired CSRF token / session → 419. Reload to fetch a fresh token instead of
// surfacing Inertia's blank error modal.
router.on('invalid', (event) => {
  if (event.detail?.response?.status === 419) {
    event.preventDefault();
    if (window.confirm('Your session expired. Reload to continue?')) {
      window.location.reload();
    }
  }
});

createInertiaApp({
  title: (title) => (title ? `${title} · Sistem Livehost Bedaie` : 'Sistem Livehost Bedaie'),
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.jsx', { eager: true });
    const page = pages[`./pages/${name}.jsx`];
    if (!page) {
      throw new Error(`[livehost-pocket] page not found: ${name}`);
    }
    return page;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#7C3AED' },
});
