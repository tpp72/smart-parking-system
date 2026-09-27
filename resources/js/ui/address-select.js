/**
 * ที่อยู่แบบเลือกต่อกันเป็นชั้น: จังหวัด → อำเภอ/เขต → ตำบล/แขวง → รหัสไปรษณีย์
 *
 * ข้อมูลโหลดจาก public/data/thai-geography.json ครั้งเดียวต่อหน้า (~54 KB เมื่อบีบอัด)
 * โหลดเมื่อเปิดหน้าที่มีฟอร์มเท่านั้น ไม่ได้ติดไปกับ bundle หลัก
 *
 * รหัสไปรษณีย์ผูกกับตำบล/แขวง จึงเติมให้ได้ต่อเมื่อเลือกครบทั้ง 3 ชั้น
 * ฝั่งเซิร์ฟเวอร์คำนวณรหัสใหม่เองเสมอ ไม่เชื่อค่าที่ส่งมาจากเบราว์เซอร์
 */
export function addressSelect(initial = {}) {
    return {
        geo: null,
        loading: true,
        province: initial.province || '',
        district: initial.district || '',
        subdistrict: initial.subdistrict || '',
        postalCode: initial.postalCode || '',

        async init() {
            try {
                const res = await fetch(this.$el.dataset.geoUrl, { headers: { Accept: 'application/json' } });
                this.geo = res.ok ? await res.json() : {};
            } catch {
                this.geo = {};
            }
            this.loading = false;
            this.syncPostalCode();
        },

        get districts() {
            return this.geo && this.province ? Object.keys(this.geo[this.province] || {}) : [];
        },

        get subdistricts() {
            return this.geo && this.province && this.district
                ? Object.keys((this.geo[this.province] || {})[this.district] || {})
                : [];
        },

        onProvinceChange() {
            this.district = '';
            this.subdistrict = '';
            this.syncPostalCode();
        },

        onDistrictChange() {
            this.subdistrict = '';
            this.syncPostalCode();
        },

        syncPostalCode() {
            const code = this.geo?.[this.province]?.[this.district]?.[this.subdistrict];
            this.postalCode = code ? String(code) : '';
        },
    };
}
