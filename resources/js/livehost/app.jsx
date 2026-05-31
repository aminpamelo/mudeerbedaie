import './styles/livehost.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

// When the CSRF token / session has expired (e.g. signed in elsewhere), a
// write request comes back as 419. Inertia would otherwise render the raw
// response in a blank error modal — intercept it and offer a clean reload
// (which fetches a fresh token) instead of a confusing white box.
router.on('invalid', (event) => {
  if (event.detail?.response?.status === 419) {
    event.preventDefault();
    if (window.confirm('Your session expired (you may have signed in elsewhere). Reload the page to continue?\n\nNote: unsaved changes on this screen will be lost.')) {
      window.location.reload();
    }
  }
});

createInertiaApp({
  title: (title) => title ? `${title} · Live Host Desk` : 'Live Host Desk',
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.jsx', { eager: true });
    const page = pages[`./pages/${name}.jsx`];
    if (!page) {
      throw new Error(`[livehost] page not found: ${name}`);
    }
    return page;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#10B981' },
});
