# UI PHASE PROGRESS SUMMARY — Phase 14 UI Rebuild

> **วันที่ตรวจ:** 2026-09-17 23:52 – 2026-09-18 00:10 (เวลาไทย)
> **Branch:** `main` · **HEAD:** `5f0c73c` · **UI commit:** `2c250af` "UI Rebuild Phase 1–9: ระบบดีไซน์ "บัตรจอดรถ" ทั้งแอป" (2026-09-17 19:52, 230 ไฟล์, +10,673 / −9,204, ลบ 41 ไฟล์)
> **Requirement:** `docs/PRODUCT.md` (Hybrid Navigation, Thai-only, UI status constraints) + `docs/project-plan.md`
> **Design authority:** `DESIGN.md` (repo root) · `.impeccable/design.json` และ surface brief (gitignored, อยู่ในเครื่องเท่านั้น)
> **ประเภทงาน:** Documentation / Audit เท่านั้น — ไม่มีการแก้ code
> **เอกสารคู่กัน:** `docs/PHASE_PROGRESS_SUMMARY.md`

---

## วิธีตรวจ

| หลักฐาน | รายละเอียด |
|---|---|
| Code | views 103 ไฟล์ (ไม่นับ mail), `resources/css` 3 ไฟล์, `resources/js` 7 ไฟล์, `tailwind.config.js`, `app/Support/Navigation.php`, `StatusCatalog.php` |
| Test | PHPUnit 325 passed (รวม UI page tests 54 test) · E2E 2/2 passed |
| Build | `vite build` ✅ · `view:cache` ✅ (เขียนผลไป scratchpad) |
| **UI crawl ใหม่ (รอบนี้)** | Playwright บนฐาน `smart_parking_test`:<br>• 184 การวัด ครอบคลุม 4 role (guest / user / owner / admin) และทุกหน้า GET<br>• ทุกหน้าที่ 390px ธีม dark และ 1280px ธีม light<br>• หน้าหลักที่ 375 / 768 / 1024 / 1536 / 1920px<br>• ตรวจ axe-core 4.10.2 (WCAG 2 A/AA) 122 views, overflow แนวนอน, จำนวน h1, touch target, console error, ทดสอบธีม |
| Impeccable detector | `impeccable detect --json resources/views resources/css resources/js` → 41 findings |

**ข้อจำกัดของหลักฐาน:**
- ผล QA ของ UI Phase 9 (axe crawl 110 checks) และ finish review **ไม่ได้เก็บไฟล์ไว้ใน repo** ไฟล์ใน `.impeccable/review/` มีแค่ภาพ audit ก่อน rebuild — จึงใช้ผล crawl ใหม่ของรอบนี้แทน
- axe ตรวจสถานะหน้าตอนโหลดเสร็จเท่านั้น ไม่ได้เปิด modal / drawer / dropdown ระหว่างตรวจ
- ไม่ได้ทดสอบบนเบราว์เซอร์ Safari/Firefox หรืออุปกรณ์จริง

---

## 1. UI Phase Status

