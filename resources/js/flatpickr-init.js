import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.css';
import { Thai } from 'flatpickr/dist/l10n/th.js';

flatpickr.localize(Thai);
flatpickr.defaultConfig.locale = Thai;

/**
 * เปิด popup ปฏิทินให้ input วันที่/เวลาทุกช่องในระบบ แทนตัวเลือกวันที่แบบ native ของ browser
 * ที่ format แสดงผลไม่แน่นอน (ขึ้นกับ locale เครื่องผู้ใช้ เช่น MM/DD/YYYY แบบสหรัฐฯ) — ใช้ altInput
 * เพื่อโชว์ผู้ใช้เป็น d/m/Y (H:i) แบบไทยเสมอ ในขณะที่ค่าที่ส่งจริงไปยัง backend ยังเป็น ISO 8601
 * ที่ไม่กำกวมและ parse ได้ตรงเป๊ะ (Y-m-d หรือ Y-m-d\TH:i)
 */
function initFlatpickrInputs() {
    document.querySelectorAll('[data-flatpickr="datetime"]').forEach((el) => {
        if (el._flatpickr) return;
        flatpickr(el, {
            enableTime: true,
            time_24hr: true,
            dateFormat: 'Y-m-d\\TH:i',
            altInput: true,
            altFormat: 'd/m/Y H:i',
            minDate: el.min || el.dataset.min || undefined,
            defaultDate: el.value || undefined,
            disableMobile: true,
        });
    });

    document.querySelectorAll('[data-flatpickr="date"]').forEach((el) => {
        if (el._flatpickr) return;
        flatpickr(el, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            defaultDate: el.value || undefined,
            disableMobile: true,
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFlatpickrInputs);
} else {
    initFlatpickrInputs();
}
