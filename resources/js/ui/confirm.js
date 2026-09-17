/**
 * กล่องยืนยันแทน confirm() ของ Browser
 *
 * แบบโค้ด:     const ok = await window.spConfirm({ title, message, confirmLabel, tone: 'danger' })
 * แบบประกาศ:  <form data-confirm="ข้อความ" data-confirm-title="..." data-confirm-label="..." data-confirm-tone="danger">
 *
 * กล่องนี้ render ครั้งเดียวใน layout (<x-ui.confirm-dialog />) และคืนโฟกัสไปยังปุ่มเดิมเมื่อปิด
 */
export function registerConfirm(Alpine) {
    Alpine.store('confirm', {
        open: false,
        title: '',
        message: '',
        confirmLabel: 'ยืนยัน',
        cancelLabel: 'ยกเลิก',
        tone: 'primary',
        resolver: null,
        returnFocus: null,

        ask({ title = 'ยืนยันการทำรายการ', message = '', confirmLabel = 'ยืนยัน', cancelLabel = 'ยกเลิก', tone = 'primary' } = {}) {
            this.settle(false);
            Object.assign(this, { title, message, confirmLabel, cancelLabel, tone: tone === 'danger' ? 'danger' : 'primary' });
            this.returnFocus = document.activeElement;
            this.open = true;
            return new Promise((resolve) => { this.resolver = resolve; });
        },

        settle(result) {
            if (!this.resolver) return;
            const resolve = this.resolver;
            this.resolver = null;
            this.open = false;
            resolve(result);
            const target = this.returnFocus;
            this.returnFocus = null;
            if (target && typeof target.focus === 'function') setTimeout(() => target.focus(), 0);
        },
    });

    window.spConfirm = (options) => Alpine.store('confirm').ask(options);

    // ฟอร์มที่ประกาศ data-confirm — ดักตั้งแต่ capture phase ก่อนตัวจัดการ submit อื่น
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const submitter = event.submitter;
        const ok = await window.spConfirm({
            title: form.dataset.confirmTitle || 'ยืนยันการทำรายการ',
            message: form.dataset.confirm,
            confirmLabel: form.dataset.confirmLabel || 'ยืนยัน',
            cancelLabel: form.dataset.confirmCancel || 'ยกเลิก',
            tone: form.dataset.confirmTone,
        });

        if (!ok) return;
        form.dataset.confirmed = 'true';
        form.requestSubmit(submitter && form.contains(submitter) ? submitter : undefined);
    }, true);
}
