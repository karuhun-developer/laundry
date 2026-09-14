import { fileURLToPath, URL } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { configDefaults, defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        exclude: [...configDefaults.exclude, '.claude/worktrees/**'],
    },
});
