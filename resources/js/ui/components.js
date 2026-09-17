/**
 * Alpine behaviour ของ Blade component ใน resources/views/components/ui
 * ครอบคลุม focus trap, Escape, การคืนโฟกัส, คีย์ลูกศร และ ARIA state
 */
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const focusablesIn = (root) => [...root.querySelectorAll(FOCUSABLE)].filter((el) => el.offsetParent !== null || el === document.activeElement);

function trapTab(event, root) {
    const items = focusablesIn(root);
    if (!items.length) {
        event.preventDefault();
        return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

/** Modal / Drawer ที่เปิดด้วย event: window.dispatchEvent(new CustomEvent('open-modal', { detail: 'name' })) */
function overlay({ name, show = false, openEvent = 'open-modal', closeEvent = 'close-modal' }) {
    return {
        name,
        show,
        returnFocus: null,

        init() {
            window.addEventListener(openEvent, (event) => { if (event.detail === this.name) this.open(); });
            window.addEventListener(closeEvent, (event) => { if (event.detail === this.name) this.close(); });
            this.$watch('show', (value) => {
                document.documentElement.classList.toggle('overflow-hidden', value);
                if (value) {
                    this.$nextTick(() => (this.$refs.panel.querySelector('[autofocus]') || focusablesIn(this.$refs.panel)[0] || this.$refs.panel).focus());
                } else if (this.returnFocus) {
                    const target = this.returnFocus;
                    this.returnFocus = null;
                    this.$nextTick(() => target.focus?.());
                }
            });
            if (this.show) this.$nextTick(() => focusablesIn(this.$refs.panel)[0]?.focus());
        },

        open() {
            this.returnFocus = document.activeElement;
            this.show = true;
        },

        close() {
            this.show = false;
        },

        onKeydown(event) {
            if (!this.show) return;
            if (event.key === 'Escape') {
                event.stopPropagation();
                this.close();
            } else if (event.key === 'Tab') {
                trapTab(event, this.$refs.panel);
            }
        },
    };
}

export function registerComponents(Alpine) {
    Alpine.data('spModal', (config) => overlay({ ...config, openEvent: 'open-modal', closeEvent: 'close-modal' }));
    Alpine.data('spDrawer', (config) => overlay({ ...config, openEvent: 'open-drawer', closeEvent: 'close-drawer' }));

    /** กล่องยืนยันกลาง (store "confirm") */
    Alpine.data('spConfirmDialog', () => ({
        get store() { return Alpine.store('confirm'); },
        init() {
            this.$watch('store.open', (value) => {
                document.documentElement.classList.toggle('overflow-hidden', value);
                if (value) this.$nextTick(() => this.$refs.cancel.focus());
            });
        },
        onKeydown(event) {
            if (!this.store.open) return;
            if (event.key === 'Escape') {
                event.stopPropagation();
                this.store.settle(false);
            } else if (event.key === 'Tab') {
                trapTab(event, this.$refs.panel);
            }
        },
    }));

    /** Dropdown menu: Enter/Space/ArrowDown เปิด · ลูกศรเลื่อน · Escape ปิดและคืนโฟกัส */
    Alpine.data('spDropdown', () => ({
        open: false,

        get items() {
            return [...this.$refs.menu.querySelectorAll('[role="menuitem"]:not([aria-disabled="true"])')];
        },

        get trigger() {
            return this.$refs.trigger.querySelector('button, a, [tabindex]') || this.$refs.trigger;
        },

        init() {
            const trigger = this.trigger;
            trigger.setAttribute('aria-haspopup', 'menu');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.setAttribute('aria-controls', this.$refs.menu.id);
            this.$watch('open', (value) => trigger.setAttribute('aria-expanded', String(value)));
        },

        toggle() {
            this.open ? this.close() : this.openMenu();
        },

        openMenu(focusLast = false) {
            this.open = true;
            this.$nextTick(() => (focusLast ? this.items.at(-1) : this.items[0])?.focus());
        },

        close(returnFocus = true) {
            if (!this.open) return;
            this.open = false;
            if (returnFocus) this.trigger.focus();
        },

        onTriggerKeydown(event) {
            if (['ArrowDown', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                this.openMenu();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.openMenu(true);
            }
        },

        onMenuKeydown(event) {
            const items = this.items;
            const index = items.indexOf(document.activeElement);
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                items[(index + 1) % items.length]?.focus();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                items[(index - 1 + items.length) % items.length]?.focus();
            } else if (event.key === 'Home') {
                event.preventDefault();
                items[0]?.focus();
            } else if (event.key === 'End') {
                event.preventDefault();
                items.at(-1)?.focus();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                this.close();
            } else if (event.key === 'Tab') {
                this.close(false);
            }
        },
    }));

    /** Tabs: roving tabindex + ลูกศรซ้าย/ขวา/Home/End (เปิดแท็บทันทีที่โฟกัส) */
    Alpine.data('spTabs', ({ active, keys }) => ({
        active,
        keys,

        select(key, focus = false) {
            this.active = key;
            if (focus) this.$nextTick(() => this.$root.querySelector(`[data-tab="${key}"]`)?.focus());
        },

        onKeydown(event) {
            const index = this.keys.indexOf(this.active);
            const map = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: this.keys.length - 1 };
            if (!(event.key in map)) return;
            event.preventDefault();
            this.select(this.keys[(map[event.key] + this.keys.length) % this.keys.length], true);
        },
    }));

    /** Tooltip: แสดงเมื่อ hover หรือ focus · Escape ซ่อน · ผูก aria-describedby ให้ตัวกระตุ้น */
    Alpine.data('spTooltip', ({ id }) => ({
        visible: false,
        init() {
            const target = this.$refs.target.querySelector('button, a, input, select, textarea, [tabindex]') || this.$refs.target;
            const ids = new Set((target.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
            ids.add(id);
            target.setAttribute('aria-describedby', [...ids].join(' '));
        },
        showTip() { this.visible = true; },
        hideTip() { this.visible = false; },
    }));
}
