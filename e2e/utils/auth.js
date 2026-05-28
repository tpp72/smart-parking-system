import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export const ADMIN_STATE = path.join(__dirname, '../.auth/admin.json');
export const USER_STATE  = path.join(__dirname, '../.auth/user.json');

export const ADMIN_CREDENTIALS = { email: 'admin@tester.com', password: '12345678' };
export const USER_CREDENTIALS  = { email: 'user@tester.com',  password: '1234' };

/**
 * Login programmatically and return to the redirected URL.
 * Useful in tests that need to switch roles mid-run.
 */
export async function loginAs(page, role = 'admin') {
  const creds = role === 'admin' ? ADMIN_CREDENTIALS : USER_CREDENTIALS;
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(creds.email);
  await page.locator('input[name="password"]').fill(creds.password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/dashboard/, { timeout: 15_000 });
}

export async function logout(page) {
  await page.evaluate(() => {
    const form = document.querySelector('form[action*="logout"]');
    if (form) form.submit();
  });
  await page.waitForURL(/login/, { timeout: 10_000 });
}
