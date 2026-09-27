---
name: Smart Parking
description: One car, one parking ticket — a Thai parking operations app for users, lot owners and admins.
colors:
  thermal-page: "#E9EBE7"
  thermal-paper: "#FAFBF8"
  thermal-sunken: "#F0F1ED"
  perforation-line: "#D5D8D2"
  field-edge: "#737971"
  print-ink: "#15171A"
  print-ink-2: "#3A3F45"
  print-ink-3: "#5C636A"
  stamp-indigo: "#3438B8"
  stamp-indigo-pressed: "#2B2F9D"
  on-stamp: "#FFFFFF"
  paid-green: "#17693F"
  wait-amber: "#855600"
  void-red: "#B3261E"
  booth-page: "#0F1113"
  booth-paper: "#171A1D"
  booth-sunken: "#1E2226"
  booth-line: "#2A2F34"
  booth-field-edge: "#727980"
  booth-ink: "#ECEDEA"
  booth-ink-2: "#C4C8C3"
  booth-ink-3: "#959B97"
  booth-stamp-fill: "#4F54D9"
  booth-stamp-fill-hover: "#5E63E8"
  booth-stamp-ink: "#9A9EFF"
  booth-paid-green: "#5FCF92"
  booth-wait-amber: "#EDB453"
  booth-void-red: "#FF8175"
typography:
  display:
    fontFamily: "Anuphan Variable, Anuphan, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 700
    lineHeight: "2.375rem"
    letterSpacing: "normal"
  headline:
    fontFamily: "Anuphan Variable, Anuphan, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 700
    lineHeight: "1.875rem"
    letterSpacing: "normal"
  title:
    fontFamily: "Anuphan Variable, Anuphan, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: "1.625rem"
    letterSpacing: "normal"
  body:
    fontFamily: "Anuphan Variable, Anuphan, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.625
    letterSpacing: "normal"
  label:
    fontFamily: "Anuphan Variable, Anuphan, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 600
    lineHeight: "1.25rem"
    letterSpacing: "normal"
  caption:
    fontFamily: "Anuphan Variable, Anuphan, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: "1.125rem"
    letterSpacing: "normal"
  receipt-figure:
    fontFamily: "Martian Mono Variable, Martian Mono, ui-monospace, monospace"
    fontSize: "2rem"
    fontWeight: 600
    lineHeight: "2.5rem"
    fontFeature: "\"tnum\" 1"
rounded:
  control: "2px"
  card: "6px"
spacing:
  gutter-mobile: "16px"
  gutter-tablet: "24px"
  gutter-desktop: "32px"
  card-padding: "20px"
  card-padding-wide: "24px"
  touch: "44px"
components:
  button-primary:
    backgroundColor: "{colors.stamp-indigo}"
    textColor: "{colors.on-stamp}"
    rounded: "{rounded.card}"
    padding: "0 16px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.stamp-indigo-pressed}"
  button-secondary:
    backgroundColor: "{colors.thermal-paper}"
    textColor: "{colors.print-ink}"
    rounded: "{rounded.card}"
    padding: "0 16px"
    height: "44px"
  button-secondary-hover:
    backgroundColor: "{colors.thermal-sunken}"
  button-ghost:
    textColor: "{colors.print-ink}"
    rounded: "{rounded.card}"
    padding: "0 12px"
    height: "44px"
  button-danger:
    backgroundColor: "{colors.void-red}"
    textColor: "{colors.on-stamp}"
    rounded: "{rounded.card}"
    padding: "0 16px"
    height: "44px"
  input:
    backgroundColor: "{colors.thermal-paper}"
    textColor: "{colors.print-ink}"
    rounded: "{rounded.control}"
    padding: "0 12px"
    height: "44px"
  card:
    backgroundColor: "{colors.thermal-paper}"
    rounded: "{rounded.card}"
    padding: "{spacing.card-padding}"
  status-stamp:
    textColor: "{colors.wait-amber}"
    rounded: "{rounded.control}"
    padding: "0 8px"
    height: "28px"
  thai-plate:
    backgroundColor: "{colors.thermal-paper}"
    textColor: "{colors.print-ink}"
    rounded: "{rounded.control}"
    padding: "4px 12px"
    typography: "{typography.title}"
  nav-item-active:
    textColor: "{colors.stamp-indigo}"
    rounded: "{rounded.card}"
    padding: "0 12px"
    height: "44px"
