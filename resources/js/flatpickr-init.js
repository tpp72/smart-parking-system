import flatpickr from 'flatpickr';
import { Thai } from 'flatpickr/dist/l10n/th.js';

// ภาษาไทยของ flatpickr ไม่มีชื่อช่องสำหรับ screen reader (ค่าเริ่มต้นเป็นอังกฤษ "Year", "Hour") จึงเติมเอง
const ThaiLocale = {
    ...Thai,
    yearAriaLabel: 'ปี',
    monthAriaLabel: 'เดือน',
    hourAriaLabel: 'ชั่วโมง',
    minuteAriaLabel: 'นาที',
    toggleTitle: 'คลิกเพื่อสลับ',
};

flatpickr.localize(ThaiLocale);
flatpickr.defaultConfig.locale = ThaiLocale;

/**
 * altInput เป็นช่องใหม่ที่ผู้ใช้เห็นจริง แต่ <label for> ยังชี้ช่องเดิมที่ถูกซ่อน (id เดิมต้องคงไว้ให้ E2E/สคริปต์อื่นเรียก _flatpickr)
 * จึงคัดลอกชื่อช่อง คำอธิบาย และสถานะผิดพลาดไปให้ altInput เพื่อให้ screen reader อ่านได้
 */
function linkAltInput(el, instance) {
    const alt = instance.altInput;
    if (!alt) return;

    const label = el.id ? document.querySelector(`label[for="${CSS.escape(el.id)}"]`) : null;
    const name = label?.textContent.replace('*', '').replace(/\s+/g, ' ').trim();
    if (name) alt.setAttribute('aria-label', name);

    ['aria-describedby', 'aria-invalid', 'required'].forEach((attr) => {
        if (el.hasAttribute(attr)) alt.setAttribute(attr, el.getAttribute(attr));
    });

    // flatpickr ตั้ง readonly เพื่อบังคับให้เลือกจากปฏิทิน — ช่องนี้ใช้งานได้ จึงไม่ใช้พื้นสีเทาแบบช่องอ่านอย่างเดียว
    alt.classList.remove('read-only:bg-surface-2');
}

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
            maxDate: el.max || el.dataset.max || undefined,
            defaultDate: el.value || undefined,
            disableMobile: true,
            onReady: (_dates, _str, instance) => linkAltInput(el, instance),
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
            onReady: (_dates, _str, instance) => linkAltInput(el, instance),
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFlatpickrInputs);
} else {
    initFlatpickrInputs();
}
