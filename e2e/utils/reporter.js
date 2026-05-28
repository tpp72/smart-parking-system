// Reporter — generates bug-report.md, coverage-report.md, and error-log.json

import fs   from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname  = path.dirname(fileURLToPath(import.meta.url));
const REPORT_DIR = path.join(__dirname, '../reports');
const LOG_DIR    = path.join(__dirname, '../logs');

function ensureDir(dir) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

// ── Error log ────────────────────────────────────────────────────────────────

/** Append a single error entry to logs/error-log.json */
export function logError(entry) {
  ensureDir(LOG_DIR);
  const file     = path.join(LOG_DIR, 'error-log.json');
  const existing = fs.existsSync(file)
    ? JSON.parse(fs.readFileSync(file, 'utf-8'))
    : [];
  existing.push({ ...entry, timestamp: new Date().toISOString() });
  fs.writeFileSync(file, JSON.stringify(existing, null, 2));
}

/** Overwrite logs/error-log.json with a full array */
export function writeErrorLog(entries) {
  ensureDir(LOG_DIR);
  fs.writeFileSync(
    path.join(LOG_DIR, 'error-log.json'),
    JSON.stringify(entries, null, 2)
  );
}

// ── Bug report ───────────────────────────────────────────────────────────────

/**
 * Generate reports/bug-report.md from an array of bug objects.
 * Each bug: { title, url, severity, type, description, screenshot, error }
 */
export function generateBugReport(bugs) {
  ensureDir(REPORT_DIR);

  const byS = { critical: [], high: [], medium: [], low: [] };
  for (const b of bugs) byS[b.severity ?? 'medium']?.push(b);

  const lines = [
    '# Bug Report',
    `**Generated:** ${new Date().toISOString()}`,
    `**Total issues:** ${bugs.length}`,
    '',
    '| Severity | Count |',
    '|----------|-------|',
    ...Object.entries(byS).map(([s, list]) => `| ${s} | ${list.length} |`),
    '',
  ];

  let idx = 1;
  for (const severity of ['critical', 'high', 'medium', 'low']) {
    for (const bug of byS[severity]) {
      lines.push(`## #${idx++} [${severity.toUpperCase()}] ${bug.title ?? 'Untitled'}`);
      lines.push(`- **URL:** ${bug.url ?? 'N/A'}`);
      lines.push(`- **Type:** ${bug.type ?? 'unknown'}`);
      if (bug.description) lines.push(`- **Description:** ${bug.description}`);
      if (bug.screenshot)  lines.push(`- **Screenshot:** \`${bug.screenshot}\``);
      if (bug.error)       lines.push(`\`\`\`\n${bug.error}\n\`\`\``);
      lines.push('');
    }
  }

  fs.writeFileSync(path.join(REPORT_DIR, 'bug-report.md'), lines.join('\n'));
}

// ── Coverage report ──────────────────────────────────────────────────────────

/**
 * Generate reports/coverage-report.md from visitRoutes() results.
 * Each route: { url, route, status, title, error, redirectedTo }
 */
export function generateCoverageReport(routes) {
  ensureDir(REPORT_DIR);

  const downloads = routes.filter(r => r.isDownload);
  const passed    = routes.filter(r => !r.isDownload && r.status >= 200 && r.status < 400 && !r.error);
  const failed    = routes.filter(r => !r.isDownload && (r.status >= 400 || r.error));
  const pct       = routes.length > 0
    ? Math.round(((passed.length + downloads.length) / routes.length) * 100)
    : 0;

  const lines = [
    '# Coverage Report',
    `**Generated:** ${new Date().toISOString()}`,
    '',
    '| Metric | Value |',
    '|--------|-------|',
    `| Total routes | ${routes.length} |`,
    `| Passed (2xx/3xx) | ${passed.length} |`,
    `| Download endpoints | ${downloads.length} |`,
    `| Failed (4xx/5xx/error) | ${failed.length} |`,
    `| Coverage | ${pct}% |`,
    '',
    '## Route Details',
    '',
    '| Route | Status | Title | Notes |',
    '|-------|--------|-------|-------|',
  ];

  for (const r of routes) {
    const status = r.isDownload ? '⬇ DL' : (r.error ? 'ERR' : String(r.status));
    const title  = (r.title ?? '').replace(/\|/g, '-').substring(0, 60);
    const notes  = r.isDownload
      ? 'File download (expected)'
      : (r.redirectedTo ? `→ ${r.redirectedTo}` : '-');
    lines.push(`| ${r.route ?? r.url} | ${status} | ${title} | ${notes} |`);
  }

  fs.writeFileSync(path.join(REPORT_DIR, 'coverage-report.md'), lines.join('\n'));
}
