/**
 * ช่องเลือกภาพรถของหน้าสแกน — ย่อรูปในเบราว์เซอร์ก่อนส่ง
 *
 * รูปจากมือถือมักใหญ่ 2–5 MB ซึ่งเกินขีดจำกัดอัปโหลดของ PHP ในหลายเครื่อง (ค่าเริ่มต้น 2 MB)
 * ย่อด้านยาวเหลือ MAX_EDGE แล้วบีบเป็น JPEG ได้ไฟล์ราว 300–600 KB
 * ความแม่นยำของ AI ไม่ลด เพราะ Claude Vision ย่อรูปที่ด้านยาวเกินราว 1568px ลงเองอยู่แล้ว
 *
 * รูปที่เล็กอยู่แล้วส่งตามเดิม (รวมไฟล์ทดสอบ E2E ที่ใช้ชื่อไฟล์เป็นผลของ AI โหมดจำลอง)
 * ย่อไม่ได้ด้วยเหตุใดก็ตาม → ส่งไฟล์เดิม ให้เซิร์ฟเวอร์ตัดสินเอง
 */
const MAX_EDGE = 1600;
const QUALITY = 0.85;
const SMALL_ENOUGH = 1.5 * 1024 * 1024;

function loadImage(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('อ่านรูปไม่ได้')); };
        img.src = url;
    });
}

async function shrink(file) {
    const img = await loadImage(file);
    const longEdge = Math.max(img.naturalWidth, img.naturalHeight);

    if (longEdge <= MAX_EDGE && file.size <= SMALL_ENOUGH) {
        return file;
    }

    const scale = Math.min(1, MAX_EDGE / longEdge);
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(img.naturalWidth * scale);
    canvas.height = Math.round(img.naturalHeight * scale);

    const ctx = canvas.getContext('2d');
    // PNG ที่มีพื้นโปร่งใสจะกลายเป็นพื้นดำเมื่อเป็น JPEG — ปูพื้นขาวก่อน
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY));

    if (!blob || blob.size >= file.size) {
        return file;
    }

    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';

    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
}

export function carImage() {
    return {
        preview: null,
        fileName: '',
        note: '',
        busy: false,

        async handleFile(event) {
            const input = event.target;
            const original = input.files[0];

            if (!original) {
                this.preview = null;
                this.fileName = '';
                this.note = '';
                return;
            }

            this.busy = true;
            let file = original;

            try {
                file = await shrink(original);

                if (file !== original) {
                    // แทนไฟล์ในช่องเลือกด้วยรูปที่ย่อแล้ว ฟอร์มจึงส่งแบบ multipart ปกติได้
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    input.files = transfer.files;
                }
            } catch {
                file = original;
            }

            this.fileName = file.name;
            this.note = file !== original
                ? `ย่อรูปจาก ${(original.size / 1048576).toFixed(1)} MB เหลือ ${Math.max(0.1, file.size / 1048576).toFixed(1)} MB`
                : '';

            const reader = new FileReader();
            reader.onload = (e) => { this.preview = e.target.result; };
            reader.readAsDataURL(file);

            this.busy = false;
        },
    };
}