---

# Design System: Smart Parking

## Overview

**Creative North Star: "บัตรจอดรถ (The Parking Ticket)"**

Every reservation is a paper parking ticket. It is printed when the booking is made, stamped when the deposit is paid, punched in and out at the gate, and voided or torn off when it ends. The interface is the ticket booth that handles those tickets. Light mode is cool thermal paper under daylight. Dark mode is the night booth: graphite surfaces with the same inks. The app is Thai-first, operational and dense enough for a staff member working a queue. It still reads clearly on a phone at the gate and on a projector in a thesis committee room.

The system rejects the glowing dark KPI-card dashboard it replaced. There are no gradients, glows, glass or blur. Hierarchy comes from print weight, rules, dashed perforations and one indigo rubber-stamp accent. State lives in stamps with a Thai word, a shape and an ink. Money is written as receipt lines, each with its unit and its source. Staff screens lead with what needs action now, then show the tally roll.

**Key Characteristics:**
- One accent (stamp indigo). Every other color is a print ink or a state ink.
- Thai labels everywhere. English appears only in proper product terms (Check-in, Check-out, Walk-in, AI, CSV, Log, Audit Log) and raw codes shown as secondary data.
- Square forms: 2px on controls and stamps, 6px on buttons and cards, never rounder.
- Tabular monospace numerals for money, time, slot codes and counts.
- Every number says its scope and unit ("12 คัน · รวมทุกลาน (8 ลาน)", "มัดจำ 1 · ฿40.00").
- Every interactive target is at least 44px tall, on every surface including the desktop sidebar.

## Colors

The palette is a locked ink set: thermal paper and graphite, black print, one indigo stamp, and green, amber and red state inks. Every value lives in `resources/css/tokens.css` as an `R G B` channel so Tailwind can apply opacity. Pages never introduce a color outside the tokens.

### Primary
- **Stamp Indigo** (light fill `stamp-indigo`, dark fill `booth-stamp-fill`, dark ink `booth-stamp-ink`): the only accent. Used for:
  - primary buttons
  - links and the current navigation item
  - the focus ring
  - the selected choice card
  - the "now" marker on time rails
  - the brand "P" mark

  Dark mode splits it into a deeper fill for buttons and a lighter ink for text, so both pass contrast.

### Tertiary (state inks)
- **Paid Green** (`paid-green` / `booth-paid-green`): paid, checked-in, available slots, passed AI scans.
- **Wait Amber** (`wait-amber` / `booth-wait-amber`): waiting for payment, reserved slots, low AI accuracy, work-to-do highlights (as a 10% wash).
- **Void Red** (`void-red` / `booth-void-red`): cancelled, occupied slots, blacklist hits, destructive actions, errors.

### Neutral
- **Thermal Page / Booth Page** (`thermal-page`, `booth-page`): the page behind everything.
- **Thermal Paper / Booth Paper** (`thermal-paper`, `booth-paper`): cards, panels, forms, the ticket itself.
- **Sunken** (`thermal-sunken`, `booth-sunken`): hover rows, segmented-control wells, previews, empty-state boxes.
- **Perforation Line** (`perforation-line`, `booth-line`): 1px rules and dividers. Grids use it as a 1px gap between cells.
- **Field Edge** (`field-edge`, `booth-field-edge`): input borders and dashed perforations (≥3:1 against paper).
- **Print Ink 1/2/3** (`print-ink`…`print-ink-3`, `booth-ink`…`booth-ink-3`): primary text, secondary text, captions and metadata. Ink 3 is the lightest allowed text and still passes 4.5:1 on paper.

### Named Rules
**The One Stamp Rule.** Indigo is the only accent. A second decorative color on a screen is a defect.

**The Three Signals Rule.** A state is never color alone: Thai label + shape glyph + ink, all from `App\Support\StatusCatalog`.

**The Locked Ink Rule.** No ad-hoc hex, no Tailwind palette families (`red-500`, `gray-400`). Only semantic tokens (`bg-surface`, `text-fg-2`, `border-line`, `text-danger`…). The one exception is car-color swatches, which are data.

