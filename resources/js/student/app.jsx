import './styles/student.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

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
  title: (title) => (title ? `${title} · Student Portal` : 'Student Portal'),
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.jsx', { eager: true });
    const page = pages[`./pages/${name}.jsx`];
    if (!page) {
      throw new Error(`[student] page not found: ${name}`);
    }
    return page;
  },
  setup({ el, App, props }) {
    // Set translations globally before first render so t() works everywhere
    const pageProps = props.initialPage?.props ?? {};
    if (pageProps.translations) {
      window.__studentTranslations = pageProps.translations;
    }

    createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#7C3AED' },
});

// Keep translations in sync on SPA navigation
router.on('navigate', (event) => {
  const trans = event.detail?.page?.props?.translations;
  if (trans) {
    window.__studentTranslations = trans;
  }
});
