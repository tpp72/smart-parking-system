/**
 * จัดรูปแบบเลขทะเบียนขณะพิมพ์ — ต้องให้ผลตรงกับ App\Support\LicensePlateNormalizer ฝั่งเซิร์ฟเวอร์
 *
 * ฝั่งนี้เป็นแค่ตัวช่วยให้ผู้ใช้เห็นรูปแบบที่ระบบจะเก็บจริง ไม่ใช่ตัวตัดสิน
 * เซิร์ฟเวอร์จัดรูปแบบซ้ำเสมอ และไม่เชื่อค่าที่ส่งมาจากเบราว์เซอร์
 *
 * กติกาเดียวกับฝั่ง PHP:
 *   - ตัวคั่นทุกแบบ (ช่องว่าง ยัติภังค์ ขีดล่าง) และที่ซ้อนกัน → ช่องว่างเดียว
 *   - เลขไทย ๐–๙ → 0–9 · อักษรละตินเป็นตัวพิมพ์ใหญ่
 *   - เติมช่องว่างให้เฉพาะรูปแบบที่แยกหมวดกับเลขได้แน่ชัด (พยัญชนะไทยล้วน/ละตินล้วน แล้วตามด้วยเลข)
 *   - ทะเบียนลักษณะพิเศษที่มีสระหรือวรรณยุกต์ ไม่ถูกเดาตัวคั่น
 */

const THAI_DIGITS = /[๐-๙]/g;

// [ก-ฮ] = พยัญชนะไทย · สระและวรรณยุกต์อยู่นอกช่วงนี้ จึงไม่เข้าเงื่อนไขแยกอัตโนมัติ
const SPLITTABLE = [
    /^(\d?[ก-ฮ]{1,3})(\d{1,4})$/u,
    /^(\d?[A-Z]{1,3})(\d{1,4})$/u,
];

export function normalizePlate(input) {
    if (input == null) return '';

    let value = String(input).normalize('NFC');
    value = value.replace(THAI_DIGITS, (d) => String(d.charCodeAt(0) - 0x0e50));
    value = value.toUpperCase();
    value = value.replace(/[\s\-_ ]+/gu, ' ').trim();

    if (value === '' || value.includes(' ')) return value;

    for (const pattern of SPLITTABLE) {
        const m = value.match(pattern);
        if (m) return `${m[1]} ${m[2]}`;
    }

    return value;
}

// อักขระที่เป็นส่วนของทะเบียนได้ — ตรงกับ LicensePlateNormalizer::ALLOWED_PATTERN (บวกยัติภังค์ที่ผู้ใช้พิมพ์ได้ก่อนถูกแปลงเป็นช่องว่าง)
const DISALLOWED = /[^฀-๿A-Za-z0-9 \-]/gu;

/** ตัดอักขระที่เป็นทะเบียนไม่ได้ทิ้งระหว่างพิมพ์ โดยไม่ให้เคอร์เซอร์กระโดด */
function filterWhileTyping(el) {
    const before = el.value;
    const after = before.replace(DISALLOWED, '');

    if (after === before) return;

    const caret = el.selectionStart ?? after.length;
    const removedBeforeCaret = before.slice(0, caret).replace(DISALLOWED, '').length;

    el.value = after;
    el.setSelectionRange(removedBeforeCaret, removedBeforeCaret);
}

/**
 * ผูกกับช่องกรอกทะเบียนทุกช่องที่มี data-plate-input
 *
 * ระหว่างพิมพ์: ตัดเฉพาะอักขระที่เป็นทะเบียนไม่ได้ (อีโมจิ เครื่องหมายวรรคตอน)
 * ออกจากช่อง/ส่งฟอร์ม: จัดรูปแบบให้ตรงกับที่ระบบจะเก็บจริง
 *
 * ไม่บังคับรูปแบบทะเบียนที่นี่ — เงื่อนไข "ต้องมีตัวเลข" ตรวจที่เซิร์ฟเวอร์ เพื่อไม่ขัดจังหวะขณะพิมพ์
 */
export function initPlateInputs(root = document) {
    root.querySelectorAll('input[data-plate-input]').forEach((el) => {
        if (el.dataset.plateBound) return;
        el.dataset.plateBound = '1';

        const apply = () => {
            const next = normalizePlate(el.value);
            if (next !== el.value) el.value = next;
        };

        el.addEventListener('input', () => filterWhileTyping(el));
        el.addEventListener('blur', apply);
        el.form?.addEventListener('submit', apply);
    });
}
