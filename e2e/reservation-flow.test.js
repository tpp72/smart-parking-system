import { test, expect } from '@playwright/test';
import {
  ADMIN_LOT, PROVINCE, bangkokDateTime, expectReservationStatus, loginAs, markPaid,
  paidPaymentRow, scanCar, selectOptionContaining, uniquePlate,
} from './support/helpers.js';

/**
 * project-plan.md §25.2 — Reservation Happy Path
 * User Login → เลือก Lot → เลือกเวลา → กรอกข้อมูลรถ → ชำระ Deposit (Admin Mark as Paid) → Reservation Confirmed
 * → Auto Check-in → ระบบจัด Slot → Check-out → คำนวณยอด → ยืนยัน Payment → Reservation Completed
 */
test('reservation happy path: book → deposit paid → auto check-in → auto check-out → completed', async ({ browser }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL;
  const plate = uniquePlate('ทด');

  // ── User จอง: เลือกลาน เวลา และข้อมูลรถ ──────────────────────────────────
  const user = await loginAs(browser, baseURL, 'user');
  await user.goto('/user/reservations/create');

  // เวลาเริ่มจองต้องเป็นอนาคต แต่ Auto Check-in ทำได้เมื่อถึงเวลาจองแล้ว → จองล่วงหน้า 1–2 นาทีแล้วรอ
  const reserveStart = new Date(Math.floor((Date.now() + 120_000) / 60_000) * 60_000);

  await selectOptionContaining(user, '#parking_lot_id', ADMIN_LOT);
  await user.locator('#plate_number').fill(plate);
  await user.selectOption('#plate_province', PROVINCE);
  await user.locator('#brand').fill('Toyota');
  await user.selectOption('#color', 'ขาว');
  await user.evaluate((value) => document.querySelector('#reserve_start')._flatpickr.setDate(value, true), bangkokDateTime(reserveStart));
  await user.locator('form[action$="/user/reservations"] button[type="submit"]').click();

  await expect(user.getByText('ส่งคำขอจองสำเร็จ')).toBeVisible();
  await expect(user.locator('table')).toContainText(plate);

  // ── Admin ยืนยันรับเงินมัดจำ → Confirmed + Lock Slot ─────────────────────
  const admin = await loginAs(browser, baseURL, 'admin');
  await expectReservationStatus(admin, plate, 'pending');
  await markPaid(admin, plate, 'มัดจำ');
  await expect(admin.getByText('ยืนยันรับเงินมัดจำ')).toBeVisible();
  await expectReservationStatus(admin, plate, 'confirmed');

  // ── รอถึงเวลาจอง แล้วกล้องสแกนรถเข้า → Auto Check-in ─────────────────────
  const waitMs = reserveStart.getTime() - Date.now() + 3_000;
  if (waitMs > 0) await admin.waitForTimeout(waitMs);

  await scanCar(admin, { plate });
  await expect(admin.getByText('เช็คอินอัตโนมัติสำเร็จ')).toBeVisible();
  await expect(admin.getByText(/เช็คอินด้วยการจอง #\d+/)).toBeVisible();
  await expect(admin.getByText('ระบบจัดสรรช่อง')).toBeVisible();
  await expectReservationStatus(admin, plate, 'checked_in');

  // ── กล้องสแกนรถออก → Auto Check-out + คำนวณยอด ────────────────────────────
  await scanCar(admin, { plate });
  await expect(admin.getByText('เช็คเอาท์อัตโนมัติสำเร็จ')).toBeVisible();

  // จอดไม่ถึง 1 ชม. = ค่าจอด 1 ชม. ซึ่งมัดจำครอบคลุมทั้งหมด → ยอดสุทธิ 0 ระบบยืนยันชำระให้เอง
  const checkout = await paidPaymentRow(admin, plate, 'ค่าจอด');
  await expect(checkout).toHaveCount(1);
  await expect(checkout).toContainText('ชำระแล้ว');
  await expect(checkout).toContainText('฿0.00');
  await expect(checkout).toContainText(/มัดจำ -฿/);

  await expectReservationStatus(admin, plate, 'completed');
});
