import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => ({
    // 本番はWordPress配下の /great-gantt で公開する。開発サーバーは従来どおりルートで動かす。
    base: mode === 'production' ? '/great-gantt/build/' : '/',
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
}));