| UI Phase | ชื่อ | สถานะ | สิ่งที่ทำแล้ว | สิ่งที่ยังไม่ได้ทำ | ปัญหาหลัง Phase |
|:-:|---|:-:|---|---|---|
| 0 | UI Audit + Design Direction | ✅ Complete | Audit 58 หน้าจอ · เลือก direction "บัตรจอดรถ" (seed `b04171c3`) · direction contract ใน surface brief | — | หลักฐาน audit อยู่ใน `.impeccable/` (gitignored, local เท่านั้น) |
| 1 | Foundation | ⚠️ Complete with Issues | `resources/css/tokens.css` (light/dark) · theme 3 โหมด · ฟอนต์ self-host (`@fontsource-variable/anuphan`, `martian-mono`) · 33 component `x-ui.*` · `StatusCatalog` · toast / confirm / navigation JS · ลบ `theme-dark-red.css`, `theme-light-red.css` · `UiFoundationTest` (8) | — | component 4 ตัวใช้แค่ในหน้า dev showcase · font-size นอก type ramp 8 ไฟล์ · reduced motion ตัดทุก animation เป็น 1ms · ไม่มี fallback ธีมเมื่อปิด JS (ทั้งหมด Low) |
| 2 | App Shell | ✅ Complete | `layouts/shell/*` (staff-sidebar, topbar + breadcrumb, bottom-bar, nav-drawer, account-menu, brand) · `app/Support/Navigation.php` (เมนูตาม role + badge) · ลบ `layouts/navigation.blade.php` · `AppShellNavigationTest` (6) | — | แถวเมนูใน sidebar สูง 40px (`min-h-10`) ที่โหมด icon rail 1024–1279px (Low) |
| 3 | Auth / Welcome / Profile / Notifications | ✅ Complete | หน้า auth 6 หน้า, welcome, profile, notifications ใช้ระบบใหม่ · `lang/th` · `AccountPagesTest` (9), `WelcomePageTest` (1) | — | อีเมลยังแสดงชื่อ "Smart-Parking" จาก `APP_NAME` (Medium, ดู §9) |
| 4 | User flow | ⚠️ Complete with Issues | `dashboard-user` (บัตรการจอง) · จอง / แก้ / รายการ (tabs) / ประวัติจอด · `x-ui.plate`, `x-ui.checkin-rail` · `UserFlowPagesTest` (6) | Dashboard ไม่มีส่วนแสดง Notification (§17.3 — เข้าถึงผ่านแถบนำทาง) · บัตรแบบต้นขั้วย่อบนมือถือ (raise "Miura" ใน direction contract) ไม่ได้ทำ | **Dashboard ล้นจอแนวนอน +108px ที่ 390px / +123px ที่ 375px (High)** |
| 5 | AI Scan | ⚠️ Complete with Issues | `scan/index` (จำลองกล้อง + ผลตัดสิน) · `scan/partials/result` · รวม history admin/owner เป็น `scan/history.blade.php` · `x-ui.accuracy-meter`, `x-ui.car-color` · `ScanPagesTest` (4) | — | ภาพผลสแกนจาก seed ไม่มีไฟล์ → HTTP 403 ใน console ที่หน้า history (Medium, ต้นเหตุคือข้อมูล) |
| 6 | Owner ops + Owner application | ✅ Complete | Owner dashboard (งานที่ต้องทำ → ลาน → รถ/การจอง) · revenue (`x-ui.bar-chart`, ไม่มี Chart.js) · payments/reservation-logs partials · ใบสมัคร Owner 3 หน้า · `OwnerOpsPagesTest` (6) | — | — |
| 7 | Lots & Slots | ✅ Complete | `resources/views/parking/{lots,slots}` ใช้ร่วม admin/owner · ผังช่องจอดต่อลาน · bulk create + preview · `LotSlotPagesTest` (5) | — | — |
| 8 | Admin | ✅ Complete | Admin dashboard (`x-ui.bar-list`) · users, blacklist, owner applications/resignations, audit log (`AuditCatalog`), exports · `staff/*` ใช้ร่วม admin/owner · ลบ Chart.js component · `AdminPagesTest` (6) | — | — |
| 9 | QA + Finish review + DESIGN.md | ⚠️ Complete with Issues | `DESIGN.md` ที่ root · ลบ legacy CSS/components ทั้งหมด · Marketplace restyle · mail theme `parking-ticket.css` | หลักฐาน QA/finish review ไม่ได้เก็บใน repo | crawl รอบนี้พบ overflow ใน User dashboard ซึ่ง QA ของ Phase 9 ไม่ได้จับ (หรือเกิดภายหลัง — **ยังไม่ยืนยัน**) · `vendor/mail/html/themes/default.css` ค้างแต่ไม่ถูกใช้ · โลโก้เก่า 2 ไฟล์ยังอยู่ |

**สรุป:** UI Phase 0–9 ส่งมอบครบ 10 Phase → ✅ 6 · ⚠️ 4 · 🟡 0 · ⏳ 0 · 🔴 0

---

## 2. UI Foundation

