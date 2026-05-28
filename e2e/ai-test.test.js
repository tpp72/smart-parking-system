/**
 * AI Web Test Suite — Smart Parking System
 *
 * Crawls all reachable routes, exercises navigation/buttons/forms,
 * detects console errors, API failures, broken UI, and responsive issues.
 * Generates: bug-report.md, coverage-report.md, error-log.json
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

import { ADMIN_ROUTES, GUEST_ROUTES } from './utils/routes.js';
import { crawlRoutes, visitRoutes }   from './utils/crawler.js';
import { attachErrorMonitor }         from './utils/errorMonitor.js';
import { runUiChecks }                from './utils/uiDetector.js';
import { screenshotOnFailure, screenshotPage, screenshotError } from './utils/screenshot.js';
import {
  collectButtons,
  collectNavLinks,
  collectForms,
} from './utils/functional.js';
import {
  generateBugReport,
  generateCoverageReport,
  writeErrorLog,
} from './utils/reporter.js';

// ── Shared accumulators (module-level so afterAll can read them) ─────────────

const bugs        = [];
const allErrors   = [];
const routeResults = [];

// De-dup key set so the same (title+url) pair is never stored twice
const seenBugKeys = new Set();

// ── Helpers ──────────────────────────────────────────────────────────────────

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function addBug(entry) {
  const key = `${entry.title}|${entry.url ?? ''}`;
  if (seenBugKeys.has(key)) return;
  seenBugKeys.add(key);
  bugs.push(entry);
  console.warn(`[BUG] [${entry.severity}] ${entry.title} — ${entry.url ?? ''}`);
}

function pushErrors(pageUrl, monitor) {
  const e = monitor.getErrors();
  for (const c of e.console)      allErrors.push({ category: 'console',     pageUrl, ...c });
  for (const n of e.network)      allErrors.push({ category: 'network',     pageUrl, ...n });
  for (const x of e.exceptions)   allErrors.push({ category: 'exception',   pageUrl, ...x });
  for (const a of e.failedAssets) allErrors.push({ category: 'failedAsset', pageUrl, ...a });
}

const SKIP_CLICK   = /logout|delete|destroy|remove|deactivate|cancel/i;
const BASE_URL     = 'http://127.0.0.1:8000';

// ── Classify overflow severity ────────────────────────────────────────────────

function overflowSeverity(vpName, overflowPx) {
  if (vpName === 'mobile'  && overflowPx > 20) return 'major';
  if (vpName === 'mobile'  && overflowPx > 5)  return 'minor';
  if (vpName === 'tablet'  && overflowPx > 40) return 'minor';
  return 'low'; // hairline rounding artefacts
}

// ── Per-page test helper ─────────────────────────────────────────────────────

async function testPage(page, url, label) {
  const monitor = attachErrorMonitor(page);

  try {
    const response = await page.goto(url, {
      waitUntil: 'domcontentloaded',
      timeout: 20_000,
    });

    const status     = response?.status() ?? 0;
    const finalUrl   = page.url();
    const title      = await page.title();
    const redirected = finalUrl !== url ? finalUrl : null;

    routeResults.push({ url, route: label, status, title, error: null, redirectedTo: redirected });

    // ── HTTP error ───────────────────────────────────────────────────────────
    if (status >= 500) {
      const ss = await screenshotError(page, `${label}_${status}`);
      addBug({ title: `Server error ${status} on ${label}`, url, severity: 'critical', type: 'HTTP Error',
               description: `Route returned HTTP ${status}`, screenshot: ss });
    } else if (status >= 400) {
      const ss = await screenshotError(page, `${label}_${status}`);
      addBug({ title: `Client error ${status} on ${label}`, url, severity: 'high', type: 'HTTP Error',
               description: `Route returned HTTP ${status}`, screenshot: ss });
    }

    // Wait for JS to settle
    await page.waitForTimeout(400);

    // ── JS exceptions ────────────────────────────────────────────────────────
    const errs = monitor.getErrors();
    for (const ex of errs.exceptions) {
      const ss = await screenshotError(page, `${label}_jsexception`);
      addBug({ title: `JS exception on ${label}`, url, severity: 'critical', type: 'JS Exception',
               description: ex.message.substring(0, 200), screenshot: ss,
               error: ex.stack?.substring(0, 400) });
    }

    // ── Console errors ───────────────────────────────────────────────────────
    for (const ce of errs.console) {
      if (ce.type === 'error') {
        addBug({ title: `Console error on ${label}`, url, severity: 'major', type: 'Console Error',
                 description: ce.text.substring(0, 200) });
      }
    }

    // ── API failures ─────────────────────────────────────────────────────────
    for (const n of errs.network) {
      if (!n.url?.includes('127.0.0.1:8000')) continue;
      if (n.status >= 500) {
        addBug({ title: `API error ${n.status} on ${label}`, url, severity: 'critical',
                 type: 'API Failure', description: `${n.method ?? 'GET'} ${n.url} → ${n.status}` });
      } else if (n.status >= 400) {
        addBug({ title: `API error ${n.status} on ${label}`, url, severity: 'high',
                 type: 'API Failure', description: `${n.method ?? 'GET'} ${n.url} → ${n.status}` });
      }
    }

    // ── UI checks ────────────────────────────────────────────────────────────
    const ui = await runUiChecks(page);

    if (ui.brokenImages.length > 0) {
      const ss = await screenshotError(page, `${label}_broken_images`);
      addBug({ title: `Broken images on ${label} (${ui.brokenImages.length})`, url,
               severity: 'major', type: 'Broken Image',
               description: ui.brokenImages.map(i => i.src).join(', ').substring(0, 300),
               screenshot: ss });
    }

    for (const r of ui.responsive) {
      const sev = overflowSeverity(r.viewport, r.overflow[0]?.overflowPx ?? 0);
      if (sev === 'low') continue; // skip hairline rounding artefacts
      const ss = await screenshotError(page, `${label}_overflow_${r.viewport}`);
      addBug({
        title:       `Overflow at ${r.viewport} on ${label}`,
        url,
        severity:    sev,
        type:        'Responsive Issue',
        description: `${r.overflow.length} element(s) overflow at ${r.width}×${r.height}px. ` +
                     `First: <${r.overflow[0].tag} class="${r.overflow[0].className ?? ''}"> +${r.overflow[0].overflowPx}px`,
        screenshot:  ss,
      });
    }

    // ── Forms — inventory only ────────────────────────────────────────────────
    const forms = await collectForms(page);
    for (const form of forms) {
      const unlabeled = form.fields.filter(f => !f.name && !f.placeholder).length;
      if (unlabeled > 0) {
        addBug({ title: `${unlabeled} unlabeled field(s) in form on ${label}`, url,
                 severity: 'minor', type: 'UX Issue',
                 description: `Form (${form.method} ${form.action}) has fields with no name/placeholder` });
      }
    }

    // Capture a baseline page screenshot for passing pages
    if (status >= 200 && status < 400) {
      await screenshotPage(page, label.replace(/\//g, '_'));
    }

  } catch (err) {
    const ss = await screenshotOnFailure(page, label);
    routeResults.push({ url, route: label, status: 0, title: null, error: err.message, redirectedTo: null });
    addBug({ title: `Page load failure: ${label}`, url, severity: 'critical', type: 'Load Failure',
             description: err.message.substring(0, 300), screenshot: ss });
  } finally {
    pushErrors(url, monitor);
    monitor.clear();
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// TEST SUITE
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('AI Web Testing — Smart Parking System', () => {

  // ── 1. Crawl from dashboard ─────────────────────────────────────────────────
  test('Crawl all reachable links from admin dashboard', async ({ page }) => {
    const monitor = attachErrorMonitor(page);

    await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
    const crawled = await crawlRoutes(page, '/admin/dashboard', {
      maxPages: 60,
      maxDepth: 3,
    });

    for (const result of crawled) {
      if (result.isDownload) continue; // valid download — not a bug

      if (result.status >= 500) {
        const ss = await screenshotError(page, `crawl_${result.url.replace(/[^a-z0-9]/gi, '_').substring(0, 40)}`);
        addBug({ title: `Server error ${result.status} discovered by crawler`, url: result.url,
                 severity: 'critical', type: 'HTTP Error',
                 description: `Crawled URL returned HTTP ${result.status}`, screenshot: ss });
      }
      if (result.errors.length > 0) {
        addBug({ title: `Crawler error on ${result.url}`, url: result.url,
                 severity: 'high', type: 'Load Failure',
                 description: result.errors.join('; ').substring(0, 200) });
      }
    }

    pushErrors('/admin/dashboard (crawl)', monitor);
    console.log(`Crawled ${crawled.length} pages (${crawled.filter(r => r.isDownload).length} download endpoints)`);
  });

  // ── 2. Admin routes — full per-page check ────────────────────────────────────
  test('Test all admin routes', async ({ page }) => {
    test.setTimeout(180_000); // 21 routes × UI checks needs headroom
    for (const route of ADMIN_ROUTES) {
      await testPage(page, `${BASE_URL}${route}`, route);
    }
  });

  // ── 3. Guest routes ──────────────────────────────────────────────────────────
  test('Test guest routes (unauthenticated)', async ({ browser }) => {
    const ctx  = await browser.newContext(); // no storageState
    const page = await ctx.newPage();
    try {
      for (const route of GUEST_ROUTES) {
        await testPage(page, `${BASE_URL}${route}`, `[guest]${route}`);
      }
    } finally {
      await ctx.close();
    }
  });

  // ── 4. Coverage report (visitRoutes) ─────────────────────────────────────────
  test('Generate route coverage report', async ({ page }) => {
    const results = await visitRoutes(page, ADMIN_ROUTES);

    for (const r of results) {
      if (!routeResults.find(x => x.route === r.route)) routeResults.push(r);
      if (r.isDownload) continue; // valid
      if (r.error || (r.status && r.status >= 400)) {
        addBug({ title: `Route ${r.route} unreachable (${r.status ?? 'ERR'})`, url: r.url,
                 severity: r.status >= 500 ? 'critical' : 'high',
                 type: 'Route Coverage', description: r.error ?? `HTTP ${r.status}` });
      }
    }

    generateCoverageReport(results);
    console.log('Coverage report written');
  });

  // ── 5. Responsive sweep on key pages ─────────────────────────────────────────
  test('Responsive layout check on key pages', async ({ page }) => {
    const KEY_PAGES = [
      '/admin/dashboard',
      '/admin/parking-lots',
      '/admin/reservations',
      '/admin/parking-logs',
      '/admin/scan',
    ];

    const VIEWPORTS = [
      { name: 'mobile',  width: 375,  height: 667 },
      { name: 'tablet',  width: 768,  height: 1024 },
      { name: 'desktop', width: 1280, height: 800 },
    ];

    for (const route of KEY_PAGES) {
      await page.goto(`${BASE_URL}${route}`, { waitUntil: 'domcontentloaded' });

      for (const vp of VIEWPORTS) {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        await page.waitForTimeout(300);

        // Reuse the improved checkOverflow from uiDetector (inlined here for
        // the specific responsive test so we can screenshot only real overflows)
        const overflow = await page.evaluate(() => {
          const THRESHOLD = 10;
          function insideScroll(el) {
            let n = el.parentElement;
            while (n && n !== document.body) {
              const ox = window.getComputedStyle(n).overflowX;
              if (ox === 'auto' || ox === 'scroll') return true;
              n = n.parentElement;
            }
            return false;
          }
          const issues = [];
          for (const el of document.querySelectorAll('*')) {
            const s = window.getComputedStyle(el);
            if (s.display === 'none' || s.visibility === 'hidden') continue;
            if (insideScroll(el)) continue;
            const rect = el.getBoundingClientRect();
            if (rect.right > window.innerWidth + THRESHOLD) {
              issues.push({
                tag: el.tagName,
                cls: typeof el.className === 'string' ? el.className.split(' ').slice(0, 3).join(' ') : '',
                px:  Math.round(rect.right - window.innerWidth),
              });
            }
          }
          return issues;
        });

        if (overflow.length > 0) {
          const sev = overflowSeverity(vp.name, overflow[0].px);
          if (sev === 'low') continue;

          const ss = await screenshotError(page, `responsive_${route.replace(/\//g, '_')}_${vp.name}`);
          addBug({
            title:       `Horizontal overflow at ${vp.name} on ${route}`,
            url:         `${BASE_URL}${route}`,
            severity:    sev,
            type:        'Responsive Issue',
            description: `${overflow.length} element(s) overflow at ${vp.width}px. ` +
                         `First: <${overflow[0].tag} class="${overflow[0].cls}"> +${overflow[0].px}px`,
            screenshot:  ss,
          });
        }
      }

      // restore desktop
      await page.setViewportSize({ width: 1280, height: 800 });
    }
  });

  // ── 6. Form inventory & validation check ────────────────────────────────────
  test('Inspect forms on create pages', async ({ page }) => {
    const CREATE_PAGES = [
      '/admin/parking-lots/create',
      '/admin/parking-slots/create',
      '/admin/devices/create',
      '/admin/vehicles/create',
      '/admin/reservations/create',
    ];

    for (const route of CREATE_PAGES) {
      const url     = `${BASE_URL}${route}`;
      const monitor = attachErrorMonitor(page);

      try {
        const res    = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15_000 });
        const status = res?.status() ?? 0;
        if (status >= 400) { pushErrors(url, monitor); continue; }

        await page.waitForTimeout(500);

        const forms   = await collectForms(page);
        const buttons = await collectButtons(page);
        const hasSubmit = buttons.some(b => b.type === 'submit' || /save|create|submit|add/i.test(b.text));

        if (forms.length === 0) {
          addBug({ title: `No form found on create page ${route}`, url, severity: 'major',
                   type: 'UX Issue', description: 'Create page has no <form> element' });
        }
        if (!hasSubmit) {
          addBug({ title: `No submit button on ${route}`, url, severity: 'major',
                   type: 'UX Issue',
                   description: `Page has ${forms.length} form(s) but no visible submit button` });
        }

        console.log(`  ${route}: ${forms.length} form(s), submit=${hasSubmit}`);

      } catch (err) {
        const ss = await screenshotOnFailure(page, `form_${route}`);
        addBug({ title: `Form page load error: ${route}`, url, severity: 'critical',
                 type: 'Load Failure', description: err.message.substring(0, 200), screenshot: ss });
      } finally {
        pushErrors(url, monitor);
        monitor.clear();
      }
    }
  });

  // ── 7. Navigation integrity from dashboard ───────────────────────────────────
  test('Verify nav links from dashboard', async ({ page }) => {
    await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);

    const navLinks = await collectNavLinks(page);
    console.log(`  Found ${navLinks.length} nav links`);

    for (const link of navLinks) {
      if (!link.href || link.href.startsWith('#') || link.href.startsWith('mailto:')) continue;
      if (SKIP_CLICK.test(link.href)) continue;
      if (!link.href.includes('127.0.0.1') && link.href.startsWith('http')) continue;

      const monitor = attachErrorMonitor(page);
      try {
        const res    = await page.goto(
          link.href.startsWith('http') ? link.href : `${BASE_URL}${link.href}`,
          { waitUntil: 'domcontentloaded', timeout: 12_000 }
        );
        const status = res?.status() ?? 0;

        if (status >= 400) {
          const ss = await screenshotError(page, `nav_${link.text.substring(0, 20)}`);
          addBug({ title: `Nav link "${link.text}" → ${status}`, url: link.href,
                   severity: status >= 500 ? 'critical' : 'high', type: 'Broken Navigation',
                   description: `Nav link "${link.text}" returns HTTP ${status}`, screenshot: ss });
        }
        if (monitor.getErrors().exceptions.length > 0) {
          addBug({ title: `JS exception after nav to "${link.text}"`, url: link.href,
                   severity: 'high', type: 'JS Exception',
                   description: monitor.getErrors().exceptions[0].message.substring(0, 200) });
        }
      } catch {
        // timeout on a single nav link — not a blocking issue
      } finally {
        pushErrors(link.href, monitor);
        monitor.clear();
      }
    }
  });

  // ── 8. Loading speed check ────────────────────────────────────────────────────
  test('Check for loading problems (slow pages)', async ({ page }) => {
    const THRESHOLD_MS = 5000;

    for (const route of ADMIN_ROUTES.slice(0, 12)) {
      const url   = `${BASE_URL}${route}`;
      const start = Date.now();
      try {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 20_000 });
        const elapsed = Date.now() - start;
        if (elapsed > THRESHOLD_MS) {
          addBug({
            title:       `Slow page load: ${route} (${elapsed}ms)`,
            url,
            severity:    elapsed > 10_000 ? 'major' : 'minor',
            type:        'Loading Problem',
            description: `Page took ${elapsed}ms to reach networkidle (threshold: ${THRESHOLD_MS}ms)`,
          });
        }
      } catch { /* already caught elsewhere */ }
    }
  });

  // ── 9. Write final reports ────────────────────────────────────────────────────
  test.afterAll(async () => {
    // Deduplicate route results by route key
    const seen    = new Set();
    const deduped = routeResults.filter(r => {
      const key = r.route ?? r.url;
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });

    // Map severity labels to report format (major → high, minor/low → low)
    const normalizedBugs = bugs.map(b => ({
      ...b,
      severity: b.severity === 'major' ? 'high'
              : (b.severity === 'minor' || b.severity === 'low') ? 'low'
              : b.severity,
    }));

    generateBugReport(normalizedBugs);
    generateCoverageReport(deduped);
    writeErrorLog(allErrors);

    console.log('\n═══════════════════════════════════════');
    console.log(`  Reports generated:`);
    console.log(`  - bug-report.md      (${normalizedBugs.length} issues)`);
    console.log(`  - coverage-report.md (${deduped.length} routes)`);
    console.log(`  - error-log.json     (${allErrors.length} entries)`);
    console.log('═══════════════════════════════════════\n');
  });
});
