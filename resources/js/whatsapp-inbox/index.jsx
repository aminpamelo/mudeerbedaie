import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/whatsapp-inbox.css';

let currentRoot = null;

function mountApp() {
    const container = document.getElementById('whatsapp-inbox-app');
    if (!container) return;

    // Unmount previous instance if exists
    if (currentRoot) {
        currentRoot.unmount();
        currentRoot = null;
    }

    const props = {
        csrfToken: container.dataset.csrfToken,
        apiBase: container.dataset.apiBase,
    };
    currentRoot = createRoot(container);
    currentRoot.render(<App {...props} />);
}

// Mount on initial page load or immediately if DOM already ready (wire:navigate race condition)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountApp);
} else {
    mountApp();
}

// Re-mount after Livewire wire:navigate navigation
document.addEventListener('livewire:navigated', mountApp);
