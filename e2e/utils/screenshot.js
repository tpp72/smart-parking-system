// Screenshot helpers — captures failures, page states, and responsive viewports

import fs   from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname   = path.dirname(fileURLToPath(import.meta.url));
const BASE_DIR    = path.join(__dirname, '../screenshots');

const VIEWPORTS = [
  { name: 'desktop', width: 1280, height: 800 },
  { name: 'tablet',  width: 768,  height: 1024 },
  { name: 'mobile',  width: 375,  height: 667 },
];

function ensureDir(dir) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

function slug(str) {
  return String(str).replace(/[^a-zA-Z0-9-_]/g, '_').substring(0, 80);
}

function ts() {
  return new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19);
}

/** Capture a full-page screenshot on test failure */
export async function screenshotOnFailure(page, testName) {
  const dir      = path.join(BASE_DIR, 'failures');
  ensureDir(dir);
  const filename = `${slug(testName)}_${ts()}.png`;
  await page.screenshot({ path: path.join(dir, filename), fullPage: true });
  return path.join('e2e/screenshots/failures', filename);
}

/** Capture a named full-page screenshot */
export async function screenshotPage(page, name) {
  const dir      = path.join(BASE_DIR, 'pages');
  ensureDir(dir);
  const filename = `${slug(name)}_${ts()}.png`;
  await page.screenshot({ path: path.join(dir, filename), fullPage: true });
  return path.join('e2e/screenshots/pages', filename);
}

/** Capture a screenshot annotated with error context */
export async function screenshotError(page, context) {
  const dir      = path.join(BASE_DIR, 'errors');
  ensureDir(dir);
  const filename = `${slug(context ?? 'error')}_${ts()}.png`;
  await page.screenshot({ path: path.join(dir, filename), fullPage: true });
  return path.join('e2e/screenshots/errors', filename);
}

/** Capture the same page at desktop, tablet, and mobile widths */
export async function screenshotViewports(page, name) {
  const dir   = path.join(BASE_DIR, 'responsive');
  ensureDir(dir);
  const files = [];

  // Save original viewport
  const original = page.viewportSize();

  for (const vp of VIEWPORTS) {
    await page.setViewportSize({ width: vp.width, height: vp.height });
    const filename = `${slug(name)}_${vp.name}_${ts()}.png`;
    await page.screenshot({ path: path.join(dir, filename), fullPage: true });
    files.push(path.join('e2e/screenshots/responsive', filename));
  }

  // Restore original viewport
  if (original) await page.setViewportSize(original);

  return files;
}
