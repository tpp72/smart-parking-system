/**
 * CSS Visual Test Suite — Smart Parking System
 *
 * ถ่าย full-page screenshot ทุกหน้า ที่ 3 viewport
 * และตรวจ overflow / broken-image ทุก viewport
 * ผล: e2e/screenshots/responsive/<route>_<viewport>.png
 *      e2e/reports/css-report.md
 */

import { test } from '@playwright/test';
import fs   from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { ADMIN_ROUTES, USER_ROUTES, GUEST_ROUTES } from './utils/routes.js';

const __dirname  = path.dirname(fileURLToPath(import.meta.url));
const SS_DIR     = path.join(__dirname, 'screenshots', 'responsive');
const REPORT_DIR = path.join(__dirname, 'reports');
const BASE       = 'http://127.0.0.1:8000';

const VIEWPORTS = [
  { name: 'desktop', width: 1280, height: 800 },
  { name: 'tablet',  width: 768,  height: 1024 },
  { name: 'mobile',  width: 375,  height: 667 },
];

function slug(str) {
  return str.replace(/[^a-zA-Z0-9]/g, '_').replace(/^_+|_+$/g, '').substring(0, 60);
}

function ensureDir(d) { if (!fs.existsSync(d)) fs.mkdirSync(d, { recursive: true }); }

// ── Overflow checker (skips scroll-container children) ──────────────────────
async function detectOverflow(page) {
  return page.evaluate(() => {
    function inScroll(el) {
      let n = el.parentElement;
      while (n && n !== document.body) {
        const ox = window.getComputedStyle(n).overflowX;
        if (ox === 'auto' || ox === 'scroll') return true;
        n = n.parentElement;
      }
      return false;
    }
    const out = [];
    for (const el of document.querySelectorAll('*')) {
      const s = window.getComputedStyle(el);
      if (s.display === 'none' || s.visibility === 'hidden') continue;
      if (inScroll(el)) continue;
      const r = el.getBoundingClientRect();
      if (r.right > window.innerWidth + 10) {
        out.push({
          tag: el.tagName,
          cls: typeof el.className === 'string' ? el.className.split(' ').filter(Boolean).slice(0, 3).join(' ') : '',
          px:  Math.round(r.right - window.innerWidth),
        });
      }
    }
    return out;
  });
}

// ── Broken images checker ────────────────────────────────────────────────────
async function detectBrokenImages(page) {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll('img'))
      .filter(i => !i.complete || i.naturalWidth === 0)
      .map(i => i.src)
  );
}

// ════════════════════════════════════════════════════════════════════════════
// SHARED STATE
// ════════════════════════════════════════════════════════════════════════════

const report = [];   // { route, viewport, ssPath, overflows, brokenImages, status }
const issues = [];   // { severity, route, viewport, type, detail, ssPath }

function addIssue(severity, route, viewport, type, detail, ssPath) {
  issues.push({ severity, route, viewport, type, detail, ssPath });
  console.warn(`  [${severity.toUpperCase()}] ${type} @ ${route} (${viewport}): ${detail}`);
}

// ════════════════════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════════════════════

async function testRouteAtViewport(page, route, vp) {
  const url  = `${BASE}${route}`;
  const key  = `${slug(route)}_${vp.name}`;
  const ssPath = path.join(SS_DIR, `${key}.png`);
  const entry  = { route, viewport: vp.name, ssPath: `e2e/screenshots/responsive/${key}.png`, overflows: [], brokenImages: [], status: null };

  await page.setViewportSize({ width: vp.width, height: vp.height });

  try {
    const res = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20_000 });
    entry.status = res?.status() ?? 0;
    await page.waitForTimeout(300);

    // Screenshot
    ensureDir(SS_DIR);
    await page.screenshot({ path: ssPath, fullPage: true });

    // Overflow check
    const overflows = await detectOverflow(page);
    entry.overflows = overflows;
    if (overflows.length > 0) {
      const severity = vp.name === 'mobile' && overflows[0].px > 20 ? 'major'
                     : vp.name === 'mobile' ? 'minor'
                     : overflows[0].px > 40 ? 'minor' : 'low';
      if (severity !== 'low') {
        addIssue(severity, route, vp.name, 'Overflow',
          `${overflows.length} element(s) overflow. First: <${overflows[0].tag} "${overflows[0].cls}"> +${overflows[0].px}px`,
          entry.ssPath);
      }
    }

    // Broken images
    const broken = await detectBrokenImages(page);
    entry.brokenImages = broken;
    if (broken.length > 0) {
      addIssue('major', route, vp.name, 'Broken Image', broken.join(', ').substring(0, 120), entry.ssPath);
    }

    // HTTP error
    if (entry.status >= 400) {
      addIssue(entry.status >= 500 ? 'critical' : 'major', route, vp.name, `HTTP ${entry.status}`, url, entry.ssPath);
    }

  } catch (err) {
    entry.status = 0;
    entry.error  = err.message.substring(0, 120);
    addIssue('critical', route, vp.name, 'Load Error', entry.error, null);
  }

  report.push(entry);
}