| รายการ | สถานะ | Evidence |
|---|:-:|---|
| Design tokens (semantic) | ✅ | `resources/css/tokens.css` — `--color-page/surface/surface-2/line/field/fg/fg-2/fg-3/primary/success/warning/danger`, เก็บเป็น channel RGB ใช้กับ Tailwind (`bg-surface`, `text-fg`) |
| Light theme | ✅ | `:root, :root[data-theme="light"]` · crawl 1280 light: axe 0 violations |
| Dark theme (ไม่ใช่การกลับสี) | ✅ | `:root[data-theme="dark"]` ค่าแยกของตัวเอง · crawl 390 dark: axe 0 violations |
| System theme (ตาม OS) | ✅ | `resources/js/ui/theme.js` (`matchMedia` + listener) · crawl: `colorScheme: dark/light` → `data-theme=dark/light` |
| Typography — Anuphan | ✅ | `package.json` `@fontsource-variable/anuphan` · `tailwind.config.js:37` · `app.css:1` · build มี `anuphan-thai-wght-normal-*.woff2` |
| Typography — Martian Mono | ✅ | `@fontsource-variable/martian-mono` · utility `.num` (tabular + slashed zero) `app.css:104` |
| Type ramp | 🟡 | ใช้ `text-h1/h2/h3/body/label/caption/kpi` แต่ detector พบ `text-[0.6875rem]` / `text-[0.625rem]` นอก ramp ใน 8 ไฟล์ (count-badge, plate, bar-chart, brand, scan/history, checkout-confirm, admin-actions, welcome) |
| Components | ✅ | 33 ไฟล์ใน `resources/views/components/ui` + `x-password-input` |
| StatusCatalog (label ไทย + tone + shape) | ✅ | `app/Support/StatusCatalog.php` — แยก label ตามผู้ดู (`pending`: user "รอเจ้าหน้าที่ยืนยันรับเงิน" / staff "รอยืนยันรับมัดจำ") · slot มีแค่ ว่าง/จอง/ใช้งาน (ไม่มี Disabled ตาม PRODUCT.md) · grep `{{ $x->status }}` ใน views = 0 |
| Toast | ✅ | `resources/js/ui/toast.js` · `x-ui.toast-region` (เว้นที่แถบล่างด้วย `--sp-bottom-offset`) |
| Confirm dialog (แทน `confirm()`) | ✅ | `resources/js/ui/confirm.js` · `data-confirm` 70 จุด · native `confirm(`/`alert(`/`onclick=` ใน views = 0 |
| Accessibility primitives | ✅ | focus ring `:focus-visible` (`app.css:37`) · skip link `#main-content` · focus trap/Escape/คืนโฟกัส (`resources/js/ui/components.js`) · `aria-*` 257 จุด |
| Reduced motion | 🟡 | มี `@media (prefers-reduced-motion)` 2 จุด แต่เป็นการตั้งทุก animation/transition = 1ms ทั้งหน้า (`app.css:109`) ไม่ได้ออกแบบ feedback ทดแทน |
| Loading behavior | ✅ | ลบ full-screen loader แล้ว · แถบความคืบหน้าบาง `#sp-progress` + ปุ่ม submit กำลังดำเนินการ (`resources/js/ui/navigation.js`) |
| CSS cleanup | ✅ | ไม่มี `legacy-bridge.css`, `legacyPalette`, `theme-*-red.css`, `sp-glow`, `.light-theme` (grep = 0) · Tailwind palette ดิบใน views = 0 · hex ใน Blade มีเฉพาะสีรถ (`x-ui.car-color`) ซึ่งเป็นข้อมูล |
| Dev showcase | ✅ | `/_ui` ลงทะเบียนเฉพาะ `local` (`routes/web.php:40`) |

---

## 3. App Shell

| รายการ | สถานะ | Evidence |
|---|:-:|---|
| **Hybrid Navigation ตาม PRODUCT.md** | ✅ | ตรวจจาก code (ไม่อนุมานจากชื่อ Phase) ตามรายการด้านล่าง |
| Staff sidebar แบ่งกลุ่ม | ✅ | `Navigation.php:44–68` Admin: ปฏิบัติการ / ลานจอด / การเงิน / ผู้ใช้และความปลอดภัย / รายงาน · Owner: ปฏิบัติการ / ลานจอด / การเงิน / รายงาน |
| Icon rail 1024–1279px / เต็ม ≥1280px | ✅ | `staff-sidebar.blade.php:5` `hidden lg:flex w-[4.5rem] xl:w-64` |
| Staff mobile: bottom bar + drawer | ✅ | `bottom-bar.blade.php` (`lg:hidden`) + `nav-drawer.blade.php` |
| User: top bar บน desktop + bottom tab 5 รายการบนมือถือ | ✅ | `topbar.blade.php:32` (`nav aria-label="เมนูหลัก"`) · `Navigation.php:150–157` (หน้าหลัก / จอง / การจอง / แจ้งเตือน / บัญชี) |
| ทุกหน้าเข้าถึงได้จากเมนู (รวม Export, คำร้องลาออก) | ✅ | crawl ทุก GET route ของทุก role ได้ HTTP 200 (0 non-200) · Admin มีกลุ่ม "รายงาน" |
| Role-based menu | ✅ | `Navigation::for()` แยก admin / owner / user / limited (โหมดจำกัดเมื่อ force reset หรือยังไม่ยืนยันอีเมล) |
| Theme switch | ✅ | `x-ui.theme-switch` (radiogroup 3 ตัวเลือก, ลูกศรซ้าย/ขวา, Escape) |
| Notification badge | ✅ | `Navigation::unreadNotifications()` |
| Finance badge นับทั้ง Deposit + Checkout ที่ยังไม่ชำระ | ✅ | `Navigation::unpaidPayments()` (`Navigation.php:275`) ตาม PRODUCT.md "Finance pending badge" |
| User menu / account menu | ✅ | `account-menu.blade.php` (Owner มีสถานะเจ้าของลาน + ลาออก) |
| Breadcrumb | ✅ | `topbar.blade.php:15` `nav aria-label="ตำแหน่งปัจจุบัน"` แสดง ≥1024px |
| Responsive shell | ✅ | crawl ที่ 768 / 1024 / 1536 / 1920 บนหน้าหลักของทุก role: overflow = 0 (ปัญหาที่พบอยู่ในเนื้อหา User dashboard ไม่ใช่ shell) |
| Navigation accessibility | ✅ | `aria-current="page"` · `aria-label` แยกเมนูหลัก/เมนูด่วน/ตำแหน่ง · กลุ่มมี `aria-labelledby` · crawl พบ h1 = 1 ทุกหน้า |
| Touch target ในเมนู | 🟡 | bottom bar `min-h-[3.75rem]` ✅ · sidebar rail แถว 40px (`staff-sidebar.blade.php:28`) — ใช้ที่ ≥1024px ซึ่งอาจเป็นแท็บเล็ตแนวนอน |