## Typography

**Body / UI Font:** Anuphan Variable (fallback: system sans), self-hosted via `@fontsource-variable/anuphan`
**Figures Font:** Martian Mono Variable (fallback: system monospace), self-hosted via `@fontsource-variable/martian-mono`

**Character:** Anuphan is a calm, modern Thai sans that keeps tone marks and vowels legible at 12px. Martian Mono gives receipt figures a printed, tabular voice. The mono face appears only on numbers, never as a costume on words.

### Hierarchy
- **Display** (700, 1.75rem/2.375rem): the single page `h1`, exactly one per page.
- **Headline** (700, 1.375rem/1.875rem): section `h2` inside a page.
- **Title** (600, 1.125rem/1.625rem): card, ticket and form-section headings; plate numbers.
- **Body** (400, 1rem/1.625): paragraphs, inputs, list primary text.
- **Label** (600, 0.8125rem/1.25rem): field labels, nav items, secondary lines in rows, buttons at `sm` size.
- **Caption** (400, 0.75rem/1.125rem): metadata, scope notes, timestamps.
- **Receipt Figure** (Martian Mono 600, 2rem/2.5rem, tabular): KPI numbers. Smaller figures use the `.num` utility at the surrounding size.
- **Mini** (400, 0.6875rem/1rem): the province line on a plate, the count on a nav badge, a raw code shown beside its Thai label.
- **Micro** (400, 0.625rem/0.875rem): labels inside charts and bars, where nothing smaller than the bar itself fits.
- **Lead** (400, 1.125rem/1.75): the opening paragraph of a public page.
- **Hero** (700, clamp(2.5rem, 6vw, 3.25rem)/1.15): the home page headline, the one place type is allowed above Display.

### Named Rules
**The Named Size Rule.** Type sizes come from this scale only (`text-h1`…`text-micro`). An arbitrary `text-[13.5px]` means the scale is missing a step: add the step instead.

**The No Tracking Rule.** Letter-spacing is always `normal`. Tailwind `tracking-*` utilities are neutralized in the config because spacing breaks Thai clusters.

**The Figures Are Mono Rule.** Money, times, counts, slot codes and IDs use `.num` (Martian Mono + tabular + slashed zero) or `.tabular`, so columns of receipt lines align.

## Layout

- **Content width:** a single centered column. Lists and dashboards use `max-w-6xl`, detail and edit pages `max-w-3xl`, forms `max-w-2xl`.
- **Gutters:** 16px on mobile, 24px from `sm`, 32px from `lg`. Vertical page padding is 32–40px.
- **App shell:**
  - Admin and Owner have a grouped left sidebar: an icon rail (72px) from 1024px and full labels (256px) from 1280px.
  - Users get a top bar on desktop.
  - Below 1024px every role gets a 5-slot bottom bar plus a drawer. Toasts sit above the bottom bar.
- **Rhythm:**
  - Sections are separated by 40px (`mt-10`); a heading sits 16px above its content.
  - Filters sit in a paper panel right above the list they filter.
  - Lists are one bordered card with 1px dividers (a tally roll), not a grid of floating cards.
- **Responsive:** rows reflow from multi-column grids to stacked blocks. Grid children carry `min-w-0` so long Thai names truncate instead of overflowing. There is no horizontal page scroll at 390px. Only tab strips scroll sideways.
- **Staff dashboards:** a work-to-do strip first, then scoped numbers, then per-lot status, then live lists.

## Elevation & Depth

The system is almost flat and reads like paper on a counter. In light mode, cards carry a 1px border plus a hairline shadow. In dark mode, cards have no shadow; borders do the separating. Real elevation is reserved for things that float above the page: dropdowns, modals, drawers, toasts and the confirm dialog.

### Shadow Vocabulary
- **Paper** (`box-shadow: 0 1px 2px rgb(21 23 26 / 0.06)` light, `none` dark): resting cards and panels.
- **Overlay** (`box-shadow: 0 6px 16px -6px rgb(21 23 26 / 0.18), 0 1px 2px rgb(21 23 26 / 0.06)` light, `0 8px 20px -8px rgb(0 0 0 / 0.6)` dark): floating layers only.

