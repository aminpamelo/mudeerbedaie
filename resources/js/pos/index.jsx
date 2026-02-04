import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/pos.css';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('pos-app');
    if (container) {
        const root = createRoot(container);
        root.render(
            <React.StrictMode>
                <App />
            </React.StrictMode>
        );
    }
});
