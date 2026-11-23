import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/offline.js'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
    ],
    resolve: {
        // Optional jsPDF add-ons the PDF reports never use (see resources/js/stubs/unused.js).
        alias: Object.fromEntries(['html2canvas', 'canvg', 'dompurify'].map((name) => [
            name, fileURLToPath(new URL('./resources/js/stubs/unused.js', import.meta.url)),
        ])),
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
