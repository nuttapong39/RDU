import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // login-scene.js: the login page's Three.js scene; drug-code-sets.js: the settings page editor — each loaded on its own page only
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/login-scene.js', 'resources/js/drug-code-sets.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
