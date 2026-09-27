# POST-FIX AUDIT — ตรวจระบบหลังแก้ปัญหา 6 จุด

> เอกสารนี้เป็นการตรวจสอบรอบใหม่ ไม่ได้เขียนทับ `docs/PHASE_PROGRESS_SUMMARY.md` หรือ `docs/UI_PHASE_PROGRESS_SUMMARY.md` (เก็บไว้เป็นสถานะ ณ 2026-09-18)

> ### ⚠️ สถานะปัจจุบันของรายงานฉบับนี้ (อัปเดต 2026-09-21 เวลา 17:00)
>
> เนื้อหาตั้งแต่หัวข้อ 1–12 คือผลตรวจ **ก่อน** การแก้ไขรอบที่ 2 (ที่ HEAD `c90cd22`)
> หลังจากนั้นผู้ใช้สั่งให้แก้ทุกข้อ **ยกเว้นรหัสผ่าน PostgreSQL** — ดูผลการแก้และการตรวจซ้ำที่ [หัวข้อ 13](#13-ผลการแก้รอบที่-2-2026-09-21)

---

## 1. Audit Metadata

```text
Date:          2026-09-21 16:00 – 16:20 (เวลาไทย)
Branch:        main
HEAD:          c90cd22 (Merge pull request #3 from tpp72/post-ui-hardening)
               ├─ 4bb7d29 เก็บงานหลัง UI Rebuild: ความปลอดภัย ชื่อระบบ และการตั้งค่า (2026-09-20 06:35)
               └─ e59c5c6 เก็บกวาดหลัง UI Rebuild: ลบของไม่ใช้ เตรียมที่ใส่โลโก้ และเก็บรายละเอียด UI (2026-09-20 07:03)
Working Tree:  สะอาด (git status ว่าง) · ไม่มี stash · มีเฉพาะ branch main
Database:      ตรวจ/ทดสอบบน smart_parking_test (PHPUnit, E2E, browser crawl)
               ฐาน dev smart-parking-system อ่านอย่างเดียว
Baseline:      docs/PHASE_PROGRESS_SUMMARY.md + docs/UI_PHASE_PROGRESS_SUMMARY.md (HEAD 5f0c73c, 2026-09-18)
ประเภทงาน:      Audit / Verification เท่านั้น — ไม่มีการแก้ code, database, config หรือ test
```

**คำสั่งที่รันในรอบนี้ (ทั้งหมดเป็นการตรวจ ไม่เปลี่ยน source):**
`php artisan test` · `npx playwright test` · `npm run build` (เขียน `public/build` ซึ่งอยู่ใน `.gitignore`) · `php artisan view:cache` (ชี้ output ไป scratchpad) · `composer audit --locked` · `npm audit` · Playwright crawl 201 การวัด + axe-core 4.10.2

---

## 2. Executive Summary

| ระดับ | จำนวน | รายการ |
|---|:-:|---|
| 🔴 Critical | **0** | — |
| 🟠 High | **0** | — |
| 🟡 Medium | **4** | รหัส superuser ที่หลุดยังใช้ได้บนเครื่องนี้ · npm dev dependency 11 ช่องโหว่ (critical 2) · ชื่อแบรนด์ขัดกับ PRODUCT.md · axe `scrollable-region-focusable` ที่หน้ารายได้ |
| 🟢 Low | **7** | หน้า error เป็นภาษาอังกฤษ · checkbox เล็กกว่า 24px · README/TEST_COVERAGE ตัวเลขไม่ตรง · PRODUCT.md อ้างไฟล์โลโก้ที่ลบแล้ว · คอมเมนต์ใน `config/page_titles.php` ล้าสมัย · rate limit ไม่ได้บันทึกใน requirement · `public/build` เคยล้าสมัยก่อนรัน build |
| ✅ Resolved | **5 จาก 6 จุด** | Dashboard overflow · AI Scan rate limit · Scan history 403 · Dependency (composer) · ความสม่ำเสมอของชื่อแบรนด์ |

**ผลรวม:** การแก้ 6 จุดได้ผลจริง 4 จุดเต็ม, 2 จุดแก้ได้บางส่วน (A รหัสผ่าน, F ชื่อแบรนด์) · **ไม่พบ regression** ของฟังก์ชันการทำงาน · Test ผ่านทั้งหมดและเพิ่มขึ้นจาก 325 → 328

---

## 3. Six Fixed Issues

| Issue | Before (2026-09-18) | Current | Status | Evidence |
|---|---|---|:-:|---|
| **A. Database Credential** | รหัสผ่าน DB อยู่ในประวัติ git ของ repo public · แอปใช้ผู้ใช้ `postgres` (superuser) | Source ปัจจุบันไม่มีรหัสผ่าน · แอปใช้บัญชีเฉพาะ `smart_parking` (ไม่ใช่ superuser, เป็นเจ้าของ 2 ฐาน) · **แต่รหัสที่หลุดยังเปิด `postgres` superuser บนเครื่องนี้ได้** | 🟡 **Partial** | `phpunit.xml:27` ไม่มี DB_USERNAME/DB_PASSWORD แล้ว · `.env.example:34-35` ใช้ `smart_parking` และเว้นรหัสว่าง · `git grep` ในไฟล์ที่ track: ไม่พบรหัสผ่าน · ทดสอบเชื่อมต่อจริง: `smart_parking` + รหัสเก่า = ถูกปฏิเสธ, `postgres` + รหัสเก่า = **สำเร็จ** · `pg_roles`: `smart_parking` `rolsuper=false` |
| **B. Dependency Vulnerabilities** | composer: **36 advisories / 10 packages** (high 12) | composer: **0 advisories / 0 abandoned** · Laravel 12.69.2, guzzle 7.15.5, commonmark 2.10.1, psr7 2.13.1, symfony 7.4.19 | ✅ **Resolved** | `composer audit --locked --format=json` → `advisories: 0` · `npm audit --omit=dev` → 0 (runtime bundle สะอาด) |
| **C. User Dashboard Mobile Overflow** | `/user/dashboard` ล้นจอ **+108px @390**, **+123px @375** | **ไม่ล้นทั้ง 4 เงื่อนไข** | ✅ **Resolved** | วัดจริง:<br>`375px light: scrollWidth=375 clientWidth=375 overflow=0`<br>`375px dark : scrollWidth=375 clientWidth=375 overflow=0`<br>`390px light: scrollWidth=390 clientWidth=390 overflow=0`<br>`390px dark : scrollWidth=390 clientWidth=390 overflow=0`<br>โค้ดที่แก้: `dashboard-user.blade.php` เพิ่ม `min-w-0` ที่ section และ `truncate` ที่ชื่อลาน (commit `4bb7d29`) · ทั้งระบบ 201 การวัด overflow = 0 |
| **D. AI Scan Rate Limit** | ไม่มี rate limit ที่ route สแกนเลย | `throttle:30,1` (30 ครั้ง/นาที ต่อผู้ใช้) ครบทั้ง 3 role | ✅ **Resolved** | `routes/web.php:101` (user), `:162` (owner), `:192` (admin) — POST `*/scan` เท่านั้น (GET ไม่ถูกจำกัด)<br>`route:list`: `POST user/scan … throttle:30,1`, `POST owner/scan … throttle:30,1`, `POST admin/scan … throttle:30,1`<br>Test: `ScanPagesTest::test_scan_upload_is_rate_limited_per_user` — ยิง 30 ครั้งได้ 302 แล้วครั้งที่ 31 ได้ **429**<br>⚠️ หน้า 429 เป็นหน้า error มาตรฐานของ Laravel (ภาษาอังกฤษ) — ดู §4 |
| **E. Scan History 403** | ไฟล์ภาพ seed ไม่มีจริง → HTTP 403 ใน console ที่หน้าประวัติสแกน (dev DB 20/20 ไฟล์หาย) | Seeder ไม่ใส่ path ภาพแล้ว (`image_path => null`) · ผลสแกนที่มีภาพจริงคือของที่อัปโหลดเองเท่านั้น (test DB: 4 จาก 24 แถว มีภาพและไฟล์อยู่จริง) | ✅ **Resolved** | `DatabaseSeeder.php:783` `'image_path' => null` · crawl 201 การวัด: **console error / HTTP ≥400 = 0** ทั้ง `/owner/scan/history` และ `/admin/scan/history` · UI ยังมี fallback `ไม่พบไฟล์ภาพ` (`scan/history.blade.php:79`, `scan/partials/result.blade.php:125`) ไว้สำหรับไฟล์หายในอนาคต |
| **F. Brand Name** | ชื่อไม่สม่ำเสมอ: อีเมล/แท็บใช้ `Smart-Parking` จาก `APP_NAME` | **สม่ำเสมอทั้งระบบแล้ว** ผ่านแหล่งเดียว `config/brand.php` (`'name' => 'Smart Parking'`) — แถบเมนู หน้าสาธารณะ หัว/ท้ายอีเมล ชื่อแท็บ README DESIGN.md · ไม่พบคำว่า `Smart-Parking` ในโค้ด (เหลือเฉพาะ assertion กันไม่ให้กลับมา) | 🟡 **Partial** | `config/brand.php:14` · `layouts/app.blade.php:18` `{{ $pageTitle.' | Smart Parking' }}` · mail `header/footer/email.blade.php` ใช้ `config('brand.name')` · `AppShellNavigationTest:176` `assertDontSee('Smart-Parking')` · crawl: title = `"หน้าหลัก \| Smart Parking"` ฯลฯ<br>🔴 **แต่ขัดกับ requirement:** `docs/PRODUCT.md:53` ระบุว่าชื่อที่ผูกพันคือ **"Smart Parking System"** และต้องตรงกับเล่มวิทยานิพนธ์/โปสเตอร์ |

---

## 4. New Problems Found

| Issue | Location | Severity | Evidence | Status |
|---|---|:-:|---|---|
| รหัสผ่าน `postgres` (superuser) ที่หลุดใน git history **ยังใช้ได้** บนเครื่องนี้ | PostgreSQL local · ประวัติ git commit `9c93881` | **Medium** | ทดสอบเชื่อมต่อจริง: `postgres` + รหัสเก่า → สำเร็จ · `listen_addresses=*`, `ssl=off` แต่ `pg_hba.conf` **บล็อก** การต่อจาก IP อื่น (ทดสอบ 4 IP ของเครื่อง → "no pg_hba.conf entry") ความเสี่ยงจึงจำกัดที่ผู้ที่เข้าถึงเครื่องนี้ได้ | Open |
| npm **dev dependency** มีช่องโหว่ 11 รายการ (critical 2, high 6, moderate 1, low 2) | `package.json` devDependencies | **Medium** | `npm audit` (รวม dev): `{"low":2,"moderate":1,"high":6,"critical":2,"total":11}` · critical: `concurrently` → `shell-quote` · high: `vite`, `postcss`, `rollup`, `browserslist`, `nanoid`, `picomatch` · **ทุกตัวมี fix available** · `npm audit --omit=dev` = 0 → **ไม่กระทบไฟล์ที่ผู้ใช้ปลายทางโหลด** กระทบเฉพาะเครื่อง dev/CI | Open |
| ชื่อแบรนด์ในระบบ (`Smart Parking`) ไม่ตรงกับชื่อที่ผูกพันใน PRODUCT.md (`Smart Parking System`) | `config/brand.php:14` vs `docs/PRODUCT.md:53` | **Medium** | ดู §3-F · `docs/project-plan.md` บรรทัดแรกก็ใช้ "Smart Parking System" | Open — ต้องให้เจ้าของโปรเจกต์ตัดสินว่าจะแก้ชื่อในระบบ หรือแก้ requirement |
| axe `scrollable-region-focusable` (serious) — กล่องเลื่อนของตารางรายได้รายวันไม่มี `tabindex` คีย์บอร์ดเลื่อนไม่ได้ | `resources/views/owner/revenue/index.blade.php:121` (`max-h-80 overflow-y-auto`) | **Medium** | axe-core 4.10.2: `/owner/revenue` ทั้ง 390px dark และ 1280px light, nodes=1, target `.max-h-80` · **ไม่ใช่ regression**: โค้ดบรรทัดนี้มีอยู่แล้วตั้งแต่ `5f0c73c` (ยืนยันด้วย `git show 5f0c73c:…`) รอบก่อนไม่พบเพราะข้อมูลยังไม่มากพอให้กล่องเลื่อน (ตอนนี้ test DB มี 96 payment ที่ชำระแล้ว) | Open |
| หน้า error (404 / 403 / 429) เป็นภาษาอังกฤษ ขัดกับกติกา Thai-only UI | ไม่มี `resources/views/errors/` | **Low** | `curl /no-such-page` → `<title>Not Found</title>` · PRODUCT.md: "every on-screen label and status is Thai only" | Open |
| Checkbox มีพื้นที่กด 16–20px (เล็กกว่า 24px ตาม WCAG 2.2 §2.5.8) | 14 มุมมองที่ <1024px เช่น `/login` (จดจำฉัน 20×20), `/owner/apply` (16×16), `/owner/parking-slots/bulk` (16×16) | **Low** | crawl: element ที่ไม่ใช่ inline และเล็กกว่า 44px · axe ไม่จับเพราะ target-size เป็นกฎ WCAG 2.2 ที่ยังไม่เปิดโดยค่าเริ่มต้น | Open |
| ตัวเลขจำนวน test ในเอกสารไม่ตรงกับของจริง (326 vs 328) | `README.md:8` badge · `docs/TEST_COVERAGE.md:4` · `docs/PRODUCT.md:59` | **Low** | `php artisan test` = **328 passed** | Open |
| PRODUCT.md อ้างไฟล์โลโก้ที่ถูกลบไปแล้ว | `docs/PRODUCT.md:54` อ้าง `public/images/logo.png`, `logo-email.png` | **Low** | โฟลเดอร์ `public/images` ไม่มีอยู่แล้ว (ลบใน `e59c5c6`) · ตอนนี้ใช้ `config/brand.php` + `BRAND_LOGO` | Open |
| คอมเมนต์ในไฟล์ config ล้าสมัย | `config/page_titles.php:5` เขียนว่าแท็บแสดง `"ชื่อหน้า \| Smart Parking System"` | **Low** | ของจริงคือ `"ชื่อหน้า \| Smart Parking"` | Open |
| Rate limit ใหม่ยังไม่ถูกบันทึกใน requirement | `docs/project-plan.md`, `docs/PRODUCT.md` ไม่มีคำว่า throttle / 30 ครั้ง | **Low** | grep ไม่พบ · เป็นมาตรการเสริมที่ไม่ขัด requirement แต่ควรบันทึกไว้ | Open |
| `public/build` (gitignored) ล้าสมัยกว่าซอร์ส ก่อนรัน build ในรอบนี้ | `public/build/manifest.json` (06:53) vs `resources/css/*` (07:06) ของวันที่ 2026-09-20 | **Low** | ตรวจโดย build ไป scratchpad แล้วเทียบ hash → ไม่ตรง (CSS `app-ClwOA2ak.css` vs `app-DyZR5EkP.css`) · หลังรัน `npm run build` ตรงกันแล้ว · หมายความว่าถ้าเปิดเว็บบนเครื่องนี้ก่อนหน้านี้โดยไม่ build จะเห็น CSS เวอร์ชันก่อนการแก้ | Resolved ระหว่าง audit (ผลของ `npm run build` ตาม §7 ของโจทย์) |

**สิ่งที่ตรวจแล้วไม่ใช่ปัญหา:**
- `<img>` ที่ `naturalWidth=0` บนหน้า `/user/scan`, `/owner/scan`, `/admin/scan` (อย่างละ 1) คือ preview ของรูปที่ผู้ใช้เลือก ซึ่งถูกซ่อนด้วย `x-show="preview" x-cloak` และยังไม่มี `src` — ไม่ใช่ภาพเสียที่ผู้ใช้เห็น (`scan/index.blade.php:63`)
- คำว่า `pending` ที่ crawl จับได้ในหน้า `/admin/owner-applications` มาจาก**อีเมลบัญชีเดโม** `pending.owner@demo.com` ไม่ใช่ค่าสถานะภาษาอังกฤษ

---

## 5. Regression

**ไม่พบ regression ของฟังก์ชันการทำงาน**

| ตรวจอะไร | ผล |
|---|---|
| PHPUnit | 325 → **328 passed** (เพิ่ม 3 test: tab title, scan rate limit, dark token ตรงกันทั้ง attribute และ system) · 0 failed / 0 skipped |
| E2E | 2/2 passed เท่าเดิม |
| Route | 125 routes · crawl ทุก GET route ของทุก role = **HTTP 200 ทั้งหมด ไม่มี non-200** |
| Console / JS error | **0** (รอบก่อนมี HTTP 403 ที่ 4 มุมมอง) |
| Overflow | รอบก่อน 2 จุด (user dashboard) → **0 จุดจาก 201 การวัด** |
| Component ที่ถูกลบ (`tabs`, `tab-panel`, `tooltip`, `loading-state`, `error-state`) | ไม่มีการอ้างอิงค้างในหน้าใดเลย (`x-ui.<name>` refs = 0 ทุกตัว) |
| ไฟล์ที่ถูกลบ (โลโก้เก่า, mail `default.css`) | ไม่มีการอ้างอิงค้าง · mail ใช้ theme `parking-ticket` |
| Legacy เดิม (Vehicle, vehicle_id, Role, Permission, AdminMiddleware, legacy CSS) | ยังไม่พบใน source — พบเฉพาะใน test ที่ assert ว่า**ต้องไม่มี** |
| axe | รอบก่อน 0 violations / 122 views → รอบนี้ **1 ประเภท / 120 views** แต่พิสูจน์แล้วว่าเป็นโค้ดเดิมตั้งแต่ `5f0c73c` ที่เพิ่งปรากฏเพราะปริมาณข้อมูล ไม่ใช่ผลจากการแก้ 6 จุด |

---

## 6. Test Results

| Test | Result |
|---|---|
| **PHPUnit** | ✅ **328 passed**, 1,891 assertions, 0 failed, 0 skipped — 53.02s (2026-09-21 16:03–16:04, ฐาน `smart_parking_test`) |
| **Playwright E2E** | ✅ **2 passed** — 2.4 นาที (`reservation-flow.test.js`, `walk-in-flow.test.js`) |
| **Build** (`npm run build`) | ✅ built in 1.50s (ก่อนหน้านี้ `public/build` ล้าสมัย — ดู §4) |
| **View cache** | ✅ Blade templates cached successfully — 156 ไฟล์ (เขียนลง scratchpad ไม่แตะ `storage/` ของโปรเจกต์) |
| **npm audit** | ⚠️ prod (`--omit=dev`) = **0** · รวม dev = **11** (critical 2, high 6, moderate 1, low 2) |
| **composer audit** | ✅ **0 advisories**, 0 abandoned packages |
| คำสั่ง QA อื่นใน manifest | `package.json`: `test:e2e`, `test:e2e:ui/headed/debug/report` (ตัวแปรของชุด E2E เดียวกัน) · `composer.json`: `test` = `config:clear` + `artisan test` (ครอบคลุมแล้ว) — ไม่มีคำสั่ง lint/static analysis ในโปรเจกต์ |

---

## 7. Security

| หัวข้อ | ผล | Evidence |
|---|---|---|
| Secret ใน source ปัจจุบัน | ✅ สะอาด | `git grep` ไฟล์ที่ track: ไม่พบรหัสผ่าน · `phpunit.xml` อ่านจาก env |
| Secret ในประวัติ git | 🟡 ยังอยู่ (commit `9c93881`) และรหัสนั้น**ยังใช้ล็อกอิน superuser ได้** | ทดสอบเชื่อมต่อ (ไม่เปิดเผยค่า) |
| การจำกัดสิทธิ์บัญชี DB | ✅ ดีขึ้น | `smart_parking` `rolsuper=false`, `rolcreaterole=false`, เป็น owner ของ 2 ฐานเท่านั้น |
| การเข้าถึง DB จากเครือข่าย | ✅ ถูกบล็อก | `pg_hba.conf` ปฏิเสธทุก IP ที่ทดสอบ (4 interface) แม้ `listen_addresses=*` |
| Rate limiting | ✅ มีแล้วที่ POST scan ทุก role (30/นาที) · login มี Laravel rate limiter เดิม · verification `throttle:6,1` | `route:list` |
| Upload validation | ✅ `required|file|image|mimes:jpg,jpeg,png|max:5120` + ตรวจสิทธิ์ลาน | `CarScanController:52-66` |
| File access | ✅ เอกสาร Owner อยู่ disk `local` (private) · `storage/{path}` ต้อง signed URL | ตรวจรอบก่อน ยังไม่เปลี่ยน |
| Authorization / role scope / owner scope | ✅ ผ่าน test | `AuthorizationTest`, `OwnerSystemTest`, `AdminSystemTest` ใน 328 test |
| ID enumeration | ✅ มี handler แปลง `ModelNotFoundException` → 403 บน `admin/*` | `bootstrap/app.php:31-35` |
| CSRF | ✅ ใช้ middleware group `web` มาตรฐาน | `bootstrap/app.php` |
| Mass assignment | 🟢 `$guarded = []` ครบ 13 model (technical debt เดิม ไม่มี `$fillable` เลย) | grep |
| Debug setting | ✅ `.env.example` เป็น `APP_DEBUG=false` แล้ว (เดิม true) · `.env` เครื่อง dev ยัง true ซึ่งเหมาะกับ local | `.env.example:4` |
| SSL verification (AI API) | ✅ `.env.example` = true, บังคับผ่าน config; เครื่อง dev ตั้ง false | `config/carscan.php:36` |
| Logging | ✅ Audit log ครอบทุก role + system | ตรวจรอบก่อน ยังไม่เปลี่ยน |

---

## 8. UI

### Responsive (201 การวัด)

| ความกว้าง | ขอบเขตที่ตรวจ | overflow | table overflow | ผล |
|---|---|:-:|:-:|---|
| 375px | หน้าหลัก 16 หน้า + user dashboard (light/dark) | 0 | 0 | ✅ |
| 390px | **ทุกหน้าของทุก role** (60 หน้า, dark) + user dashboard (light) | 0 | 0 | ✅ |
| 768px | หน้าหลัก 16 หน้า (dark) | 0 | 0 | ✅ |
| 1024px | หน้าหลัก 16 หน้า (light) | 0 | 0 | ✅ |
| 1280px | **ทุกหน้าของทุก role** (60 หน้า, light) | 0 | 0 | ✅ |
| 1536px | หน้าหลัก 16 หน้า (dark) | 0 | 0 | ✅ |
| 1920px | หน้าหลัก 16 หน้า (light) | 0 | 0 | ✅ |

หน้าที่ครอบคลุม: guest (welcome, login, register, forgot, marketplace) · user (dashboard, จอง, รายการจอง + แท็บ, แก้ไขการจอง, ประวัติจอด, สแกน, แจ้งเตือน, โปรไฟล์, สมัคร owner) · owner (dashboard, การจอง, ชำระเงิน, ประวัติจอด, log การจอง, รายได้, ลาน+สร้าง+แก้ไข, ช่อง+สร้าง+bulk+แก้ไข, สแกน, ประวัติสแกน, คำขอ) · admin (dashboard, การจอง, ชำระเงิน, ประวัติจอด, log การจอง, audit log, export, ลาน+สร้าง+แก้ไข, ช่อง+สร้าง+bulk+แก้ไข, สแกน, ประวัติสแกน, ผู้ใช้+สร้าง+แก้ไข, บัญชีดำ+สร้าง+แก้ไข, คำขอ owner+รายละเอียด, คำร้องลาออก)

**ยังไม่ได้ทดสอบ:** สถานะที่ต้องโต้ตอบ (modal/drawer/dropdown เปิด, toast, ฟอร์มที่มี validation error), อุปกรณ์จริง, Safari/Firefox

### Accessibility

| รายการ | ผล |
|---|---|
| axe-core WCAG 2 A/AA | **1 ประเภท / 120 views**: `scrollable-region-focusable` (serious) ที่ `/owner/revenue` เท่านั้น — ที่เหลือ 0 |
| h1 | ✅ ทุกหน้ามี h1 พอดี 1 ตัว (201/201) |
| `lang="th"` | ✅ ทุกหน้า |
| Touch target | 🟡 checkbox 16–20px ใน 14 มุมมอง (ดู §4) · ส่วนเมนู/ปุ่มอื่นผ่าน 44px แล้ว (sidebar แก้เป็น `min-h-touch` ใน `4bb7d29`) |
| Interaction a11y (modal/drawer/dropdown) | **ยังไม่ทดสอบ** — axe ตรวจเฉพาะตอนโหลดหน้า ส่วน focus trap/Escape ยืนยันได้จากโค้ด `resources/js/ui/components.js` เท่านั้น |

### Theme

| รายการ | ผล | Evidence |
|---|:-:|---|
| Light | ✅ | 60 หน้า @1280 light, axe 0 |
| Dark | ✅ | 60 หน้า @390 dark, axe 0 |
| System (ตาม OS) | ✅ | `colorScheme: dark/light` → `data-theme` ตรงกัน |
| **ปิด JavaScript** | ✅ **แก้แล้ว** | `javaScriptEnabled: false` + OS dark → `body background = rgb(15,17,19)` (พื้นมืด) — เดิมเป็น light เสมอ · `tokens.css:92` `@media (prefers-color-scheme: dark)` + test `UiFoundationTest::test_dark_tokens_are_identical_for_attribute_and_system_preference` |
| Cross-tab | ✅ | เปลี่ยนใน tab A → tab B เป็น dark ตาม |
| Reduced motion | ✅ ปรับดีขึ้น | `app.css:109-115` ตัดเฉพาะการเคลื่อนที่ แต่ยังคง transition ของสี/เงา/ความทึบไว้ที่ `--duration-fast` |

### Console / รูปภาพ / ภาษา

- console error และ HTTP ≥400: **0** จาก 201 การวัด (เดิมมี 403 ที่หน้าประวัติสแกน)
- broken image ที่ผู้ใช้มองเห็น: **0** (ที่ตรวจพบ 6 รายการเป็น preview ที่ซ่อนอยู่ ดู §4)
- ค่าสถานะภาษาอังกฤษรั่วใน UI: **0** (ที่ตรวจพบเป็นอีเมลบัญชีเดโม)
- ชื่อแท็บ: เป็นภาษาไทย + ชื่อระบบ เช่น `"หน้าหลัก | Smart Parking"` (บั๊กชื่อหน้าที่ไม่เคยแสดงถูกแก้แล้วใน `4bb7d29` — config key มีจุด)

---

## 9. Requirement Alignment

เทียบกับ `docs/PRODUCT.md` และ `docs/project-plan.md` ฉบับปัจจุบัน

### ✅ Match

Core business rules ทั้งหมดที่เคยตรวจยังตรงและมี test คุ้มครอง (328 passed): Plate-based · Deposit = `hourly_rate × 1` เป็น Payment จริง · `reservation_fee` เป็นส่วนลดแยก · Walk-in = Reservation ของ `Walkin User` (deposit/fee = 0, `checked_in`) · Accuracy > 85% · Matching ทะเบียน+จังหวัด AND (ยี่ห้อ OR สี) · Lot scope · ลานเต็มไม่บันทึกอะไรยกเว้น blacklist · Expire เกิน 60 นาที · Checkout หักมัดจำแล้วหักส่วนลด ไม่ติดลบ · Payment unpaid/paid/void · Blacklist แจ้งเตือนอย่างเดียว · Notification ตาม §15.4 · Audit ทุก role + system · CSV admin-only · Owner resignation · Hybrid Navigation · Thai-only ใน UI หลัก · Slot มีแค่ ว่าง/จอง/ใช้งาน · ไม่มีปุ่มจ่ายเงินฝั่งผู้ใช้

### 🟡 Partial

| Requirement | สถานะปัจจุบัน |
|---|---|
| §17.3 User Dashboard ควรแสดง Notification | ยังเข้าถึงผ่านแถบนำทาง/แท็บล่าง ไม่มีส่วนแสดงบน dashboard (เหมือนรอบก่อน) |
| Thai-only UI | หน้าเนื้อหาเป็นไทยครบ แต่หน้า error ของระบบ (404/403/429) ยังเป็นภาษาอังกฤษ |

### 🔴 Mismatch

| Requirement | Current behavior | Gap |
|---|---|---|
| `docs/PRODUCT.md:53` — ชื่อผลิตภัณฑ์ที่ผูกพันคือ **"Smart Parking System"** ต้องตรงกับวิทยานิพนธ์และโปสเตอร์ | ระบบใช้ **"Smart Parking"** ทุกที่ (แถบเมนู, ชื่อแท็บ, อีเมล, README, DESIGN.md) | ต่างกันที่คำว่า "System" — ต้องเลือกว่าจะแก้โค้ด (`config/brand.php`) หรือแก้ requirement ให้ตรงกับชื่อที่ใช้จริง **ห้ามแก้ requirement เพื่อให้ตรงกับ code โดยไม่มีการตัดสินใจของเจ้าของโปรเจกต์** |

### ⏳ Future / Out of Scope

Marketplace + GPS (§3.2 — หน้ามีอยู่และใช้ระบบดีไซน์ใหม่ แต่ไม่มีข้อมูลตำแหน่ง) · กล้องจริง · Barrier gate · Payment gateway / slip verification · Native mobile app

---

## 10. Remaining Issues

### 🔴 Critical
ไม่มี

### 🟠 High
ไม่มี

### 🟡 Medium
1. **รหัส superuser ที่หลุดยังใช้ได้บนเครื่องนี้** — `postgres` + รหัสเก่า เชื่อมต่อสำเร็จ (จำกัดที่เครื่องนี้เพราะ `pg_hba.conf` บล็อกเครือข่าย)
2. **npm dev dependencies 11 ช่องโหว่** (critical 2: `concurrently`/`shell-quote`) — ไม่กระทบไฟล์ที่ผู้ใช้โหลด แต่กระทบเครื่อง dev และ CI · มี fix available ทุกตัว
3. **ชื่อแบรนด์ขัดกับ PRODUCT.md** — "Smart Parking" vs "Smart Parking System"
4. **axe `scrollable-region-focusable`** ที่ `owner/revenue/index.blade.php:121` — คีย์บอร์ดเลื่อนตารางรายได้ไม่ได้ (ปัญหาเดิม เพิ่งปรากฏเมื่อข้อมูลมากพอ)

### 🟢 Low
5. หน้า error 404/403/429 เป็นภาษาอังกฤษ (ไม่มี `resources/views/errors/`)
6. Checkbox 16–20px เล็กกว่า 24px ตาม WCAG 2.2 §2.5.8 (14 มุมมอง)
7. README badge + `docs/TEST_COVERAGE.md` + `docs/PRODUCT.md` ระบุ 326 tests แต่จริง 328
8. `docs/PRODUCT.md:54` อ้างไฟล์โลโก้ที่ลบไปแล้ว (ควรอ้าง `config/brand.php` + `BRAND_LOGO`)
9. `config/page_titles.php:5` คอมเมนต์ยังเขียนชื่อแท็บแบบเก่า
10. Rate limit 30/นาที ยังไม่ถูกบันทึกใน `project-plan.md` / `PRODUCT.md`
11. `$guarded = []` ทั้ง 13 model (technical debt เดิม)

### ข้อจำกัดของการตรวจรอบนี้
- axe ตรวจเฉพาะสถานะตอนโหลดหน้า **ยังไม่ได้ทดสอบ** modal/drawer/dropdown ที่เปิดอยู่, toast, ฟอร์มที่มี error
- ทดสอบบน Chromium เท่านั้น ไม่ได้ทดสอบ Safari/Firefox หรืออุปกรณ์จริง
- 375/768/1024/1536/1920 ตรวจเฉพาะหน้าหลัก 16 หน้า (390 และ 1280 ตรวจครบทุกหน้า)
- ไม่ได้ทดสอบ concurrency จริง (แย่ง slot พร้อมกัน) — มีเพียง lock ในโค้ดและ test แบบ sequential
- ข้อมูลที่ใช้ทดสอบคือ seed + ผลจาก E2E บนฐาน `smart_parking_test` ผลบางอย่าง (เช่น กล่องเลื่อนในหน้ารายได้) ขึ้นกับปริมาณข้อมูล

---

## 11. Recommended Next Step

เรียงจากหลักฐาน ไม่ใช่การ implement

1. **เปลี่ยนรหัสผ่านผู้ใช้ `postgres` บนเครื่องนี้** — เป็นงานเดียวที่ยังเปิดช่องให้ credential ที่อยู่ใน repo public ใช้งานได้จริง (แก้ที่ PostgreSQL ไม่ต้องแตะโค้ด)
2. **ตัดสินใจเรื่องชื่อแบรนด์** — ใช้ "Smart Parking System" ให้ตรง PRODUCT.md/วิทยานิพนธ์ หรืออัปเดต PRODUCT.md ให้ตรงกับ "Smart Parking" ที่ใช้จริง แล้วค่อยแก้ที่ `config/brand.php` จุดเดียว
3. **`npm audit fix`** สำหรับ dev dependencies (critical 2 รายการ) — ทุกตัวมี fix available และไม่กระทบ bundle ที่ส่งให้ผู้ใช้

งานรองลงมา: เพิ่ม `tabindex="0"` + `role`/`aria-label` ให้กล่องเลื่อนในหน้ารายได้ · หน้า error ภาษาไทย · อัปเดตตัวเลข/ข้อความในเอกสาร 4 จุด

---

## 12. Final Snapshot

```text
Critical:      0
High:          0
Medium:        4  (postgres password ยังไม่เปลี่ยน · npm dev deps 11 ช่องโหว่ · ชื่อแบรนด์ขัด PRODUCT.md · axe scrollable-region ที่หน้ารายได้)
Low:           7  (error page ภาษาอังกฤษ · checkbox <24px · ตัวเลข test ในเอกสาร · PRODUCT.md อ้างโลโก้ที่ลบแล้ว · คอมเมนต์ config · rate limit ไม่ได้บันทึกใน requirement · guarded=[])

PHPUnit:       ✅ 328 passed / 1,891 assertions / 0 failed / 0 skipped (53.02s)
E2E:           ✅ 2/2 passed (2.4 นาที)
Build:         ✅ สำเร็จ (public/build เคยล้าสมัย → rebuild แล้วตรงกับ source)
View Cache:    ✅ สำเร็จ 156 ไฟล์

Accessibility: 🟡 axe 1 ประเภท / 120 views (owner/revenue, ปัญหาเดิมไม่ใช่ regression) · h1 และ lang ครบ · interaction a11y ยังไม่ทดสอบ
Responsive:    ✅ 0 overflow จาก 201 การวัด (375–1920px) — user dashboard หายขาดแล้ว
Security:      🟡 source สะอาด, สิทธิ์ DB แคบลง, มี rate limit แล้ว · ค้างเรื่องรหัส superuser และ dev dependencies

Requirement:   ✅ Core business rules ตรงทั้งหมด · 🟡 Partial 2 (Notification บน dashboard, error page ภาษาอังกฤษ) · 🔴 Mismatch 1 (ชื่อแบรนด์)
Overall:       การแก้ 6 จุดได้ผลจริง 4 จุดเต็ม + 2 จุดบางส่วน · ไม่พบ regression · ยังไม่ควรสรุปว่าระบบพร้อม 100%
               เพราะการทดสอบ interaction, cross-browser และ concurrency ยังไม่ได้ทำ
```

---

## 13. ผลการแก้รอบที่ 2 (2026-09-21)

ผู้ใช้สั่ง: *"แก้ทั้งหมดยกเว้นรหัสผ่าน Postgres"* · การตัดสินใจที่ถาม: คงชื่อ **"Smart Parking"** แล้วแก้เอกสารให้ตรง · **ข้าม** การเปลี่ยน `$guarded = []` เป็น `$fillable`

### 13.1 สิ่งที่แก้

| # | ปัญหาเดิม | สิ่งที่ทำ | ไฟล์ |
|:-:|---|---|---|
| 1 | npm dev dependencies 11 ช่องโหว่ (critical 2) | `npm audit fix` → **0 ช่องโหว่** · อัปเดตในกรอบ semver เดิม ไม่แตะ `package.json` (vite 7.3.6, postcss 8.5.28, rollup 4.63.4, concurrently 9.2.4, shell-quote 1.9.0, nanoid 3.3.19, picomatch 2.3.2, esbuild 0.28.2) | `package-lock.json` |
| 2 | axe `scrollable-region-focusable` (serious) ที่ตารางรายได้รายวัน | เพิ่ม `tabindex="0"` + `role="region"` + `aria-labelledby="byday-title"` + focus ring | `resources/views/owner/revenue/index.blade.php:121` |
| 3 | หน้า error เป็นภาษาอังกฤษ (ขัดกติกา Thai-only) | สร้างหน้าแจ้งข้อผิดพลาดภาษาไทย 7 หน้า (401, 403, 404, 419, 429, 500, 503) ใช้โครงเดียวกับหน้าก่อนเข้าระบบ มีแบรนด์ ปุ่มกลับหน้าแรก สลับธีมได้ และไม่พึ่ง session | `resources/views/errors/*` (ใหม่ 8 ไฟล์ รวม layout) |
| 4 | Checkbox/Radio เล็กกว่า 24px | **ตรวจพบว่าไม่ใช่ข้อบกพร่องจริง** — ทุกตัวอยู่ใน `<label>` ที่กดได้ทั้งแถวและสูง ≥44px (`min-h-touch`) พื้นที่กดจริงจึงผ่าน WCAG 2.5.8 อยู่แล้ว ตัวเลขที่ crawl รายงานคือขนาดของ `<input>` เท่านั้น · ปรับ radio จาก 16px เป็น 20px ให้เท่ากับ checkbox เพื่อความสม่ำเสมอ | 5 ไฟล์ (suspicious-vehicles/form, users/create, users/edit, owner/application/form, slots/bulk) |
| 5 | ชื่อแบรนด์ขัดกับ PRODUCT.md | คง "Smart Parking" ในระบบ · เขียน Brand Commitments ใหม่: ชื่อในผลิตภัณฑ์ = "Smart Parking" มาจาก `config/brand.php` จุดเดียว, ชื่อโครงงานเชิงวิชาการยังเป็น "ระบบการจองและจัดการลานจอดรถอัจฉริยะ (Smart Parking System)", และแก้ข้อความโลโก้ให้ชี้ไป `BRAND_LOGO` แทนไฟล์ที่ลบไปแล้ว | `docs/PRODUCT.md:52-56` |
| 6 | คอมเมนต์ config ล้าสมัย | แก้เป็น `"ชื่อหน้า \| Smart Parking"` | `config/page_titles.php:5` |
| 7 | ตัวเลข test ในเอกสารไม่ตรง (326) | อัปเดตเป็น **330** ทั้ง 3 แห่ง | `README.md:8,241` · `docs/TEST_COVERAGE.md:4` · `docs/PRODUCT.md:61` |
| 8 | Rate limit ไม่ได้บันทึกใน requirement | บันทึกใน §19.3 ของแผน (30 ครั้ง/นาที ต่อผู้ใช้ ทุก Role → HTTP 429) และเพิ่มบรรทัด "Scan upload limit" ใน Operating Context | `docs/project-plan.md` · `docs/PRODUCT.md:38` |
| 9 | `public/build` ล้าสมัย | rebuild แล้วตรงกับซอร์สปัจจุบัน | `public/build` (gitignored) |
| + | — | เพิ่มเทสต์คุมหน้า error ภาษาไทย 2 ตัว (`test_error_pages_are_thai`, `test_missing_page_renders_the_thai_404`) | `tests/Feature/UiFoundationTest.php` |

### 13.2 สิ่งที่ยังไม่ได้แก้ (ตามที่ตกลง)

| ปัญหา | สถานะ | เหตุผล |
|---|---|---|
| รหัสผ่าน `postgres` (superuser) ที่หลุดใน git history ยังใช้ได้บนเครื่องนี้ | 🟡 Open | ผู้ใช้สั่งให้ข้าม · เป็นงานที่ทำที่ PostgreSQL ไม่ใช่ที่โค้ด |
| `$guarded = []` ทั้ง 13 model | 🟢 Open | ผู้ใช้เลือก "ข้ามไปก่อน" · ข้อมูลที่เขียนผ่าน controller ถูก validate แล้ว |
| §17.3 User Dashboard ไม่มีส่วน Notification | 🟢 Open | ยังต้องตีความ requirement (เข้าถึงผ่านแถบนำทาง/แท็บล่างอยู่แล้ว) |

### 13.3 การตรวจซ้ำหลังแก้

| รายการ | ก่อนแก้ | หลังแก้ |
|---|---|---|
| PHPUnit | 328 passed | ✅ **330 passed** / 1,929 assertions / 0 failed / 0 skipped (57.05s) |
| Playwright E2E | 2 passed | ✅ **2 passed** (2.3 นาที) |
| Build (`npm run build`) | ✅ | ✅ built in 3.69s (หลังอัปเดต vite/rollup/postcss) |
| View cache | ✅ 156 ไฟล์ | ✅ **164 ไฟล์** (รวมหน้า error ใหม่) |
| `composer audit` | 0 | ✅ **0** |
| `npm audit` (รวม dev) | 11 (critical 2) | ✅ **0** |
| axe WCAG 2 A/AA | 1 ประเภท / 120 views | ✅ **0 violations / 120 views** |
| Horizontal overflow | 0 / 201 การวัด | ✅ **0 / 201 การวัด** (375–1920px) |
| HTTP non-200 ตอน crawl | 0 | ✅ **0** |
| Console / HTTP ≥400 | 0 | ✅ **0** |
| หน้า 404 บนเบราว์เซอร์ | `<title>Not Found</title>` (อังกฤษ) | ✅ `<title>ไม่พบหน้าที่ต้องการ \| Smart Parking</title>` · HTTP 404 |
| Impeccable detector (ไฟล์ที่แก้) | — | ✅ 0 findings |

### 13.4 สถานะหลังแก้

```text
Critical: 0
High:     0
Medium:   1  (รหัสผ่าน postgres — ผู้ใช้เลือกจัดการเอง)
Low:      2  (guarded=[] · Notification บน User Dashboard)

Regression จากการแก้รอบนี้: ไม่พบ — test เพิ่มจาก 328 → 330 และผ่านทั้งหมด,
E2E ผ่าน, axe ดีขึ้นจาก 1 → 0, ไม่มี console error, ทุก route ตอบ 200
ยังไม่ได้ทดสอบ: interaction a11y (modal/drawer เปิดอยู่), cross-browser, concurrency
```
