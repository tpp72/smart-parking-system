import { test, expect } from '@playwright/test';
import { expectReservationStatus, loginAs, markPaid, paidPaymentRow, scanCar, uniquePlate } from './support/helpers.js';

/**
 * project-plan.md §25.2 — Walk-in Flow
 * รับภาพรถ → AI Scan → ไม่พบ Reservation → สร้าง Walk-in Reservation → Auto Check-in → จัด Slot
 * → Check-out → Payment → Completed
 */
test('walk-in flow: scan without booking → walk-in check-in → check-out → payment → completed', async ({ browser }, testInfo) => {
  const admin = await loginAs(browser, testInfo.project.use.baseURL, 'admin');
  const plate = uniquePlate('วอ');

  // ── รถเข้า: ไม่พบการจอง → Walk-in + จัดช่องจอด ────────────────────────────
  await scanCar(admin, { plate, brand: 'Honda', color: 'ดำ' });
  await expect(admin.getByText('เช็คอินอัตโนมัติสำเร็จ (Walk-in)')).toBeVisible();
  await expect(admin.getByText('ระบบจัดสรรช่อง')).toBeVisible();
  await expectReservationStatus(admin, plate, 'checked_in');

  // ── รถออก: Auto Check-out + สร้างยอดค่าจอด (Walk-in ไม่มีมัดจำ/ส่วนลด) ─────────
  await scanCar(admin, { plate, brand: 'Honda', color: 'ดำ' });
  await expect(admin.getByText('เช็คเอาท์อัตโนมัติสำเร็จ')).toBeVisible();

  // ── ยืนยันรับชำระค่าจอด ──────────────────────────────────────────────────
  await markPaid(admin, plate, 'ค่าจอด');
  await expect(admin.getByText('บันทึกการชำระเงิน')).toBeVisible();

  const payment = await paidPaymentRow(admin, plate, 'ค่าจอด');
  await expect(payment).toHaveCount(1);
  await expect(payment).toContainText('ชำระแล้ว');
  await expect(payment).not.toContainText('฿0.00');

  await expectReservationStatus(admin, plate, 'completed');
});
