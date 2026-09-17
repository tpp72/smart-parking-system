/**
 * ธีม: Light / Dark / System (ค่าเริ่มต้น System = ตาม OS) — จำค่าใน localStorage
 * ค่า data-theme ถูกตั้งก่อน paint แล้วโดย resources/views/partials/theme-init.blade.php
 * ไฟล์นี้ดูแลการเปลี่ยนหลังโหลดหน้า และตามการเปลี่ยนธีมของ OS เมื่อผู้ใช้เลือก System
 */
const STORAGE_KEY = 'sp-theme';
const PREFERENCES = ['light', 'dark', 'system'];
const media = window.matchMedia('(prefers-color-scheme: dark)');

export function getPreference() {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        return PREFERENCES.includes(value) ? value : 'system';
    } catch {
        return 'system';
    }
}

const resolve = (preference) => (preference === 'system' ? (media.matches ? 'dark' : 'light') : preference);

export function applyTheme(preference = getPreference()) {
    const root = document.documentElement;
    const theme = resolve(preference);
    root.setAttribute('data-theme', theme);
    root.setAttribute('data-theme-preference', preference);
    window.dispatchEvent(new CustomEvent('sp:theme-change', { detail: { preference, theme } }));
}

export function setPreference(preference) {
    if (!PREFERENCES.includes(preference)) return;
    try {
        localStorage.setItem(STORAGE_KEY, preference);
    } catch {
        /* โหมดส่วนตัว/ปิด storage — ยังเปลี่ยนธีมในหน้านี้ได้ */
    }
    applyTheme(preference);
}

export function initTheme() {
    media.addEventListener('change', () => {
        if (getPreference() === 'system') applyTheme('system');
    });
    // เปลี่ยนธีมในแท็บอื่น → ตามด้วย
    window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_KEY) applyTheme();
    });
}

/** Alpine: <x-ui.theme-switch> — radiogroup 3 ตัวเลือก รองรับลูกศรซ้าย/ขวา */
export function themeSwitch() {
    return {
        preference: getPreference(),
        options: PREFERENCES,
        labels: { light: 'สว่าง', dark: 'มืด', system: 'ตามระบบ' },
        open: false,

        /* popover: เปิดแผงแล้วโฟกัสตัวเลือกปัจจุบัน · ปิดด้วย Escape คืนโฟกัสให้ปุ่ม */
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.$root.querySelector(`[data-theme-option="${this.preference}"]`)?.focus());
            }
        },

        close(returnFocus = false) {
            if (!this.open) return;
            this.open = false;
            if (returnFocus) this.$refs.trigger?.focus();
        },

        init() {
            window.addEventListener('sp:theme-change', (event) => {
                this.preference = event.detail.preference;
            });
        },

        choose(preference) {
            setPreference(preference);
        },

        move(step) {
            const next = PREFERENCES[(PREFERENCES.indexOf(this.preference) + step + PREFERENCES.length) % PREFERENCES.length];
            this.choose(next);
            this.$nextTick(() => this.$root.querySelector(`[data-theme-option="${next}"]`)?.focus());
        },
    };
}
