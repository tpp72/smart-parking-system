import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  testMatch: '**/*.test.js',
  timeout: 30_000,
  retries: 1,
  workers: 1,           // sequential — single Laravel dev server
  fullyParallel: false,

  reporter: [
    ['list'],
    ['html', { outputFolder: 'e2e/reports/html', open: 'never' }],
    ['json', { outputFile: 'e2e/reports/results.json' }],
  ],

  use: {
    baseURL: 'http://127.0.0.1:8000',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    locale: 'th-TH',
    timezoneId: 'Asia/Bangkok',
    actionTimeout: 10_000,
    navigationTimeout: 15_000,
  },

  outputDir: 'e2e/screenshots/playwright-output',

  projects: [
    // ─── Auth setup (must run first, no storageState) ───────────────────
    {
      name: 'setup',
      testMatch: /auth\.setup\.js/,
    },

    // ─── Desktop Chromium ────────────────────────────────────────────────
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 800 },
        storageState: 'e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },

    // ─── Desktop Firefox ─────────────────────────────────────────────────
    {
      name: 'firefox',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 800 },
        storageState: 'e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },

    // ─── Desktop WebKit (Safari) ─────────────────────────────────────────
    {
      name: 'webkit',
      use: {
        ...devices['Desktop Safari'],
        viewport: { width: 1280, height: 800 },
        storageState: 'e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },

    // ─── Mobile (375×667) ────────────────────────────────────────────────
    {
      name: 'mobile',
      use: {
        ...devices['iPhone 12'],
        storageState: 'e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },

    // ─── Tablet (768×1024) ───────────────────────────────────────────────
    {
      name: 'tablet',
      use: {
        ...devices['iPad (gen 7)'],
        storageState: 'e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
  ],
});
