import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/app.tsx'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        proxy: Object.fromEntries(['/api', '/storage', '/sanctum'].map(prefix => [prefix, { target: process.env.HARBORAI_BACKEND_URL || 'http://127.0.0.1:8001', changeOrigin: true }])),
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
