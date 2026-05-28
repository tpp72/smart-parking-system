// UI/UX Detector — finds layout overflow, invisible text, broken images, responsive issues

const VIEWPORTS = [
  { name: 'mobile',  width: 375,  height: 667  },
  { name: 'tablet',  width: 768,  height: 1024 },
  { name: 'desktop', width: 1280, height: 800  },
];

/**
 * Find elements that overflow the viewport horizontally.
 * Skips elements that are inside a legitimate scroll container
 * (overflow-x: auto | scroll) — those are expected to extend past viewport.
 * Also skips elements with display:none or visibility:hidden.
 */
export async function checkOverflow(page) {
  return page.evaluate(() => {
    const OVERFLOW_PX_THRESHOLD = 10; // ignore sub-10px rounding artefacts

    function isInsideScrollContainer(el) {
      let node = el.parentElement;
      while (node && node !== document.body) {
        const style = window.getComputedStyle(node);
        const ox = style.overflowX;
        if (ox === 'auto' || ox === 'scroll') return true;
        node = node.parentElement;
      }
      return false;
    }

    function isVisible(el) {
      const s = window.getComputedStyle(el);
      return s.display !== 'none' && s.visibility !== 'hidden' && parseFloat(s.opacity) > 0;
    }

    const issues = [];
    for (const el of document.querySelectorAll('*')) {
      if (!isVisible(el)) continue;
      if (isInsideScrollContainer(el)) continue;

      const rect = el.getBoundingClientRect();
      if (rect.right > window.innerWidth + OVERFLOW_PX_THRESHOLD) {
        issues.push({
          tag:        el.tagName,
          id:         el.id        || null,
          className:  (el.className && typeof el.className === 'string')
                        ? el.className.split(' ').filter(Boolean).slice(0, 3).join(' ')
                        : null,
          overflowPx: Math.round(rect.right - window.innerWidth),
        });
      }
    }
    return issues;
  });
}

/** Find text elements that may be invisible (zero-size, hidden colour, etc.) */
export async function checkInvisibleText(page) {
  return page.evaluate(() => {
    const issues = [];
    const sel = 'p,span,h1,h2,h3,h4,h5,h6,a,button,label,td,th,li,div';

    for (const el of document.querySelectorAll(sel)) {
      const text = el.textContent?.trim();
      if (!text) continue;

      const style = window.getComputedStyle(el);

      // Same foreground and background colour (excluding transparent)
      if (style.color === style.backgroundColor && style.color !== 'rgba(0, 0, 0, 0)') {
        issues.push({ tag: el.tagName, text: text.substring(0, 60), issue: 'same fg/bg colour', color: style.color });
      }

      // Visible in DOM but has zero dimensions
      const rect = el.getBoundingClientRect();
      if (
        style.display     !== 'none'    &&
        style.visibility  !== 'hidden'  &&
        parseFloat(style.opacity) > 0   &&
        (rect.width === 0 || rect.height === 0)
      ) {
        issues.push({ tag: el.tagName, text: text.substring(0, 60), issue: 'zero dimensions' });
      }
    }
    return issues;
  });
}

/** Find <img> elements that failed to load */
export async function checkBrokenImages(page) {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll('img'))
      .filter(img => !img.complete || img.naturalWidth === 0)
      .map(img => ({ src: img.src, alt: img.alt || null }))
  );
}

/** Run overflow check across three viewports, restore original after */
export async function checkResponsiveIssues(page) {
  const original = page.viewportSize();
  const results  = [];

  for (const vp of VIEWPORTS) {
    await page.setViewportSize({ width: vp.width, height: vp.height });
    const overflow = await checkOverflow(page);
    if (overflow.length > 0) results.push({ viewport: vp.name, ...vp, overflow });
  }

  if (original) await page.setViewportSize(original);
  return results;
}

/** Run all UI checks at once and return a combined report */
export async function runUiChecks(page) {
  return {
    overflow:      await checkOverflow(page),
    invisibleText: await checkInvisibleText(page),
    brokenImages:  await checkBrokenImages(page),
    responsive:    await checkResponsiveIssues(page),
  };
}
