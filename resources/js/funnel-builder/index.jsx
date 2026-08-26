/**
 * Funnel Builder Entry Point
 * Bootstraps the React application
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

// Import Puck CSS
import '@puckeditor/core/puck.css';

// Import custom styles
import './styles/funnel-builder.css';
import './styles/studio-theme.css';

// Mount the app when DOM is ready. Guard on readyState instead of relying
// solely on DOMContentLoaded: ES module scripts are deferred and can execute
// *after* that event has already fired, in which case a bare listener never
// runs and the page stays blank until a refresh reshuffles the timing.
function mountApp() {
    const container = document.getElementById('funnel-builder-app');

    if (container && !container.dataset.mounted) {
        container.dataset.mounted = 'true';
        const root = createRoot(container);
        root.render(
            <React.StrictMode>
                <App />
            </React.StrictMode>
        );
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountApp);
} else {
    mountApp();
}
