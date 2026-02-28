import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/whatsapp-inbox.css';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('whatsapp-inbox-app');
    if (container) {
        const props = {
            csrfToken: container.dataset.csrfToken,
            apiBase: container.dataset.apiBase,
        };
        const root = createRoot(container);
        root.render(<App {...props} />);
    }
});
