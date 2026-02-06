/**
 * Workflow Builder Entry Point
 * Bootstraps the React application
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

// Import React Flow CSS
import '@xyflow/react/dist/style.css';

// Import custom styles
import './styles/workflow-builder.css';

// Mount the app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('workflow-builder-app');

    if (container) {
        const root = createRoot(container);
        // Get workflow UUID from the window config (set by Blade template)
        const config = window.workflowBuilderConfig || {};
        const workflowUuid = config.workflowUuid || null;

        root.render(
            <React.StrictMode>
                <App workflowUuid={workflowUuid} />
            </React.StrictMode>
        );
    }
});