### Named Rules
**The Flat Counter Rule.** Nothing at rest glows or lifts. No colored halos, no hover lift. Hover changes the surface tone (`bg-surface-2`) only.

**The Reduced-Motion Rule.** With `prefers-reduced-motion`, movement stops but state still reads: transforms and animations are cut, while color, border, shadow and opacity keep their 120ms transition.

**The No-Script Theme Rule.** Dark mode is set from `data-theme` before paint, and the same token values repeat under `prefers-color-scheme: dark` for `:root:not([data-theme])`, so the theme still follows the OS with JavaScript off. The two blocks must stay identical; a test compares them.

## Shapes

The shapes are those of a printed ticket: square corners, thin rules, dashed perforations.
- **Corners:** 2px (`rounded-control`) for inputs, stamps, plates, badges and segmented options; 6px (`rounded-card`) for buttons, cards, modals and choice cards. Tailwind `rounded-lg` and above are capped at 6px, and `rounded-full` appears only on status dots, color swatches, meter and occupancy bars, and the half-circle punch notches on the sample ticket.
- **Perforations:** a dashed `field-edge` rule separates the ticket stub (plate) from the body, marks previews ("จะสร้าง 20 ช่อง"), divides receipt totals, and outlines "no image / unreadable" placeholders.
- **Thai plate:** a real registration-plate silhouette, with a 2px ink border around the number and the province below.
- **Tabs:** a 2px inset underline in stamp indigo, never a pill.

## Components

### Buttons
Buttons are tactile and plain, like pressing a stamp.
- **Shape:** 6px corners, 44px minimum height at both `md` and `sm` sizes. The `sm` size only reduces padding and type.
- **Primary:** stamp-indigo fill with white label, semibold. Hover uses the pressed indigo, and pressing moves it down 1px.
- **Secondary:** paper fill, field-edge border, ink label. Hover uses the sunken tone.
- **Ghost:** no fill. Used for "clear filter", "cancel" and quiet row actions. `class="text-danger"` makes a red ghost for delete/cancel; the component drops its default ink so the red wins.
- **Danger:** void-red fill. Only for the confirming step of an irreversible action (demote owner, delete account, approve resignation).
- **Loading:** `data-loading` shows a spinner and keeps full opacity; disabled is 50% opacity with no pointer events.

### Status Stamps
- **Style:** inline stamp with a 1px border in the state ink, 2px corners, a 16px shape glyph (clock, check-box, dot, tick, cross, hourglass, slashed circle, triangle, filled/hatched square) and the Thai label. Heights are 28px, or 24px at `sm`.
- **Source:** always `<x-ui.status type="reservation|payment|slot|scan|review" :value audience="user|staff">`. Wording differs by audience; for example, pending reads "รอเจ้าหน้าที่ยืนยันรับเงิน" to users and "รอยืนยันรับมัดจำ" to staff.

### Cards / Containers
- **Corner style:** 6px.
- **Background:** paper on the page tone; sunken for nested wells.
- **Shadow strategy:** Paper shadow (see Elevation).
- **Border:** 1px perforation line; a 40% void-red border marks a danger zone section.
- **Internal padding:** 20px, or 24px from `sm`. List rows use 16px vertical and 16–20px horizontal padding.
- **Never nested:** a card inside a card becomes a sunken well or a divided row instead.

### Inputs / Fields
- **Style:** paper background, field-edge 1px border, 2px corners, 44px height, 16px text. Numeric inputs use Martian Mono.
- **Structure:** `<x-ui.field label hint required>` wraps every control and wires the label, hint and error through `aria-describedby`. The required mark is a red asterisk.
- **Focus:** the border and a 1px ring switch to stamp indigo. The global keyboard focus ring is a 2px indigo outline with a 2px offset.
- **Error / disabled:** errors use a void-red border and ring plus a message with an icon under the field. Disabled uses the sunken fill and ink 3. Date and time inputs use flatpickr showing `d/m/Y H:i`; they are never styled as read-only.
- **Choices:** radio and checkbox options are full choice cards (44px+, 6px corners). The checked card gets an indigo border and a 5% indigo wash.

