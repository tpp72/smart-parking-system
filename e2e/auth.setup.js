import { test as setup, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const AUTH_DIR = path.join(__dirname, '.auth');

setup.beforeAll(() => {
  if (!fs.existsSync(AUTH_DIR)) fs.mkdirSync(AUTH_DIR, { recursive: true });
});

// Save admin session ─────────────────────────────────────────────────────────
setup('authenticate as admin', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.locator('input[name="email"]').fill('admin@tester.com');
  await page.locator('input[name="password"]').fill('Admin1234!');
  await page.locator('button[type="submit"]').click();

  await page.waitForURL('**/admin/dashboard', { timeout: 30_000 });
  await expect(page).toHaveURL(/admin\/dashboard/);

  await page.context().storageState({ path: path.join(AUTH_DIR, 'admin.json') });
  console.log('✓ Admin auth state saved');
});

// Save user session ──────────────────────────────────────────────────────────
setup('authenticate as user', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.locator('input[name="email"]').fill('user@tester.com');
  await page.locator('input[name="password"]').fill('User1234!');
  await page.locator('button[type="submit"]').click();

  await page.waitForURL('**/user/dashboard', { timeout: 30_000 });
  await expect(page).toHaveURL(/user\/dashboard/);

  await page.context().storageState({ path: path.join(AUTH_DIR, 'user.json') });
  console.log('✓ User auth state saved');
});

// Save owner session ─────────────────────────────────────────────────────────
setup('authenticate as owner', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.locator('input[name="email"]').fill('owner@tester.com');
  await page.locator('input[name="password"]').fill('Owner1234!');
  await page.locator('button[type="submit"]').click();

  await page.waitForURL('**/owner/dashboard', { timeout: 30_000 });
  await expect(page).toHaveURL(/owner\/dashboard/);

  await page.context().storageState({ path: path.join(AUTH_DIR, 'owner.json') });
  console.log('✓ Owner auth state saved');
});