---

## 4. Page Rebuild Status

นับจาก page template (ไม่รวม components / partials / layouts / mail) = **44 ไฟล์**

ทุกไฟล์ใช้ layout ใหม่และ `x-ui.*` และไม่พบ marker ของ UI เก่า (sp-* class, Breeze component, palette สีดิบ, `confirm()`)

### ✅ Rebuilt (43 หน้า + dev showcase 1)

| กลุ่ม | View (resources/views/…) |
|---|---|
| Guest / Auth | `welcome` · `auth/login` · `auth/register` · `auth/forgot-password` · `auth/reset-password` · `auth/verify-email` · `auth/confirm-password` |
| ทุก Role | `notifications/index` · `profile/edit` (+ partials) |
| User | `dashboard-user` ⚠️ overflow · `user/reservations/create` · `user/reservations/edit` · `user/reservations/index` · `user/parking-logs/index` |
| สแกน (ทุก Role) | `scan/index` · `scan/history` (admin/owner) |
| Owner | `owner/dashboard` · `owner/revenue/index` · `owner/application/create` · `owner/application/edit` · `owner/application/show` |
| Staff ใช้ร่วม (admin/owner) | `staff/reservations` · `staff/payments` · `staff/parking-logs` · `staff/reservation-logs` · `parking/lots/index` · `parking/lots/form` · `parking/slots/index` · `parking/slots/form` · `parking/slots/bulk` |
| Admin | `admin/dashboard` · `admin/users/{index,create,edit}` · `admin/suspicious-vehicles/{index,create,edit}` · `admin/owner-applications/{index,show}` · `admin/owner-resignations/index` · `admin/admin-actions/index` · `admin/exports/index` |
| Future scope (restyle แล้ว) | `marketplace/index` — ใช้ระบบใหม่ แต่ยังไม่มี GPS ตาม §3.2 |
| Dev only | `dev/ui-foundation` (`/_ui`, local เท่านั้น) |

### 🟡 Partially Rebuilt

ไม่พบหน้าที่ยังผสม UI เก่า — `dashboard-user` นับเป็น rebuilt แต่มี bug ด้าน responsive (§9)

### Legacy UI

**ไม่พบ** — ไฟล์ UI เก่าถูกลบใน `2c250af` (41 ไฟล์ เช่น `layouts/navigation.blade.php`, `theme-dark-red.css`, `theme-light-red.css`, `mail/html/themes/sp-dark.css`)

### Not Started

**ไม่มี**

### Email templates

`vendor/mail/html/*` ใช้ theme `parking-ticket` (`config/mail.php:119`) · `vendor/notifications/email.blade.php` ลงชื่อ Smart Parking System — แต่ `message.blade.php` ส่วน footer ยังใช้ `config('app.name')` = "Smart-Parking"

---

## 5. Design System Usage

| ตรวจ | ผล | Evidence |
|---|:-:|---|
| Semantic token | ✅ | Tailwind palette ดิบใน views = 0 · สีทั้งหมดผ่าน token (`bg-page`, `bg-surface`, `text-fg*`, `text-primary-ink`, `border-line`) |
| Shared components | ✅ | ใช้มากสุด: `x-ui.button` 343 · `x-ui.field` 220 · `x-ui.alert` 82 · `x-ui.input` 64 · `x-ui.select` 52 · `x-ui.empty-state` 38 · `x-ui.status` 28 · `x-ui.plate` 18 |
| StatusCatalog | ✅ | อ้างอิงใน 26 ไฟล์ · ไม่พบ enum ดิบใน views (คำว่า "pending"/"admin"/"owner" ที่ crawl จับได้เป็น**อีเมลบัญชีเดโม** เช่น `pending.owner@demo.com` — false positive) |
| Toast | ✅ | `x-app-layout flash-toast` ใน 17 จาก 36 หน้า · อีก 19 หน้าไม่เปิด flash-toast แต่**ไม่พบ** controller ที่ redirect `->with('success')` ไปหน้าเหล่านั้น → ยังไม่พบข้อความหาย |
| Modal / Confirm | ✅ | `x-ui.modal` 10 · `x-ui.confirm-dialog` 3 · `data-confirm` 70 |
| Theme system | ✅ | ทุก layout (`app`, `guest`, `welcome`) include `partials/theme-init` ก่อน `@vite` |
| Typography system | 🟡 | ใช้ utility ของระบบเป็นหลัก แต่มี arbitrary font-size นอก ramp 9 จุด / 8 ไฟล์ (detector `design-system-font-size`) |
| Component ซ้ำ / ไม่ได้ใช้ | 🟡 | `x-ui.tabs`, `x-ui.tooltip`, `x-ui.loading-state`, `x-ui.error-state` ถูกใช้**เฉพาะ**ใน `dev/ui-foundation` · หน้า "การจองของฉัน" ใช้ tabs แบบลิงก์ `?tab=` แทน `x-ui.tabs` |
| Hard-coded สี | ✅ | hex ใน Blade = เฉพาะตัวอย่างสีรถ 13 สีใน `x-ui.car-color` (ข้อมูลของโดเมน) · `app.css:62,69` ฝังสีใน SVG ลูกศร select (มีคอมเมนต์เหตุผล: ปลั๊กอิน forms ใช้ CSS variable ไม่ได้) |

