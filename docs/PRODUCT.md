# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Smart Parking System is a web app with three roles plus one system account:

- **User (customer):** books a parking space up to one day ahead, types in their own plate, province, brand and colour, then waits for staff to confirm the deposit. Before check-in they can edit their car details or cancel. They also view their parking history, receive notifications and can apply to become an Owner.
- **Owner (lot owner):** manages only their own lots and slots. Watches reservations and history for those lots, marks deposits and parking fees as paid, does manual check-in/check-out when the auto flow cannot, follows revenue, and can submit a resignation that an Admin must approve.
- **Admin:** sees the whole system across all lots. Manages the admin-owned lots (`owner_id IS NULL`) and users, approves Owner applications and resignations, keeps the blacklist, reads the Audit Log and is the only role that can export CSV.
- **Walkin User:** a system account that anchors walk-in reservations. It cannot log in and gets no notifications.

This is an undergraduate Computer Science thesis prototype. Its real audience today is the thesis committee, seminar visitors and UAT testers, who judge it by walking through these roles with seeded demo accounts. There is no plan to deploy it to a real parking lot.

Every role uses the app on both phone and desktop equally, so every page must work well on both.

## Product Purpose

The app brings a parking lot's whole operation into one system: lots and slots, advance booking, deposits, AI plate reading, automatic check-in, walk-ins, check-out with fee calculation, blacklist alerts, notifications, logs and role-based dashboards. It replaces handwritten plate logs, manual slot checks and hand-calculated fees.

Success means the committee can follow a car end to end (booking → deposit confirmed → AI scan → auto check-in → check-out → payment) under each role. Every rule shown on screen must match `docs/project-plan.md`, the requirements source of truth.

## Positioning

Every car becomes exactly one Reservation. A booked car and a car that just drives in (walk-in) both go through the same lifecycle. The camera at the lot is simulated by uploading a photo. The AI reads the plate, province, brand, colour and accuracy, matches the car against bookings, and checks it in or creates a walk-in on its own. The system, not the user, picks the slot. The user never chooses a slot, and staff never confirm a booking by hand beyond marking the deposit paid.

## Operating Context

- **Booking flow:** create booking → pending, holds no slot → Owner/Admin marks the deposit paid → confirmed, slot allocated and locked → check-in must happen within 60 minutes of `reserve_start` or the booking expires.
- **Lot gate flow:** photo upload stands in for the camera. The AI must score above 85% accuracy to pass. A booking matches when plate and province match, plus brand or colour. A car with no usable booking becomes a walk-in. A full lot shows "ลานเต็ม". A blacklisted car raises an alert but is still let in.
- **Exit flow:** the exit scan auto-detects direction and checks the car out, or staff use the manual check-out button. The fee is `ceil(hours, min 1) × hourly_rate`. A paid deposit and the discount are deducted, and the total is never negative.
- **Payments are simulated:** there is no gateway, slip check or QR payment. Staff record received money with "Mark as Paid".
- **Scan upload limit:** uploading a photo to the AI gate is capped at 30 requests per minute per user on every role, to keep the Claude API cost bounded. Over the limit the request is rejected with HTTP 429 and the Thai "ส่งคำขอถี่เกินไป" page.
- **Finance pending badge:** navigation badges count all actionable unpaid finance items, including both unpaid Deposit payments and unpaid Checkout payments. The badge represents work requiring staff attention; the destination page must distinguish the two payment types rather than presenting only a combined number.
- **Demo and evaluation:** `php artisan migrate:fresh --seed` creates the demo accounts (`admin@demo.com`, `owner@demo.com`, `user@demo.com`, …, all with password `password`). The UAT checklist is in `docs/UAT_CHECKLIST.md`. The project also has a seminar poster (80 × 120 cm).

## Capabilities and Constraints

