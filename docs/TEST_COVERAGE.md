# TEST COVERAGE — Requirement ↔ Test

> อ้างอิง `docs/project-plan.md` §25 · อัปเดต 2026-09-28 (หลัง PR #5)
> PHPUnit: **442 tests** · 2,269 assertions (`php artisan test`) · E2E: **2 flows** (`npm run test:e2e`)
>
> ⚠️ ตารางจับคู่ requirement ↔ test ด้านล่างยังเป็นของรอบ 2026-09-20 (330 tests)
> ยังไม่ได้เพิ่มแถวของฟีเจอร์ที่ทำหลังจากนั้น: รูปแบบทะเบียน · ที่อยู่ลานจอด · เช็คสถานะรถ · ผูกทะเบียนกับบัญชี · ไอคอนระบบ
> ไฟล์เทสต์ของฟีเจอร์เหล่านั้นคือ `LicensePlateNormalizerTest` (unit), `LicensePlateMatchingTest`,
> `ThaiAddressTest`, `PublicTrackTest`, `UserVehicleTest`, `BrandIconTest`

## 1. §25.1 Feature / Integration Tests

| Test Area | สิ่งที่ต้องทดสอบ (§25.1) | ไฟล์ทดสอบ → เทสต์ที่ครอบคลุม |
| --- | --- | --- |
| **Authentication** | Register / Login / Logout / Email Verification / Role Redirect / Force Password Reset | `Auth/RegistrationTest` (register) · `Auth/AuthenticationTest` (login, invalid password, logout) · `Auth/EmailVerificationTest` · `AuthorizationTest::test_unverified_users_cannot_use_the_main_system`, `test_registration_sends_verification_email_and_blocks_until_verified`, `test_force_password_reset_applies_to_every_role_including_admin`, `test_role_areas_are_protected_from_other_roles` (role redirect), `test_walkin_system_user_cannot_log_in_reset_password_or_keep_a_session` · `Auth/PasswordResetTest`, `Auth/PasswordUpdateTest`, `Auth/PasswordConfirmationTest` · `AuditLogTest::test_authentication_events_are_audited` |
| **Reservation** | สร้าง แก้ไข ยกเลิก และสถานะ | `ReservationTest::test_reservation_success`, `test_user_cannot_choose_a_slot`, `test_reservation_blocked_when_same_plate_and_province_is_active` · `UserCancelReservationTest` (11 เทสต์) · `ReservationStateMachineTest::test_allowed_transitions_follow_the_state_machine`, `test_reservation_cannot_be_hard_deleted_through_the_web` · `AuditLogTest::test_user_reservation_actions_and_deposit_payment_log_are_audited` (แก้ไขข้อมูลรถ) · `DataModelTest::test_reservation_status_is_restricted`, `test_one_active_reservation_per_plate_and_province` |
| **Reservation Time** | ห้ามเวลาในอดีต · ห้ามจองเกิน 1 วัน · Expire หลัง 1 ชั่วโมง | `ReservationTest::test_reservation_blocked_when_start_time_in_past`, `test_reservation_blocked_when_start_time_more_than_one_day_ahead` · `ExpireReservationsTest::test_check_in_window_is_one_hour`, `test_reservation_is_not_expired_at_exactly_one_hour`, `test_reservation_expires_one_second_after_one_hour`, `test_scheduler_runs_expire_command_every_minute` (+10 เทสต์) |
| **Deposit** | `hourly_rate × 1`, สร้าง Payment, Mark as Paid, ยืนยัน Reservation และ Lock Slot | `ReservationTest::test_reservation_creates_unpaid_one_hour_deposit_and_holds_no_slot` · `ReservationStateMachineTest::test_deposit_equals_one_hour_of_lot_rate` · `DepositPaymentTest::test_admin_mark_paid_confirms_reservation_and_locks_slot`, `test_deposit_cannot_be_marked_paid_twice`, `test_mark_paid_when_lot_is_full_cancels_reservation_and_voids_deposit` · `SlotReservationLifecycleTest::test_deposit_mark_paid_sets_slot_to_reserved` |
| **Cancel Deposit** | ยกเลิกแล้วไม่คืน Deposit ที่ชำระแล้ว · ที่ยังไม่ชำระเป็น `void` | `UserCancelReservationTest::test_cancel_before_payment_voids_deposit`, `test_cancel_after_payment_does_not_refund_deposit` · `ReservationStateMachineTest::test_admin_can_cancel_pending_reservation_and_voids_unpaid_deposit`, `test_admin_cancel_confirmed_releases_slot_and_keeps_paid_deposit` · `ExpireReservationsTest::test_pending_expiry_voids_unpaid_deposit` |
| **Slot Assignment** | ระบบเลือก Slot อัตโนมัติและไม่ชนกัน | `SlotAllocationTest::test_system_allocates_an_available_slot_skipping_reserved_and_occupied`, `test_each_confirmed_reservation_gets_a_distinct_slot`, `test_slot_is_never_given_to_a_second_reservation`, `test_available_slot_is_locked_with_skip_locked`, `test_slot_sent_by_user_is_ignored_and_system_allocates` · `DataModelTest::test_slot_number_is_unique_within_a_lot` |
| **Slot Lock** | Lock Slot และคืน Slot เมื่อ Cancel / Expire | `SlotAllocationTest::test_cancel_and_expire_release_the_locked_slot`, `test_slot_lifecycle_reserved_occupied_available` · `SlotReservationLifecycleTest` (6 เทสต์) · `ExpireReservationsTest::test_confirmed_expiry_releases_locked_slot_and_keeps_paid_deposit`, `test_occupied_slot_is_never_released` |
| **Auto Check-in** | Reservation ที่ตรงเงื่อนไขต้อง Check-in อัตโนมัติ | `AutoCheckInTest::test_matching_confirmed_booking_is_checked_in_automatically`, `test_early_arrival_is_not_checked_in_and_staff_is_asked_to_check_in_manually`, `test_booking_past_grace_period_becomes_walk_in` · `CheckInServiceTest` (10 เทสต์) · `ReservationStateMachineTest::test_reservation_is_not_checkable_before_reserve_start` · **E2E** `reservation-flow.test.js` |
| **AI Accuracy** | `> 85%` ผ่าน · `<= 85%` แจ้งเตือนและไม่ Auto Check-in | `AiScanTest::test_accuracy_threshold_is_strictly_greater_than_85`, `test_ai_output_is_recorded_and_passes_above_threshold`, `test_accuracy_at_threshold_blocks_auto_check_in_and_notifies_owner_and_admin`, `test_missing_accuracy_is_not_treated_as_passing` · `ScanCheckOutTest::test_low_accuracy_exit_scan_does_not_check_out` |
| **AI Matching** | ทะเบียน + จังหวัดตรง และยี่ห้อหรือสีตรงอย่างน้อย 1 | `AutoCheckInTest::test_brand_and_color_mismatch_still_checks_in_booking_and_alerts_lot_manager` (ตามที่ตัดสินใจ §10.10), `test_same_plate_with_different_province_is_a_different_car` |
| **AI No Match** | ไม่พบ Reservation → สร้าง Walk-in Reservation | `AutoCheckInTest::test_car_without_booking_is_checked_in_as_walk_in_even_when_lot_does_not_accept_bookings`, `test_booking_in_another_lot_becomes_walk_in_here`, `test_pending_booking_in_this_lot_becomes_separate_walk_in` · `CarScanFakeModeTest::test_scan_in_fake_mode_runs_the_real_pipeline_without_calling_claude` · **E2E** `walk-in-flow.test.js` |
| **AI Read Error** | อ่านทะเบียนไม่ได้ → แจ้ง Owner + Admin | `AiScanTest::test_unreadable_plate_is_logged_and_notifies_owner_and_admin`, `test_admin_lot_alerts_go_to_admins`, `test_ai_failure_shows_error_without_creating_records` · `AutoCheckInTest::test_unreadable_province_is_treated_as_unreadable_plate` |
| **Walk-in** | `Walkin User`, เวลาปัจจุบัน, Deposit 0, ไม่มี `reservation_fee`, `checked_in`, เข้าได้ทุกลานที่ว่าง · ลานเต็ม | `CheckInServiceTest::test_walk_in_creates_a_checked_in_reservation_for_the_walkin_user`, `test_walk_in_into_full_lot_records_nothing`, `test_walk_in_check_out_has_no_discount_and_does_not_notify_walkin_user` · `DataModelTest::test_walkin_system_user_exists`, `test_walk_in_reservation_has_no_deposit_and_no_discount` · `AutoCheckInTest::test_walk_in_into_full_lot_shows_lot_full_and_records_nothing` · `ScanCheckOutTest::test_walk_in_enters_and_leaves_by_scan` · **E2E** `walk-in-flow.test.js` |
| **Blacklist** | พบ Blacklist → Check-in ได้ + แจ้ง Owner / Admin | `AiScanTest::test_blacklisted_car_is_not_blocked_and_admin_and_owner_are_notified`, `test_blacklist_requires_matching_province` · `AutoCheckInTest::test_blacklisted_car_at_full_lot_is_alerted_and_logged_without_recording_the_scan` · `ScanCheckOutTest::test_blacklisted_car_is_still_checked_out_and_alerted` · `SuspiciousVehicleBlacklistTest` (8) · `AdminSuspiciousVehicleTest` (11) |
| **Checkout** | คำนวณค่าจอดและยอดสุทธิถูกต้อง | `CheckOutTest::test_fee_is_rounded_up_per_hour_with_one_hour_minimum`, `test_fee_uses_hourly_rate_at_check_in`, `test_manual_check_out_completes_the_flow` · `CheckoutPaymentTest::test_paid_deposit_and_reservation_fee_are_deducted`, `test_deductions_never_exceed_parking_fee_and_zero_total_is_settled_by_system` · `CheckoutReservationFeeTest` (6) · `ScanCheckOutTest::test_scanning_a_car_parked_in_this_lot_checks_it_out` |
| **Payment** | Deposit / Checkout ตาม Flow · Mark as Paid · สิทธิ์ Admin/Owner · กันยืนยันซ้ำ | `DepositPaymentTest` (10) · `CheckoutPaymentTest::test_admin_marks_checkout_payment_paid_once`, `test_owner_marks_checkout_payment_paid_only_for_own_lot` · `DataModelTest::test_paid_payment_requires_paid_at_and_records_confirmer` · **E2E** ทั้ง 2 Flow |
| **Notification** | ผู้รับถูกต้อง · read / unread ถูกต้อง | `NotificationPolicyTest` (6) รวม `test_notification_page_counts_all_unread_and_protects_other_users` · `ReservationNotificationsTest` (5) |
| **Owner Scope** | เห็น/แก้เฉพาะ Lot ของตน · Mark as Paid เฉพาะในขอบเขต | `AuthorizationTest::test_owner_cannot_touch_another_owners_lot_data` · `DepositPaymentTest::test_owner_cannot_mark_paid_deposit_of_another_owners_lot` · `CheckInTest::test_owner_cannot_check_in_reservation_of_another_owners_lot` · `DashboardChartDataTest` (owner scoped) · `OwnerSystemTest` (8) |
| **Admin Scope** | System Administration · Lot Ownership ไม่ปะปน | `AuthorizationTest::test_admin_cannot_manage_lots_owned_by_an_owner` · `DepositPaymentTest::test_admin_cannot_mark_paid_deposit_of_owner_lot` · `ReservationStateMachineTest::test_admin_cannot_cancel_reservation_of_owner_lot` · `AdminSystemTest` (8) · `CsvExportTest` (5) |
| **Security** | Route / ID Access ข้าม Role และข้าม Owner ถูกป้องกัน | `AuthorizationTest` (10) รวม `test_user_cannot_access_another_users_reservation`, `test_owner_application_document_is_private` · `CsvExportTest::test_cells_that_look_like_formulas_are_neutralised` · `CarScanFakeModeTest::test_fake_mode_is_off_by_default_and_never_enabled_in_production` |

## 2. §25.2 E2E / Playwright

| Flow | ไฟล์ | ขั้นตอนที่ตรวจ |
| --- | --- | --- |
| Reservation Happy Path | `e2e/reservation-flow.test.js` | User Login → เลือกลาน / เวลา / ข้อมูลรถ → Admin ยืนยันรับเงินมัดจำ → `confirmed` → รอถึงเวลาจอง → สแกนเข้า (Auto Check-in + จัดช่อง) → สแกนออก (Auto Check-out) → Payment ค่าจอดยอดสุทธิ 0 ระบบยืนยันเอง → `completed` |
| Walk-in Flow | `e2e/walk-in-flow.test.js` | สแกนรถที่ไม่มีการจอง → Walk-in + จัดช่อง → สแกนออก → Admin ยืนยันรับชำระค่าจอด → `completed` |

วิธีรัน: `npm run test:e2e` (เปิด PHP built-in server เอง, `migrate:fresh --seed` ฐานข้อมูล `smart_parking_test`, AI โหมดจำลอง `CARSCAN_FAKE=true`)

## 3. อื่น ๆ ที่มีเทสต์ (นอกตาราง §25.1)

| หัวข้อ | ไฟล์ |
| --- | --- |
| Data Model / Constraint ในฐานข้อมูล | `DataModelTest` (24) |
| Audit Log ทุก Role และระบบ | `AuditLogTest` (11) |
| Owner Application / คำร้องลาออก / รายได้ Owner | `OwnerSystemTest` (8), `AuthorizationTest::test_owner_application_document_is_private` |
| ลานเปิด/ปิดรับจอง | `LotReservationsEnabledTest` (7) |
| จัดการช่องจอด | `ParkingSlotManagementTest` (9) |
| Dashboard | `DashboardChartDataTest` (7), `AdminSystemTest::test_admin_dashboard_covers_every_lot_while_management_pages_stay_admin_only` |
| CSV Export | `CsvExportTest` (5) |
| Profile / หน้าแรก | `ProfileTest` (5), `WelcomePageTest` (1) |
| เลือกลานจากการ์ดแนะนำไปหน้าจอง | `ReservationLotPreselectTest` (3) |
| **UI Rebuild (Phase 14 / UI Phase 1–9)** — token, App Shell, หน้าแต่ละบทบาท | `UiFoundationTest` (8), `AppShellNavigationTest` (7), `AccountPagesTest` (9), `UserFlowPagesTest` (6), `ScanPagesTest` (4), `OwnerOpsPagesTest` (6), `LotSlotPagesTest` (5), `AdminPagesTest` (6) |

## 4. ส่วนที่ทดสอบด้วยมือ (ดู `docs/UAT_CHECKLIST.md`)

- Responsive UI บน Desktop / Tablet / Mobile
- การอ่านภาพจริงของ Claude Vision (เทสต์อัตโนมัติ Mock หรือใช้โหมดจำลอง)
- การส่งอีเมลจริง (เทสต์ใช้ Mail Fake)
