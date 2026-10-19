import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: [
                'app/**/*.php',
                'config/**/*.php',
                'database/**/*.php',
                'resources/views/**/*.blade.php',
                'routes/**/*.php',
            ],
        }),
    ],
});
