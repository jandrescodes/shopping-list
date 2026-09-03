import { defineConfig, devices } from '@playwright/test';

// Browser-level tests for the client layer (RF-6, RF-21, RF-22, RF-23, RF-25,
// RF-26, RF-27, RF-32). Run with `npx playwright test`, separately from
// `php artisan test` -- Pest 4 / pest-plugin-browser need PHP 8.3+ and the
// environment is on 8.2 (constitution 1).
//
// The web server boots against the MySQL testing database defined in
// .env.testing (copy .env.testing.example, create `shopping_list_testing`
// once). Assets are built first so `@vite` resolves the compiled list.js.

const PORT = 8199;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: `http://127.0.0.1:${PORT}`,
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: {
        command: `npm run build && APP_ENV=testing php artisan migrate:fresh --force && APP_ENV=testing E2E_RELAXED_LIMITS=1 php artisan serve --host=127.0.0.1 --port=${PORT}`,
        url: `http://127.0.0.1:${PORT}`,
        reuseExistingServer: false,
        timeout: 120_000,
    },
});
