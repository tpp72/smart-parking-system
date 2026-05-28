// Route Crawler — discovers and visits all reachable pages from a start URL

const DEFAULT_EXCLUDE = [
  /logout/,
  /\/email\/verification/,
  /password\/confirm/,
  /\.(pdf|zip|png|jpg|jpeg|gif|svg|ico|css|js|woff|woff2|ttf|map)$/i,
];

// Known download endpoints — navigating to these triggers a file download,
// which is correct behaviour.  The crawler marks them as "download" rather
// than reporting a load failure.
const DOWNLOAD_PATTERNS = [
  /\/export($|\/|\?)/i,
  /\/download($|\/|\?)/i,
  /\/export-csv/i,
  /\/report\.pdf/i,
];

function isDownloadUrl(url) {
  return DOWNLOAD_PATTERNS.some(p => p.test(url));
}

/**
 * Crawl all links reachable from startUrl.
 * Returns an array of { url, status, title, depth, errors, links, isDownload }.
 */
export async function crawlRoutes(page, startUrl = '/', options = {}) {
  const {
    maxPages = 80,
    maxDepth = 4,
    baseURL = 'http://127.0.0.1:8000',
    exclude = DEFAULT_EXCLUDE,
    waitUntil = 'domcontentloaded',
  } = options;

  const visited = new Set();
  const queue   = [{ url: startUrl, depth: 0 }];
  const results = [];

  while (queue.length > 0 && visited.size < maxPages) {
    const { url, depth } = queue.shift();

    const fullUrl = url.startsWith('http') ? url : `${baseURL}${url}`;

    if (visited.has(fullUrl))                             continue;
    if (!fullUrl.startsWith(baseURL))                     continue;
    if (exclude.some(p => p.test(fullUrl)))               continue;
    if (depth > maxDepth)                                 continue;

    visited.add(fullUrl);

    const result = {
      url:        fullUrl,
      status:     null,
      title:      null,
      depth,
      errors:     [],
      links:      [],
      isDownload: false,
    };

    // Download endpoints — catch the "Download is starting" error and record
    // them as valid download routes instead of failures.
    if (isDownloadUrl(fullUrl)) {
      result.isDownload = true;
      result.status     = 200; // treat as OK
      result.title      = '(download endpoint)';
      results.push(result);
      continue;
    }

    try {
      const response = await page.goto(fullUrl, { waitUntil, timeout: 15_000 });
      result.status  = response?.status() ?? 0;
      result.title   = await page.title();

      const hrefs = await page.evaluate(() =>
        Array.from(document.querySelectorAll('a[href]'))
          .map(a => a.getAttribute('href'))
          .filter(h => h && !h.startsWith('#') && !h.startsWith('mailto:') && !h.startsWith('tel:'))
      );

      result.links = hrefs;

      for (const href of hrefs) {
        const normalized = href.startsWith('http') ? href : `${baseURL}${href}`;
        if (!visited.has(normalized)) queue.push({ url: normalized, depth: depth + 1 });
      }
    } catch (err) {
      // Detect download-triggered navigation errors even for URLs we didn't
      // pre-classify (e.g. a link whose href doesn't match DOWNLOAD_PATTERNS
      // but whose response sets Content-Disposition: attachment).
      if (err.message.includes('Download is starting') ||
          err.message.includes('net::ERR_ABORTED')) {
        result.isDownload = true;
        result.status     = 200;
        result.title      = '(download endpoint)';
      } else {
        result.errors.push(err.message);
      }
    }

    results.push(result);
  }

  return results;
}

/**
 * Visit a predefined list of routes and return status + title for each.
 */
export async function visitRoutes(page, routes, baseURL = 'http://127.0.0.1:8000') {
  const results = [];

  for (const route of routes) {
    const url    = route.startsWith('http') ? route : `${baseURL}${route}`;
    const result = {
      url,
      route,
      status:      null,
      title:       null,
      error:       null,
      redirectedTo: null,
      isDownload:  false,
    };

    if (isDownloadUrl(url)) {
      result.isDownload = true;
      result.status     = 200;
      result.title      = '(download endpoint)';
      results.push(result);
      continue;
    }

    try {
      const response    = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15_000 });
      result.status     = response?.status() ?? 0;
      result.title      = await page.title();
      result.redirectedTo = page.url() !== url ? page.url() : null;
    } catch (err) {
      if (err.message.includes('Download is starting') ||
          err.message.includes('net::ERR_ABORTED')) {
        result.isDownload = true;
        result.status     = 200;
        result.title      = '(download endpoint)';
      } else {
        result.error = err.message;
      }
    }

    results.push(result);
  }

  return results;
}
