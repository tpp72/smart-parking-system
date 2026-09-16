import './flatpickr-init';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

/* ── Page Loader ─────────────────────────────────── */
(function () {
    const SAFETY_TIMEOUT = 10000; // ไม่มีการเปลี่ยนหน้าเกิดขึ้นจริงภายในเวลานี้ → ซ่อนเอง กันค้างถาวร
    let safetyTimer = null;

    const loader = () => document.getElementById('sp-page-loader');

    function hide(immediate = false) {
        clearTimeout(safetyTimer);
        const el = loader();
        if (!el) return;
        el.style.opacity = ''; // ให้ CSS ของ .sp-loader-out ทำ fade-out ได้ (inline opacity จาก show() จะบังไว้)
        el.classList.add('sp-loader-out');
        if (immediate) {
            el.style.display = 'none';
            return;
        }
        setTimeout(() => { if (el.classList.contains('sp-loader-out')) el.style.display = 'none'; }, 280);
    }

    function show() {
        const el = loader();
        if (!el) return;
        el.style.display = 'flex';
        el.style.opacity = '1';
        el.classList.remove('sp-loader-out');
        clearTimeout(safetyTimer);
        safetyTimer = setTimeout(() => hide(), SAFETY_TIMEOUT);
    }

    // ปุ่มย้อนกลับ/ไปข้างหน้าของ Browser มี 2 แบบ ซึ่ง Chrome เลือกเองในแต่ละครั้ง:
    //  1) คืนหน้าจาก back-forward cache ทันที — DOMContentLoaded ไม่ทำงานซ้ำ (จัดการที่ pageshow ด้านล่าง)
    //  2) โหลดหน้าใหม่จาก server — ถ้า server ตอบเร็ว loader จะถูกซ่อนเร็วจนมองไม่เห็น
    // ทั้งสองแบบแสดง loader อย่างน้อย RESTORE_LOADER_MS แล้วจางหายเอง เพื่อให้เห็นเหมือนกันทุกครั้ง
    const RESTORE_LOADER_MS = 350;
    const isBackForward = performance.getEntriesByType('navigation')[0]?.type === 'back_forward';
    const hideAfterLoad = () => (isBackForward ? setTimeout(() => hide(), RESTORE_LOADER_MS) : hide());

    // Hide when DOM is ready (fast — no waiting for images)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideAfterLoad);
    } else {
        hideAfterLoad();
    }

    // แบบที่ 1: คืนจาก back-forward cache — แสดง loader สั้น ๆ แล้วจางหายเอง (ไม่ค้าง)
    // (ไม่ซ่อนตอน pagehide เพราะจะทำให้ loader หายไประหว่างรอหน้าใหม่)
    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;
        show();
        setTimeout(() => hide(), RESTORE_LOADER_MS);
    });

    // Show loader on link navigation — เฉพาะการเปลี่ยนหน้าจริงในแท็บนี้
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        const a = e.target.closest('a[href]');
        if (!a || a.hasAttribute('download') || (a.target && a.target !== '_self')) return;

        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

        const url = new URL(a.href, location.href);
        if (url.origin !== location.origin) return;
        // ลิงก์ Export CSV เป็นการดาวน์โหลดไฟล์ หน้าเดิมไม่ถูกเปลี่ยน
        if (/\/export(s\/|$)/.test(url.pathname)) return;
        // ลิงก์ไปยังตำแหน่งในหน้าเดิม
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;

        show();
    });

    // Show loader on form submit — ข้ามเมื่อถูกยกเลิก (เช่น กด "ยกเลิก" ในกล่อง confirm) หรือเป็นการดาวน์โหลดไฟล์
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;

        const form = e.target;
        if (form.target && form.target !== '_self') return;

        const action = new URL(form.getAttribute('action') || location.href, location.href);
        if (/\/export(s\/|$)/.test(action.pathname)) return;

        show();
    });
})();
