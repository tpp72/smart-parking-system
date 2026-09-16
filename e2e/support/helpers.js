import { expect } from '@playwright/test';

/** บัญชีจาก DatabaseSeeder (รหัสผ่าน password ทุกบัญชี) */
export const ACCOUNTS = {
  admin: { email: 'admin@demo.com', password: 'password' },
  user: { email: 'user@demo.com', password: 'password' },
};

/** ลานของ Admin จาก DatabaseSeeder — Admin ยืนยันรับเงินและดูการจองของลานนี้ได้ */
export const ADMIN_LOT = 'ลานจอดรถ เทศบาลนครระยอง';
export const PROVINCE = 'กรุงเทพมหานคร';

/** PNG 1×1 — เนื้อหารูปไม่สำคัญในโหมดจำลอง AI */
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
  'base64',
);

/** ทะเบียนไม่ซ้ำต่อการรัน (ระบบกันการจอง/จอดซ้ำของ ทะเบียน + จังหวัด) */
export function uniquePlate(prefix) {
  return `${prefix} ${String(Date.now()).slice(-4)}`;
}

/** เปิด Session ใหม่ของ Role ที่ระบุ (ยอมรับกล่อง confirm() อัตโนมัติ) */
export async function loginAs(browser, baseURL, role) {
  const context = await browser.newContext({ baseURL, locale: 'th-TH', timezoneId: 'Asia/Bangkok' });
  const page = await context.newPage();
  page.on('dialog', (dialog) => dialog.accept());

  await page.goto('/login');
  await page.locator('input[name="email"]').fill(ACCOUNTS[role].email);
  await page.locator('input[name="password"]').fill(ACCOUNTS[role].password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/dashboard/);

  return page;
}

/** เลือก option ที่ข้อความมีคำที่ระบุ */
export async function selectOptionContaining(page, selector, text) {
  const value = await page.locator(`${selector} option`, { hasText: text }).first().getAttribute('value');
  await page.selectOption(selector, value);
}

/** วันเวลาในเขตเวลาไทย รูปแบบเดียวกับ flatpickr (Y-m-d\TH:i) */
export function bangkokDateTime(date) {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Bangkok',
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(date).map((p) => [p.type, p.value]),
  );

  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

/**
 * จำลองกล้อง: อัปโหลดรูปที่ลาน ADMIN_LOT โดยชื่อไฟล์คือผลที่ AI (โหมดจำลอง) อ่านได้
 * @returns ข้อความผลการสแกนที่แสดงบนหน้าจอ
 */
export async function scanCar(page, { plate, brand = 'Toyota', color = 'ขาว', accuracy = 95 }) {
  await page.goto('/admin/scan');
  await selectOptionContaining(page, '#parking_lot_id', ADMIN_LOT);
  await page.locator('#car_image').setInputFiles({
    name: `${plate}__${PROVINCE}__${brand}__${color}__${accuracy}.png`,
    mimeType: 'image/png',
    buffer: PNG,
  });
  await page.getByRole('button', { name: 'วิเคราะห์รูปรถ' }).click();
  await page.waitForLoadState('domcontentloaded');
}

/** ตรวจว่าการจองของทะเบียนนี้ในลาน Admin อยู่ในสถานะที่ระบุ (ใช้ตัวกรองหน้าจัดการการจอง) */
export async function expectReservationStatus(adminPage, plate, status) {
  await adminPage.goto(`/admin/reservations?q=${encodeURIComponent(plate)}&status=${status}`);
  await expect(adminPage.locator('table')).toContainText(plate);
}

/** กด "รับชำระแล้ว" ของรายการค้างชำระที่ตรงกับทะเบียนและประเภท (มัดจำ / ค่าจอด) */
export async function markPaid(adminPage, plate, type) {
  await adminPage.goto('/admin/payments?status=unpaid');
  const row = adminPage.locator('tbody tr', { hasText: plate }).filter({ hasText: type });
  await expect(row).toHaveCount(1);
  await row.getByRole('button', { name: /รับชำระแล้ว/ }).click();
  await adminPage.waitForLoadState('domcontentloaded');
}

/** แถว Payment ที่ชำระแล้วของทะเบียนและประเภทนี้ */
export async function paidPaymentRow(adminPage, plate, type) {
  await adminPage.goto('/admin/payments?status=paid');
  return adminPage.locator('tbody tr', { hasText: plate }).filter({ hasText: type });
}
