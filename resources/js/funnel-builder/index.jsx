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

// Mount the app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('funnel-builder-app');

    if (container) {
        const root = createRoot(container);
        root.render(
            <React.StrictMode>
                <App />
            </React.StrictMode>
        );
    }
});