---

## 6. Light / Dark / System

ทดสอบจริงด้วย Playwright (2026-09-18 00:05)

| รายการ | ผล | Evidence |
|---|:-:|---|
| Light ทำงาน | ✅ | 63 หน้าที่ 1280px light → HTTP 200 ทั้งหมด, axe 0 violations |
| Dark ทำงาน | ✅ | 61 หน้าที่ 390px dark → HTTP 200 ทั้งหมด, axe 0 violations |
| System ตาม OS | ✅ | ไม่มีค่าที่จำไว้ + `colorScheme: dark` → `data-theme="dark"` · `colorScheme: light` → `data-theme="light"` |
| จำค่า (persisted) | ✅ | ตั้ง `sp-theme=light` แล้วเปิดหน้าใหม่ → `data-theme=light`, `data-theme-preference=light` |
| ไม่กระพริบธีมผิด | ✅ | `data-theme` ถูกตั้งแล้วตอน `<body>` เริ่ม parse (วัดได้ `dark` ภายใต้ system dark) — inline script ใน `<head>` ก่อน CSS |
| Cross-tab | ✅ | เปลี่ยน `localStorage` ในแท็บ A → แท็บ B เปลี่ยนตาม (dark → light) ผ่าน `storage` event (`theme.js`) |
| OS เปลี่ยนธีมระหว่างเปิดหน้า | ✅ (code) | `media.addEventListener('change')` เมื่อ preference = system — **ไม่ได้ทดสอบ runtime** |
| ปิด JavaScript | 🟡 | ไม่มี `@media (prefers-color-scheme: dark)` ใน CSS → ถ้าปิด JS จะเป็น Light เสมอ (Low) |
| `color-scheme` meta | ✅ | `<meta name="color-scheme" content="light dark">` ใน `theme-init` |

---

## 7. Responsive

ความกว้าง 390 และ 1280 ตรวจ**ทุกหน้า** ส่วนความกว้างอื่นตรวจเฉพาะ**หน้าหลัก 12 หน้า** ได้แก่:
- guest: `/`, `/login`
- user: dashboard, จอง, รายการจอง
- owner: dashboard, การจอง, ช่องจอด
- admin: dashboard, การจอง, audit log, users

| ความกว้าง | หน้าที่ตรวจ | ผ่าน | มี overflow | สถานะ |
|---|:-:|:-:|:-:|---|
| 375px | 12 | 11 | 1 | 🟡 ผ่านบางส่วน — `/user/dashboard` +123px |
| 390px | 61 | 60 | 1 | 🟡 ผ่านบางส่วน — `/user/dashboard` +108px |
| 768px | 12 | 12 | 0 | ✅ ผ่าน (เฉพาะหน้าที่ตรวจ) |
| 1024px | 12 | 12 | 0 | ✅ ผ่าน (เฉพาะหน้าที่ตรวจ) |
| 1280px | 63 | 63 | 0 | ✅ ผ่าน |
| 1536px | 12 | 12 | 0 | ✅ ผ่าน (เฉพาะหน้าที่ตรวจ) |
| 1920px | 12 | 12 | 0 | ✅ ผ่าน (เฉพาะหน้าที่ตรวจ) |

**ยังไม่ได้ทดสอบ:**
- หน้าที่ไม่อยู่ในชุดหลักที่ 375 / 768 / 1024 / 1536 / 1920
- สถานะที่ต้องโต้ตอบ เช่น modal เปิด, drawer, ผลสแกนหลังอัปโหลด, หน้ามีข้อผิดพลาด validation
- อุปกรณ์จริงและ Safari

**ต้นเหตุ overflow (วัดได้):** ใน `dashboard-user.blade.php` ส่วน `section` "ลานที่จองได้ตอนนี้" และ `ul` รายการลานกว้าง 482px ที่ viewport 390px (ทั้ง light และ dark) — **สาเหตุเชิง CSS ยังไม่ยืนยัน** ไม่ได้แก้ในงานนี้

---

## 8. Accessibility