### Navigation
- **Staff sidebar:**
  - Items are grouped under caption headings.
  - Each row is label type with a 20px line icon.
  - The active row gets a 10% indigo wash, indigo ink, semibold weight and `aria-current="page"`.
  - Count badges in indigo show pending work.
  - The icon rail shows a floating tooltip on hover or focus.
- **Top bar:** 56px tall with the breadcrumb (role › group › page), notification bell, theme popover and account menu.
- **Mobile:** a fixed 5-slot bottom bar (icon over caption, active item washed indigo) plus a slide-in drawer for everything else.

### Parking Ticket (signature)
One unfinished reservation is shown as a single ticket:
- **Left stub:** plate, car and booking number, split from the body by a dashed perforation.
- **Body:** lot, slot, times, deposit line, the check-in rail, and one next action.

It stacks vertically on phones and always shows the whole ticket. A collapsed stub that expands on tap was considered and rejected: an unfinished booking is exactly what the page exists to show, so it never hides behind a tap.

### Check-in Rail (signature)
A fixed-length bar covers the 60-minute check-in window, with a single indigo "now" marker that updates every 30 seconds. The phase text ("เหลือเวลาเช็คอิน 42 นาที") sits under it. Time-bound states always use this rail, never a countdown badge.

### Thai Plate (signature)
`<x-ui.plate>` shows the registration number in title type with the province under it, inside a 2px ink border with 2px corners. It comes in `sm`, `md` and `lg` sizes. It is the key that identifies a car everywhere: tickets, scans, parking logs and the blacklist.

### Occupancy Bar and Bar List
- **Occupancy Bar:** a stacked bar (occupied red, reserved amber, available green) that always carries a numeric legend ("ใช้งาน 4 · จอง 0 · ว่าง 56 · จาก 60 ช่อง").
- **Bar List:** labeled horizontal bars with the value printed at the end of each row. Current rows get an indigo wash.
- Neither uses a charting library.

### Help Popover
Long-form explanation that a confident operator does not need — gate rules, scoring thresholds — hides behind a 44px `(i)` button in the panel header and opens as a floating card (`bg-surface`, `border-line`, `shadow-overlay`) anchored under the button. The mechanism is `<details>`/`<summary>`, so it still opens with JavaScript off; Alpine only adds close-on-outside-click and Escape. Help never occupies a column of its own: the page composes as if the explanation were not there.

### Confirm Dialog
Every irreversible or money-moving form declares `data-confirm` (title, message, confirm label, tone, cancel label). A single shared alert dialog asks before submitting. The message names the car, the amount and the consequence. Inside an open modal, confirmation is a second step within that modal instead of a stacked dialog.

## Do's and Don'ts

### Do:
- **Do** use only semantic tokens (`bg-surface`, `text-fg-2`, `border-line`, `text-primary-ink`, `text-danger`), and define new values in `tokens.css` for both themes.
- **Do** render every state with `<x-ui.status>` and every car with `<x-ui.plate>`.
- **Do** give every number its unit and scope, and write money with `Format::baht()` on its own receipt line.
- **Do** keep one `h1` per page and one primary action per screen or ticket.
- **Do** keep interactive targets at least 44px tall (`min-h-touch`), including links used as buttons.
- **Do** confirm destructive or money-moving actions with `data-confirm` and say exactly what will happen.
- **Do** write Thai copy in the product's own words: ผู้ใช้ / เจ้าของลาน / ผู้ดูแลระบบ, Check-in / Check-out, ยืนยันรับเงิน, บัญชีดำ.

### Don't:
- **Don't** bring back the old dark KPI-card dashboard: no glowing cards, no gradients, glass, blur or colored halos.
- **Don't** use Tailwind palette families (`red-600`, `gray-400`), raw hex in views, or `onsubmit="return confirm()"`.
- **Don't** show raw enum values (`pending`, `void`, `checked_in`) or English role words (Owner, Admin, User) in UI copy.
- **Don't** add letter-spacing to Thai text, or use Martian Mono for words.
- **Don't** round corners beyond 6px, or use pill tabs and pill buttons.
- **Don't** convey state by color alone, or rely on hover-only tooltips for information.
- **Don't** stack a confirm dialog on top of an open modal.
