/**
 * Toast ทั้งระบบ — Alpine store "toasts" + window.spToast({ tone, message, title })
 * tone: success | info | warning | error · error ไม่หายเอง (ผู้ใช้ต้องอ่านและปิด)
 * Flash message จาก Laravel ถูกส่งเข้ามาผ่าน <x-ui.toast-region :flash="true">
 */
const TIMEOUTS = { success: 6000, info: 6000, warning: 9000, error: null };
const TONES = Object.keys(TIMEOUTS);

export function registerToasts(Alpine) {
    Alpine.store('toasts', {
        items: [],
        nextId: 1,

        push({ tone = 'info', message = '', title = null } = {}) {
            if (!message) return null;
            const safeTone = TONES.includes(tone) ? tone : 'info';
            const toast = { id: this.nextId++, tone: safeTone, title, message, timeout: TIMEOUTS[safeTone], paused: false, timer: null };
            this.items.push(toast);
            this.schedule(toast);
            return toast.id;
        },

        schedule(toast) {
            if (!toast.timeout) return;
            clearTimeout(toast.timer);
            toast.timer = setTimeout(() => this.dismiss(toast.id), toast.timeout);
        },

        pause(id) {
            const toast = this.items.find((item) => item.id === id);
            if (toast) clearTimeout(toast.timer);
        },

        resume(id) {
            const toast = this.items.find((item) => item.id === id);
            if (toast) this.schedule(toast);
        },

        dismiss(id) {
            const toast = this.items.find((item) => item.id === id);
            if (toast) clearTimeout(toast.timer);
            this.items = this.items.filter((item) => item.id !== id);
        },
    });

    window.spToast = (options) => Alpine.store('toasts').push(options);
}