| รายการ | สถานะ | Evidence |
|---|:-:|---|
| axe-core WCAG 2 A/AA | ✅ | **0 violations ใน 122 views** (61 URL × 390 dark และ 1280 light) — ตรวจสถานะหน้าตอนโหลดเท่านั้น |
| Contrast | ✅ | รวมอยู่ในผล axe (0) · token ใน `tokens.css` มีคอมเมนต์ค่า contrast (เช่น field ≥ 3:1) |
| focus-visible | ✅ | `app.css:36–40` focus ring เดียวทั้งระบบ |
| Keyboard | ✅ (code) | theme-switch ลูกศร/Escape · dropdown Enter/Space/ลูกศร/Escape · modal/drawer trap Tab + Escape + คืนโฟกัส (`components.js`) — **ไม่ได้ทดสอบด้วย keyboard จริงในรอบนี้** |
| ARIA | ✅ | `aria-*` 257 จุด · role: dialog 2, alertdialog 1, alert 3, status 1, tablist/tab/tabpanel, radiogroup, search 11 |
| Modal focus trap | ✅ (code) | `trapTab()` ใน `components.js` — ยังไม่ยืนยันด้วย interaction test |
| Drawer | ✅ (code) | `x-ui.drawer` ใช้กลไกเดียวกับ modal |
| Tooltip | ✅ (code) | `x-ui.tooltip` ผูก `aria-describedby` + `role="tooltip"` (แต่ใช้แค่ใน dev showcase) · sidebar rail ใช้ tip ของตัวเอง `aria-hidden` + ชื่อเมนูเป็น `sr-only` |
| Labels | ✅ | axe ไม่พบ label ขาด · `x-ui.field` ครอบ label/hint/error |
| Screen reader / lang | ✅ | `<html lang="th">` ทุก layout · skip link "ข้ามไปเนื้อหาหลัก" · h1 = 1 ทุกหน้า (184/184) |
| Reduced motion | 🟡 | มี แต่เป็นการตัดทั้งหมดเป็น 1ms (`app.css:109`) |
| Touch targets | 🟡 | ตรวจละเอียด 11 หน้าหลักที่ 390px: เหลือเฉพาะลิงก์ในบรรทัดข้อความ (เข้าข้อยกเว้น WCAG 2.5.8) เช่น "จัดการบัญชีดำ" ใน admin dashboard 15px · checkbox "จดจำฉัน" 20px · sidebar rail 40px ที่ ≥1024px |

---

## 9. UI Problems Introduced After Rebuild

| Problem | Page/File | Phase | Severity | Evidence | Status |
|---|---|:-:|:-:|---|---|
| หน้า Dashboard ของ User **เลื่อนแนวนอนได้บนมือถือ** | `resources/views/dashboard-user.blade.php` (section "ลานที่จองได้ตอนนี้" กว้าง 482px) | 4 | **High** | crawl: overflow +108px @390, +123px @375 ทั้ง light/dark · หน้าอื่นทุกหน้า = 0 | Open |
| ภาพผลสแกนเสีย (403) ในหน้าประวัติสแกน | `scan/history.blade.php` (ต้นเหตุคือข้อมูล seed ไม่มีไฟล์ภาพ) | 5 | Medium | console 403 ที่ `/owner/scan/history` (4 รูป), `/admin/scan/history` (2 รูป) · ฐาน dev 20/20 ไฟล์หาย | Open — UI แสดงข้อความสำรองหรือไม่ **ยังไม่ยืนยันด้วยภาพ** |
| ชื่อแบรนด์ในอีเมลไม่ตรง "Smart Parking System" | `vendor/mail/html/message.blade.php:5,24`, `layout.blade.php:4` ← `APP_NAME=Smart-Parking` | 3 / 9 | Medium | `.env:1`, `.env.example:1` · Brand commitment ใน PRODUCT.md | Open |
| font-size นอก type ramp | `components/ui/count-badge`, `plate`, `bar-chart`, `layouts/shell/brand`, `scan/history`, `staff/partials/checkout-confirm`, `admin/admin-actions/index`, `welcome` | 1–9 | Low | detector `design-system-font-size` 9 จุด (ไม่นับ mail) | Open |
| Component ที่มีแต่ไม่ได้ใช้ในหน้าจริง | `x-ui.tabs`, `x-ui.tooltip`, `x-ui.loading-state`, `x-ui.error-state` | 1 | Low | grep: อ้างอิงเฉพาะ `dev/ui-foundation` | Open |
| Reduced motion ตัดทุกอย่างเหลือ 1ms | `resources/css/app.css:109` | 1 | Low | code | Open |
| ไม่มีธีม dark เมื่อปิด JavaScript | `resources/css/tokens.css` (ไม่มี `prefers-color-scheme` media) | 1 | Low | code | Open |
| แถวเมนู sidebar rail สูง 40px | `layouts/shell/staff-sidebar.blade.php:28` | 2 | Low | `min-h-10` (ต่ำกว่า `min-h-touch` 44px) | Open |
| User Dashboard ไม่มีส่วน Notification | `dashboard-user.blade.php` | 4 | Low | grep ไม่พบ · requirement §17.3 | Open — ต้องตัดสินใจว่าแถบนำทางเพียงพอหรือไม่ |
| Raise "ต้นขั้วย่อบนมือถือ" ใน direction contract ไม่ได้ทำ | `user/partials/reservation-ticket.blade.php` | 4 | Low | ไม่มี state collapse/expand | Open (design contract) |
| Mail theme ของ Laravel ค้างไว้โดยไม่ใช้ | `resources/views/vendor/mail/html/themes/default.css` | 9 | Low | `config/mail.php:119` ใช้ `parking-ticket` · detector พบ side-tab/color/radius ในไฟล์นี้ | Open |
| โลโก้เก่ายังอยู่ใน repo | `public/images/logo.png`, `logo-email.png` | 9 | Low | tracked, ไม่มีการอ้างอิง | Open |
| ชื่อ pagination view ยังเป็นชื่อเก่า | `resources/views/vendor/pagination/sp.blade.php` | 1 | Low | อ้างจาก `x-ui.pagination` (ใช้งานอยู่ ไม่ใช่ dead code — แค่ชื่อ) | Open |

