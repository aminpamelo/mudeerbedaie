import './styles/ceo.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

// Register the CEO PWA service worker (scope /ceo). Served from the site root so
// the /ceo scope is allowed without a Service-Worker-Allowed header.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/ceo-sw.js', { scope: '/ceo' }).catch(() => {});
  });
}

// When the CSRF token / session has expired, a write request comes back as 419.
// Intercept it and offer a clean reload instead of Inertia's blank error modal.
router.on('invalid', (event) => {
  if (event.detail?.response?.status === 419) {
    event.preventDefault();
    if (window.confirm('Your session expired. Reload the page to continue?')) {
      window.location.reload();
    }
  }
});

createInertiaApp({
  title: (title) => (title ? `${title} · CEO Overview` : 'CEO Overview'),
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.jsx', { eager: true });
    const page = pages[`./pages/${name}.jsx`];
    if (!page) {
      throw new Error(`[ceo] page not found: ${name}`);
    }
    return page;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#0A0A0A' },
});
