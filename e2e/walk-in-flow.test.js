import { test, expect } from '@playwright/test';
import { driverPaysAtTrack, expectReservationStatus, loginAs, paidPaymentRow, readReferenceCode, scanCar, uniquePlate } from './support/helpers.js';

/**
 * project-plan.md §25.2 — Walk-in Flow
 * รับภาพรถ → AI Scan → ไม่พบ Reservation → สร้าง Walk-in Reservation → Auto Check-in → จัด Slot
 * → กด Check-out + ชำระก่อนออก (§12.6) → Auto Check-out → Completed
 */
test('walk-in flow: scan without booking → walk-in check-in → pay before exit → check-out → completed', async ({ browser }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL;
  const admin = await loginAs(browser, baseURL, 'admin');
  const plate = uniquePlate('วอ');

  // ── รถเข้า: ไม่พบการจอง → Walk-in + จัดช่องจอด + ออกรหัสอ้างอิงให้คนขับ ─────────
  await scanCar(admin, { plate, brand: 'Honda', color: 'ดำ' });
  await expect(admin.getByText('เช็คอินอัตโนมัติสำเร็จ (Walk-in)')).toBeVisible();
  await expect(admin.getByText('ระบบจัดสรรช่อง')).toBeVisible();
  const code = await readReferenceCode(admin);
  expect(code).toMatch(/^[2-9A-HJ-KMNP-Z]{6}$/);
  await expectReservationStatus(admin, plate, 'checked_in');

  // ── สแกนออกโดยยังไม่ได้ชำระ → กล้องไม่ปล่อยรถ ────────────────────────────────
  await scanCar(admin, { plate, brand: 'Honda', color: 'ดำ' });
  await expect(admin.getByText('ยังไม่ได้ชำระค่าจอด')).toBeVisible();
  await expect(admin.getByText('Check-out ไม่สำเร็จ กรุณาชำระค่าจอด')).toBeVisible();
  await expectReservationStatus(admin, plate, 'checked_in');

  // ── คนขับกด Check-out และชำระเองที่หน้าเช็คสถานะรถ (ไม่มีบัญชี) ─────────────────
  await driverPaysAtTrack(browser, baseURL, { plate, code });

  // ── สแกนออกอีกครั้ง → ปล่อยรถ ────────────────────────────────────────────
  await scanCar(admin, { plate, brand: 'Honda', color: 'ดำ' });
  await expect(admin.getByText('เช็คเอาท์อัตโนมัติสำเร็จ')).toBeVisible();

  // ── ยอดค่าจอดชำระแล้วตั้งแต่ก่อนออก — ไม่มียอดค้างให้เจ้าหน้าที่ตามเก็บ ───────────
  const payment = await paidPaymentRow(admin, plate, 'ค่าจอด');
  await expect(payment).toHaveCount(1);
  await expect(payment).toContainText('ชำระแล้ว');
  await expect(payment).not.toContainText('฿0.00');

  await expectReservationStatus(admin, plate, 'completed');
});
