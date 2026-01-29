import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'resources/dist',
        emptyOutDir: true,
        rollupOptions: {
            input: resolve(__dirname, 'resources/js/addon.js'),
            output: {
                entryFileNames: 'js/addon.js',
                format: 'iife',
                globals: {
                    vue: 'Vue',
                },
            },
            external: ['vue'],
        },
    },
});
