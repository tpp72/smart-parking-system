/**
 * สถานะโหลดทั้งระบบ — ไม่มี overlay เต็มจออีกต่อไป
 *
 * 1) เปลี่ยนหน้า: แถบบาง 2px ด้านบน (#sp-progress) ขึ้นเฉพาะเมื่อรอเกิน 300ms
 * 2) ส่งฟอร์ม: ปุ่ม submit ที่กดเข้าสถานะกำลังดำเนินการ (aria-busy + disabled) กันกดซ้ำ
 *
 * ข้ามทุกกรณีที่หน้าไม่เปลี่ยนจริง: กดยกเลิกในกล่องยืนยัน, ดาวน์โหลดไฟล์ (Export CSV),
 * เปิดแท็บใหม่ / Ctrl-คลิก, ลิงก์ภายในหน้า, ลิงก์ไปเว็บอื่น
 */
const SHOW_DELAY = 300;
const SAFETY_TIMEOUT = 15000;

const isDownloadPath = (pathname) => /\/export(s\/|$)/.test(pathname);

let showTimer = null;
let safetyTimer = null;

const bar = () => document.getElementById('sp-progress');

export function startProgress() {
    clearTimeout(showTimer);
    clearTimeout(safetyTimer);
    showTimer = setTimeout(() => bar()?.setAttribute('data-state', 'running'), SHOW_DELAY);
    safetyTimer = setTimeout(stopProgress, SAFETY_TIMEOUT);
}

export function stopProgress() {
    clearTimeout(showTimer);
    clearTimeout(safetyTimer);
    const el = bar();
    if (el?.getAttribute('data-state') === 'running') el.setAttribute('data-state', 'done');
}

function setBusy(button, busy) {
    if (!button) return;
    if (busy) {
        button.setAttribute('aria-busy', 'true');
        button.setAttribute('data-loading', '');
        // ปิดหลัง submit เริ่มแล้ว เพื่อให้ค่า name/value ของปุ่มยังถูกส่งไป
        setTimeout(() => { button.disabled = true; }, 0);
    } else {
        button.removeAttribute('aria-busy');
        button.removeAttribute('data-loading');
        button.disabled = false;
    }
}

function resetBusyButtons() {
    document.querySelectorAll('[data-loading][aria-busy="true"]').forEach((button) => setBusy(button, false));
}

export function initNavigationFeedback() {
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || /^(javascript|mailto|tel):/i.test(href)) return;

        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || isDownloadPath(url.pathname)) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;

        startProgress();
    });

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;

        const form = event.target;
        if (form.target && form.target !== '_self') return;

        const action = new URL(form.getAttribute('action') || location.href, location.href);
        if (isDownloadPath(action.pathname)) return;

        if (!form.hasAttribute('data-no-busy')) {
            const button = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
            setBusy(button, true);
            setTimeout(() => setBusy(button, false), SAFETY_TIMEOUT);
        }

        startProgress();
    });

    // กลับมาหน้าเดิมด้วยปุ่มย้อนกลับ (back-forward cache) → คืนสถานะปกติ
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        stopProgress();
        resetBusyButtons();
    });
}
