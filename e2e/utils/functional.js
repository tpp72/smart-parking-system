// Functional helpers — collect and interact with buttons, nav, forms, and modals

// ── Collectors ───────────────────────────────────────────────────────────────

/** Return metadata for all visible, enabled buttons on the page */
export async function collectButtons(page) {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll(
      'button, input[type="submit"], input[type="button"], [role="button"]'
    ))
      .map(el => {
        const rect = el.getBoundingClientRect();
        return {
          tag:      el.tagName,
          type:     el.getAttribute('type') ?? null,
          text:     el.textContent?.trim().substring(0, 100) ?? '',
          id:       el.id || null,
          name:     el.getAttribute('name') || null,
          disabled: el.disabled || el.hasAttribute('disabled'),
          visible:  rect.width > 0 && rect.height > 0,
        };
      })
      .filter(b => !b.disabled && b.visible)
  );
}

/** Return all nav/header links with href and text */
export async function collectNavLinks(page) {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll('nav a[href], header a[href]'))
      .map(a => {
        const rect = a.getBoundingClientRect();
        return {
          href:    a.getAttribute('href'),
          text:    a.textContent?.trim() ?? '',
          visible: rect.width > 0 && rect.height > 0,
        };
      })
      .filter(a => a.href && a.visible)
  );
}

/** Return metadata for each <form> on the page */
export async function collectForms(page) {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll('form')).map((form, i) => ({
      index:  i,
      action: form.getAttribute('action') ?? window.location.pathname,
      method: (form.getAttribute('method') ?? 'GET').toUpperCase(),
      fields: Array.from(
        form.querySelectorAll('input:not([type=hidden]), select, textarea')
      ).map(f => ({
        name:        f.name        || null,
        type:        f.type        || f.tagName.toLowerCase(),
        required:    f.required,
        placeholder: f.placeholder || null,
      })),
    }))
  );
}

/** Find Alpine.js / modal trigger elements */
export async function collectModals(page) {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll(
      '[data-modal-target], [data-toggle="modal"], [x-data*="modal"], [x-data*="open"]'
    )).map((el, i) => ({
      index:   i,
      tag:     el.tagName,
      trigger: el.getAttribute('data-modal-target')
               ?? el.getAttribute('data-toggle')
               ?? null,
      xData:   el.getAttribute('x-data')?.substring(0, 80) ?? null,
    }))
  );
}

// ── Interaction helpers ──────────────────────────────────────────────────────

/**
 * Check that navigating to href results in a 2xx page.
 * Returns { href, finalUrl, status, ok }.
 */
export async function testLink(page, href, baseURL = 'http://127.0.0.1:8000') {
  const url = href.startsWith('http') ? href : `${baseURL}${href}`;
  let status = null;

  const handler = res => { if (res.url() === url) status = res.status(); };
  page.on('response', handler);

  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15_000 });
  } finally {
    page.off('response', handler);
  }

  return { href, finalUrl: page.url(), status, ok: status !== null && status < 400 };
}

/**
 * Fill every visible text/email/textarea field with a placeholder value
 * and return the form element count.
 */
export async function fillFormFields(page, formIndex = 0) {
  return page.evaluate((idx) => {
    const forms = document.querySelectorAll('form');
    const form  = forms[idx];
    if (!form) return 0;

    let filled = 0;
    for (const el of form.querySelectorAll('input, textarea, select')) {
      if (el.type === 'hidden' || el.disabled) continue;
      if (['text', 'email', 'search', 'url', 'tel'].includes(el.type)) {
        el.value = el.placeholder || 'test';
        filled++;
      } else if (el.type === 'textarea' || el.tagName === 'TEXTAREA') {
        el.value = 'test input';
        filled++;
      } else if (el.tagName === 'SELECT' && el.options.length > 1) {
        el.selectedIndex = 1;
        filled++;
      }
    }
    return filled;
  }, formIndex);
}