- **Stack:** Laravel 12 (PHP 8.4), Blade, Tailwind CSS 3, Alpine.js, Vite 7, flatpickr, PostgreSQL 18, Anthropic Claude Vision. Pages are server-rendered with no SPA.
- **Language:** every on-screen label and status is Thai only. English status values such as `pending` or `confirmed` must not leak into the UI. Technical terms already used in the plan (Check-in, Walk-in, Audit Log) may stay.
- **Navigation:** use a **Hybrid Navigation** system. Admin and Owner use a grouped sidebar on desktop (operations / lots / finance / users & security / reports), collapse to an icon rail at 1024–1279px, and use a mobile bottom bar plus drawer for smaller screens. User uses a top bar on desktop and a 5-item bottom tab on mobile (หน้าหลัก / จอง / การจอง / แจ้งเตือน / บัญชี). Every page must be reachable from navigation, including Export and owner resignations. This UI rebuild is implemented through the dedicated UI phases, not by preserving the old top-navbar structure.
- **Hard rules the UI must never contradict:** bookings at most 1 day ahead; deposit = 1 × `hourly_rate`; `reservation_fee` is a discount only, and only on bookings with a deposit; walk-ins pay no deposit and get no discount; reservations are cancelled, never deleted directly; lots have no open/closed status, only `reservations_enabled`; CSV export is Admin-only; users can edit only plate, province, brand and colour, and only before check-in.
- **UI status constraints:** Deposit is shown with its actual amount and, before staff confirmation, the user-facing state is "รอเจ้าหน้าที่ยืนยันรับเงิน". The UI does not invent a user-side payment button. Parking slots expose only the system states available in the requirement: ว่าง / จอง / ใช้งาน; do not add a `Disabled` state.
- **Out of scope:** real cameras, barrier gates, native mobile apps, payment gateways, coupons, promotions and tax reports.
- **Future:** the Marketplace (GPS-based nearby lots) is planned for later and must not block or shape the core flow.

## Brand Commitments

- The in-product brand name is **"Smart Parking"** (decided 2026-09-21). It comes from one place, `config/brand.php`, and is used by the navigation bar, public pages, browser tab titles and e-mail header/footer. Do not hard-code the name anywhere else.
- The academic project title stays **"ระบบการจองและจัดการลานจอดรถอัจฉริยะ (Smart Parking System)"** in the thesis, the poster and `docs/project-plan.md`. The shorter in-product name is deliberate, not a mismatch.
- The logo is not binding. There is no logo file in the repository: the default mark is the letter "P" drawn in HTML. Setting `BRAND_LOGO` (path under `public/`) in `.env` replaces it in the navigation bar, public pages and e-mail header at once; `BRAND_LOGO_HEIGHT` controls its height.

## Evidence on Hand

- `docs/project-plan.md`: full requirements and business rules.
- `docs/TEST_COVERAGE.md` and `docs/UAT_CHECKLIST.md`: requirement-to-test mapping and UAT steps. 330 PHPUnit tests and 2 Playwright E2E flows pass as of 2026-09-21.
- Seeded demo data: about 28 accounts with random bookings, parking history and payments.
- Seminar poster content: a conceptual framework and an ER diagram with 13 tables.
- **Absent:** there are no real lots, real customers, testimonials, usage metrics, pricing plans or partner logos. Future work must not invent any of these.

## Product Principles

1. **Every rule on screen matches the plan.** Statuses, amounts and permissions shown in the UI follow `docs/project-plan.md` exactly, because the committee checks them against it.
2. **One car, one Reservation, one clear state.** At any moment each role can tell where a car is in its lifecycle and what happens next.
3. **The system decides and people confirm.** Slot choice, matching and check-in are automatic. Human actions stay limited to marking payments, manual fallbacks and approvals.
4. **Role-scoped truth.** Each role sees and acts on exactly its own scope: own bookings, own lots, or the whole system.
5. **Demo-ready on any screen.** Every flow can be demonstrated end to end on a phone or a projector-sized desktop with seeded data.
