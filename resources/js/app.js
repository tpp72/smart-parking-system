import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

/* ── Page Loader ─────────────────────────────────── */
(function () {
    const loader = () => document.getElementById('sp-page-loader');

    function hide() {
        const el = loader();
        if (!el) return;
        el.classList.add('sp-loader-out');
        setTimeout(() => { if (el) el.style.display = 'none'; }, 280);
    }

    function show() {
        const el = loader();
        if (!el) return;
        el.style.display = 'flex';
        el.style.opacity  = '1';
        el.classList.remove('sp-loader-out');
    }

    // Hide when DOM is ready (fast — no waiting for images)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hide);
    } else {
        hide();
    }

    // Show loader on link navigation
    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[href]');
        if (!a) return;
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript') || a.target === '_blank') return;
        show();
    });

    // Show loader on form submit
    document.addEventListener('submit', function () {
        show();
    });
})();
