import { defineConfig, devices } from '@playwright/test';

/**
 * E2E ตาม project-plan.md §25.2 — Reservation Happy Path และ Walk-in Flow
 *
 * - รันกับฐานข้อมูลทดสอบ (ค่าเริ่มต้น smart_parking_test) ที่ถูก migrate:fresh --seed ก่อนทุกครั้ง — ไม่แตะฐานข้อมูลที่ใช้พัฒนา
 * - AI Scan ใช้โหมดจำลอง (CARSCAN_FAKE) อ่านผลจากชื่อไฟล์ ไม่เรียก Claude API
 * - เปิด Web Server ด้วย PHP built-in server เพื่อให้ Environment ด้านบนถูกส่งถึง Laravel
 */
process.env.E2E_DB_DATABASE ??= 'smart_parking_test';

const PORT = 8010;

export default defineConfig({
  testDir: './e2e',
  testMatch: '**/*.test.js',
  globalSetup: './e2e/global-setup.js',
  timeout: 240_000,
  retries: 0,
  workers: 1,
  fullyParallel: false,

  reporter: [
    ['list'],
    ['html', { outputFolder: 'e2e/reports/html', open: 'never' }],
  ],

  use: {
    baseURL: `http://127.0.0.1:${PORT}`,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    locale: 'th-TH',
    timezoneId: 'Asia/Bangkok',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },

  outputDir: 'e2e/screenshots/playwright-output',

  webServer: {
    // router ของ Laravel ต้องรันจากโฟลเดอร์ public (เหมือน php artisan serve)
    command: `php -S 127.0.0.1:${PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
    cwd: 'public',
    url: `http://127.0.0.1:${PORT}/login`,
    reuseExistingServer: false,
    stderr: 'ignore', // PHP built-in server เขียน access log ทุก request ลง stderr
    timeout: 60_000,
    env: {
      APP_ENV: 'local',
      DB_DATABASE: process.env.E2E_DB_DATABASE,
      CARSCAN_FAKE: 'true',
    },
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
    },
  ],
});
