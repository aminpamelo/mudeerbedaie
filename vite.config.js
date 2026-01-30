import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/reports-charts.js',
                'resources/js/email-builder.js',
                'resources/css/email-builder.css',
                'resources/js/react-email-builder.jsx',
                'resources/css/react-email-builder.css',
                'resources/js/funnel-builder/index.jsx',
                'resources/js/funnel-builder/styles/funnel-builder.css',
                'resources/js/workflow-builder/index.jsx',
                'resources/js/workflow-builder/styles/workflow-builder.css',
                'resources/js/affiliate-dashboard/index.jsx',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        cors: true,
    },
});