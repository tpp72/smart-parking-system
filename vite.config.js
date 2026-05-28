import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],

    server: {
        watch: {
            // Keep Vite's hot-reload watcher away from every directory and file
            // type that Playwright generates during test runs.  Without these
            // ignores, Vite crashes or endlessly restarts when Playwright
            // writes binary screenshots (.png), videos (.webm), trace archives
            // (.zip), partial Chromium downloads (.crdownload), and HTML
            // reports into the project tree while the dev server is running.
            ignored: [
                // Playwright test artifact directories (all under e2e/)
                '**/e2e/screenshots/**',
                '**/e2e/reports/**',
                '**/e2e/logs/**',
                '**/e2e/.auth/**',
                // Playwright default output dirs (top-level, if ever used)
                '**/playwright-report/**',
                '**/test-results/**',
                // Root-level reports written by the afterAll hook
                'bug-report.md',
                'coverage-report.md',
                'error-log.json',
                // Individual binary/generated file types Playwright emits
                '**/*.webm',        // video recordings
                '**/*.zip',         // trace archives
                '**/*.crdownload',  // Chromium in-progress downloads
            ],
            // Raise the stability threshold so rapid bursts of writes
            // (many screenshots in one test run) don't queue endless reloads.
            stabilityThreshold: 2000,
            pollInterval: 1000,
        },
    },
});
