// Error Monitor — attaches listeners for JS errors, network failures, and exceptions

const ASSET_PATTERN = /\.(png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|css)$/i;

/**
 * Attach error listeners to a Playwright page.
 * Returns { getErrors, hasErrors, clear }.
 *
 * Call attachErrorMonitor(page) at the start of each test.
 */
export function attachErrorMonitor(page) {
  const errors = {
    console:      [],   // console.error / console.warn
    network:      [],   // HTTP 4xx/5xx + requestfailed
    exceptions:   [],   // unhandled JS exceptions (pageerror)
    failedAssets: [],   // broken images / fonts / CSS
  };

  // Console errors and warnings
  page.on('console', (msg) => {
    if (msg.type() === 'error' || msg.type() === 'warning') {
      errors.console.push({
        type:      msg.type(),
        text:      msg.text(),
        location:  msg.location(),
        pageUrl:   page.url(),
        time:      new Date().toISOString(),
      });
    }
  });

  // Unhandled JS exceptions
  page.on('pageerror', (err) => {
    errors.exceptions.push({
      message: err.message,
      stack:   err.stack,
      pageUrl: page.url(),
      time:    new Date().toISOString(),
    });
  });

  // Failed network requests
  page.on('requestfailed', (req) => {
    const entry = {
      url:     req.url(),
      method:  req.method(),
      failure: req.failure()?.errorText ?? 'unknown',
      pageUrl: page.url(),
      time:    new Date().toISOString(),
    };
    if (ASSET_PATTERN.test(req.url())) {
      errors.failedAssets.push(entry);
    } else {
      errors.network.push(entry);
    }
  });

  // HTTP 4xx / 5xx responses
  page.on('response', (res) => {
    const status = res.status();
    if (status >= 400) {
      errors.network.push({
        url:    res.url(),
        status,
        method: res.request().method(),
        pageUrl: page.url(),
        time:   new Date().toISOString(),
      });
    }
  });

  return {
    getErrors:  () => ({ ...errors }),
    hasErrors:  () => errors.console.length > 0 || errors.exceptions.length > 0,
    hasNetworkErrors: () => errors.network.length > 0,
    clear: () => {
      errors.console.length      = 0;
      errors.network.length      = 0;
      errors.exceptions.length   = 0;
      errors.failedAssets.length = 0;
    },
  };
}
