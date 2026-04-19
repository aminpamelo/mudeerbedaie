import './styles/pocket.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
  title: (title) => (title ? `${title} · Live Host Pocket` : 'Live Host Pocket'),
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