// ════════════════════════════════════════════════════════════════════════════
// TESTS
// ════════════════════════════════════════════════════════════════════════════

test.describe('CSS Visual Test — Smart Parking System', () => {

  // ── Admin routes ───────────────────────────────────────────────────────────
  test('Admin pages — all viewports', async ({ page }) => {
    test.setTimeout(300_000);
    for (const route of ADMIN_ROUTES) {
      for (const vp of VIEWPORTS) {
        await testRouteAtViewport(page, route, vp);
      }
    }
  });

  // ── User routes (user session) ─────────────────────────────────────────────
  test('User pages — all viewports', async ({ browser }) => {
    test.setTimeout(180_000);

    // user session — look for user.json auth state
    const userAuthPath = path.join(__dirname, '.auth', 'user.json');
    const ctx = fs.existsSync(userAuthPath)
      ? await browser.newContext({ storageState: userAuthPath })
      : await browser.newContext();
    const page = await ctx.newPage();

    try {
      for (const route of USER_ROUTES) {
        for (const vp of VIEWPORTS) {
          await testRouteAtViewport(page, route, vp);
        }
      }
    } finally {
      await ctx.close();
    }
  });

  // ── Guest routes (unauthenticated) ─────────────────────────────────────────
  test('Guest pages — all viewports', async ({ browser }) => {
    test.setTimeout(60_000);
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    try {
      for (const route of GUEST_ROUTES) {
        for (const vp of VIEWPORTS) {
          await testRouteAtViewport(page, route, vp);
        }
      }
    } finally {
      await ctx.close();
    }
  });

  // ── Generate CSS report ────────────────────────────────────────────────────
  test.afterAll(async () => {
    ensureDir(REPORT_DIR);

    const totalPages = new Set(report.map(r => r.route)).size;
    const totalShots = report.length;
    const byVP = { desktop: 0, tablet: 0, mobile: 0 };
    report.forEach(r => { if (byVP[r.viewport] !== undefined) byVP[r.viewport]++; });

    const bySev = { critical: 0, major: 0, minor: 0 };
    issues.forEach(i => { if (bySev[i.severity] !== undefined) bySev[i.severity]++; });

    const lines = [
      '# CSS Visual Test Report',
      `**Generated:** ${new Date().toISOString()}`,
      '',
      '## Summary',
      '',
      '| Metric | Value |',
      '|--------|-------|',
      `| Pages tested | ${totalPages} |`,
      `| Screenshots taken | ${totalShots} |`,
      `| Desktop shots | ${byVP.desktop} |`,
      `| Tablet shots  | ${byVP.tablet} |`,
      `| Mobile shots  | ${byVP.mobile} |`,
      `| Total issues  | ${issues.length} |`,
      `| Critical | ${bySev.critical} |`,
      `| Major    | ${bySev.major} |`,
      `| Minor    | ${bySev.minor} |`,
      '',
    ];

    if (issues.length === 0) {
      lines.push('## ✅ No CSS issues found');
    } else {
      lines.push('## Issues');
      lines.push('');
      lines.push('| # | Severity | Viewport | Route | Type | Detail | Screenshot |');
      lines.push('|---|----------|----------|-------|------|--------|------------|');
      issues.forEach((iss, i) => {
        const ss = iss.ssPath ? `[\`screenshot\`](${iss.ssPath})` : '-';
        lines.push(`| ${i + 1} | **${iss.severity}** | ${iss.viewport} | ${iss.route} | ${iss.type} | ${iss.detail} | ${ss} |`);
      });
    }

    lines.push('');
    lines.push('## Screenshot Index');
    lines.push('');
    lines.push('| Route | Desktop | Tablet | Mobile |');
    lines.push('|-------|---------|--------|--------|');

    const routes = [...new Set(report.map(r => r.route))];
    for (const route of routes) {
      const byViewport = {};
      report.filter(r => r.route === route).forEach(r => { byViewport[r.viewport] = r.ssPath; });
      const cell = (vp) => byViewport[vp] ? `[\`📷\`](${byViewport[vp]})` : '-';
      lines.push(`| ${route} | ${cell('desktop')} | ${cell('tablet')} | ${cell('mobile')} |`);
    }

    const outPath = path.join(REPORT_DIR, 'css-report.md');
    fs.writeFileSync(outPath, lines.join('\n'));

    console.log('\n╔══════════════════════════════════════╗');
    console.log(`║  CSS Visual Test Complete             ║`);
    console.log(`║  Pages : ${String(totalPages).padEnd(26)}║`);
    console.log(`║  Shots : ${String(totalShots).padEnd(26)}║`);
    console.log(`║  Issues: ${String(issues.length).padEnd(26)}║`);
    console.log('╚══════════════════════════════════════╝');
  });

});
