import { defineConfig } from 'vitest/config';

// Standalone config so Vitest doesn't load vite.config.js — the Laravel
// plugin there refuses to start in CI environments.
export default defineConfig({
    test: {
        include: ['tests/js/**/*.test.js'],
    },
});
