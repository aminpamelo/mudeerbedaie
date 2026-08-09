import './styles/mindpal.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

// When the CSRF token / session has expired, a write comes back as 419.
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
  title: (title) => (title ? `${title} · MindPal` : 'MindPal'),
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.jsx', { eager: true });
    const page = pages[`./pages/${name}.jsx`];
    if (!page) {
      throw new Error(`[mindpal] page not found: ${name}`);
    }
    return page;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#8B5CF6' },
});