**ตรวจแล้วไม่พบปัญหา:**
- route/link ผิด — crawl ได้ HTTP 200 ทุก GET route ส่วน redirect ที่พบคือ `/owner/application` → `/owner/apply` สำหรับ User ที่ยังไม่มีคำขอ ซึ่งเป็นพฤติกรรมที่ตั้งใจ
- navigation หาย
- JS error / `pageerror` (0)
- Blade compile error (`view:cache` ผ่าน)
- h1 ขาดหรือซ้ำ
- enum ภาษาอังกฤษโผล่ใน UI (ที่พบเป็นอีเมลบัญชีเดโมเท่านั้น)

---

## 10. Legacy UI Debt

| รายการ | ผลตรวจ | ควรลบเมื่อไร |
|---|---|---|
| `legacy-bridge.css` | ✅ ไม่มีแล้ว | — (ลบแล้วใน UI Phase 9) |
| `legacyPalette` ใน `tailwind.config.js` | ✅ ไม่มีแล้ว | — |
| Hard-coded color (palette ดิบ) | ✅ 0 จุด | — |
| Component เก่า (Breeze / sp-*) | ✅ ไม่มี (เหลือ `x-password-input` ซึ่งเป็นของใหม่) | — |
| Navigation เก่า | ✅ ลบ `layouts/navigation.blade.php` แล้ว | — |
| `confirm()` ของเบราว์เซอร์ | ✅ 0 จุด | — |
| English status | ✅ ไม่พบใน UI | — |
| โลโก้เก่า (`public/images/logo*.png`) | ⚠️ ยังอยู่ | ไม่มี Phase กำหนด — ลบได้ใน cleanup ครั้งถัดไป |
| Obsolete CSS (`vendor/mail/html/themes/default.css`) | ⚠️ ยังอยู่ | ไม่มี Phase กำหนด — ลบได้ใน cleanup ครั้งถัดไป |
| Chart.js | ✅ ไม่มีแล้ว | — |
| Duplicated UI (admin/owner) | ✅ รวมเป็น `staff/*`, `parking/*`, `scan/history` แล้ว | — |
| ถ้อยคำเก่าใน `reservation_logs.note` (ข้อมูลในฐาน dev) | ⚠️ 70 แถว | เป็นข้อมูล ไม่ใช่ code — หายเมื่อ `migrate:fresh --seed` |

---

## 11. Verification

**จุดอ้างอิง:** HEAD `5f0c73c`, 2026-09-17 23:54 – 2026-09-18 00:08

| รายการ | ผล | UI-specific / System-wide |
|---|---|---|
| PHPUnit | ✅ 325 passed / 1,849 assertions / 0 failed / 0 skipped | System-wide · UI page tests: UiFoundation 8, AppShellNavigation 6, AccountPages 9, WelcomePage 1, UserFlowPages 6, ScanPages 4, OwnerOpsPages 6, LotSlotPages 5, AdminPages 6, ReservationLotPreselect 3 = **54** |
| Playwright E2E | ✅ 2/2 passed (2.4 นาที) | System-wide (ผ่าน UI จริง) |
| `npm run build` (vite) | ✅ 13.89s · hash ตรงกับ `public/build` ที่มีอยู่ | UI |
| `php artisan view:cache` | ✅ 163 compiled views | UI |
| Browser console | ⚠️ ไม่มี JS error · มี HTTP 403 ของภาพสแกนใน 4 views | UI (สาเหตุ: data) |
| Impeccable detector | 41 findings: `design-system-font-size` advisory 20 · `design-system-color` advisory 14 · `side-tab` warning 4 · `design-system-radius` advisory 2 · `design-system-font` warning 1 — ส่วนใหญ่อยู่ใน mail CSS (`default.css` ที่ไม่ได้ใช้ และ `parking-ticket.css`) · ใน views มี font-size นอก ramp 9 จุด | UI |
| Responsive crawl | ⚠️ 1 หน้าล้นจอ (User dashboard @375/390) | UI |
| Accessibility (axe) | ✅ 0 violations / 122 views | UI |
| Theme tests | ✅ light / dark / system / persisted / no-flash / cross-tab | UI |
| Finish review (Impeccable) | **ยังไม่ยืนยัน** — ไม่มีไฟล์ผล review ใน `.impeccable/review/` | UI |

