import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        proxy: Object.fromEntries(['/api', '/storage', '/sanctum'].map(prefix => [prefix, { target: process.env.HARBORAI_BACKEND_URL || 'http://127.0.0.1:8001', changeOrigin: true }])),
    },
});