---

## 12. Next UI Work

ในแผน UI Phase 0–9 **ไม่มี UI Phase ถัดไป** รายการด้านล่างเป็นงานค้างจากหลักฐาน ไม่ใช่ข้อเสนอ implementation

| ลำดับความสำคัญ | งาน | Dependency / Blocker |
|:-:|---|---|
| 1 | แก้ overflow ของ `dashboard-user` บนมือถือ (High) | ไม่มี blocker · ควรเพิ่มการตรวจ overflow อัตโนมัติ เพราะ PHPUnit/E2E ปัจจุบันไม่จับปัญหานี้ |
| 2 | ภาพผลสแกนจาก seed (ไฟล์หาย) | ต้องตัดสินใจว่าจะแก้ที่ seeder (ข้อมูล) หรือยืนยันว่า UI แสดงข้อความสำรองเพียงพอ — เกี่ยวกับ backend/seed ไม่ใช่ UI ล้วน |
| 3 | ชื่อแบรนด์ในอีเมล | เกี่ยวกับค่า `APP_NAME` ใน `.env`/`.env.example` (config) — ต้องได้รับอนุมัติก่อนแก้ config |
| 4 | ตัดสินใจเรื่อง Notification บน User dashboard (§17.3) | ต้องตีความ requirement |
| 5 | Low items: font ramp, component ที่ไม่ได้ใช้, reduced motion, dark fallback เมื่อไม่มี JS, sidebar 40px, mail `default.css`, โลโก้เก่า | ไม่มี blocker |
| 6 | เก็บหลักฐาน QA / finish review ไว้ใน repo หรือเอกสาร | ปัจจุบันหลักฐานอยู่นอก repo |
| Future | Marketplace + GPS | ต้องมีข้อมูลตำแหน่งลาน (ยังไม่มีใน schema) — §3.2 |

**หน้าที่ยังไม่ได้ rebuild:** ไม่มี

---

## 13. Final Snapshot

```text
Current UI Phase:      ไม่มี Phase ที่กำลังทำ — UI Phase 0–9 ส่งมอบแล้ว (commit 2c250af, merge 5f0c73c)
Foundation:            ⚠️ Complete with Issues — tokens, 3 themes, Anuphan + Martian Mono, 33 components,
                       StatusCatalog, toast/confirm; ข้อย่อย Low: font นอก ramp, 4 components ไม่ได้ใช้, reduced motion 1ms
App Shell:             ✅ Complete — Hybrid Navigation ตาม PRODUCT.md ยืนยันจาก code (sidebar กลุ่ม + rail 1024–1279
                       + bottom bar/drawer; User top bar + bottom tab 5 รายการ)
Pages Rebuilt:         43 หน้า + dev showcase 1 (ทุก page template ใช้ระบบใหม่)
Pages Remaining:       0 (Marketplace restyle แล้ว แต่ GPS เป็น Future Scope)
Legacy UI:             ไม่พบใน code UI · ค้าง: โลโก้เก่า 2 ไฟล์, mail default.css
Critical UI Issues:    ไม่มี Critical · High 1: /user/dashboard ล้นจอ +108px (390px) / +123px (375px)
Theme Status:          ✅ Light / Dark / System / persisted / no-flash / cross-tab ผ่านการทดสอบจริง
                       (ข้อจำกัด: ไม่มีธีม dark เมื่อปิด JS)
Responsive Status:     🟡 ผ่านบางส่วน — 390/1280 ทุกหน้า: overflow 1 หน้า · 375/768/1024/1536/1920 เฉพาะ 12 หน้าหลัก:
                       overflow 1 หน้า (375) · ยังไม่ทดสอบสถานะ interaction และอุปกรณ์จริง
Accessibility Status:  ✅ axe WCAG 2 A/AA 0 violations ใน 122 views · h1 ครบ · focus/keyboard/trap ยืนยันจาก code
                       (ยังไม่ทดสอบ interaction จริง) · reduced motion และ touch target บางจุดเป็น Low
Next UI Phase:         ไม่มีในแผน — งานค้างสำคัญ: แก้ overflow User dashboard, ภาพสแกน seed, ชื่อแบรนด์ในอีเมล
```
