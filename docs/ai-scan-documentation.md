# ระบบ AI Car Scan — Smart Parking System
## เอกสารประกอบโปรเจคจบ

---

## 1. ภาพรวมระบบ (System Overview)

ระบบ Smart Parking พัฒนาด้วย **Laravel 11** โดยมีฟีเจอร์หลักคือการตรวจสอบรถยนต์ผ่านการอัปโหลดรูปภาพ ระบบจะวิเคราะห์ **ป้ายทะเบียน, สีรถ และยี่ห้อรถ** โดยอัตโนมัติผ่าน **Google Gemini Vision API**

---

## 2. System Flow

```
[User] อัปโหลดรูปรถ
        │
        ▼
[Laravel] CarScanController
  ├── Validate: jpg/png ≤ 5MB
  ├── Store: storage/app/public/car-scans/
  └── เรียก CarScanService
        │
        ▼
[PHP] Laravel HTTP Client
  └── POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent
        │   ├── image (base64 inline)
        │   └── prompt (Thai JSON instruction)
        │
        ▼
[Google Gemini Vision API]
  ├── วิเคราะห์ป้ายทะเบียน (Thai OCR)
  ├── วิเคราะห์สีรถ
  └── วิเคราะห์ยี่ห้อรถ
        │
        ▼
[Gemini] ตอบกลับ JSON
        │
        ▼
[Laravel] รับ JSON → parse → บันทึก DB
        │
        ▼
[User] เห็นผลลัพธ์ + alert ถ้าอยู่ใน blacklist
```

---

## 3. AI Model — Google Gemini Vision

### 3.1 ภาพรวม Gemini Vision

| รายการ | รายละเอียด |
|--------|-----------|
| **Provider** | Google DeepMind |
| **Model** | Gemini 2.5 Flash (default) |
| **ความสามารถ** | Multimodal — รับ text + image พร้อมกัน |
| **OCR** | รองรับภาษาไทย + อังกฤษ + ตัวเลข |
| **Vision** | วิเคราะห์วัตถุ, สี, ยี่ห้อ, โลโก้ |
| **API Version** | v1beta |

### 3.2 Gemini Architecture (ภาพรวม)

```
Input Image (base64) ──┐
                        ├──► Gemini Multimodal Transformer ──► JSON Response
Thai Prompt ───────────┘         (Vision + Language Model)
```

- **Multimodal Transformer** รับ image tokens + text tokens พร้อมกัน
- **Vision Encoder** แปลงรูปภาพเป็น embeddings
- **Language Model** ตีความ prompt + สร้าง structured output
- รองรับ `responseMimeType: application/json` → บังคับ output เป็น JSON

---

## 4. API Request/Response

### Request Format

```http
POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=API_KEY

{
  "contents": [{
    "parts": [
      {
        "inlineData": {
          "mimeType": "image/jpeg",
          "data": "<base64_encoded_image>"
        }
      },
      {
        "text": "วิเคราะห์รูปรถยนต์นี้แล้วตอบกลับเป็น JSON..."
      }
    ]
  }],
  "generationConfig": {
    "maxOutputTokens": 1024,
    "temperature": 0.1,
    "responseMimeType": "application/json"
  }
}
```

### Response Format

```json
{
  "candidates": [{
    "content": {
      "parts": [{
        "text": "{\"license_plate\":\"5กก 6285\",\"color\":\"เงิน\",\"brand\":\"Honda\",\"confidence\":95}"
      }]
    }
  }]
}
```

### Output JSON

```json
{
  "license_plate": "5กก 6285",
  "color":         "เงิน",
  "brand":         "Honda",
  "confidence":    95
}
```

---

## 5. Prompt Engineering

### กลยุทธ์ Prompt

```
วิเคราะห์รูปรถยนต์นี้แล้วตอบกลับเป็น JSON เท่านั้น ไม่มีข้อความอื่น ไม่มี markdown:

{
  "license_plate": "ป้ายทะเบียนรถ เช่น กข 1234 หรือ 5กก 6285 ถ้าไม่เห็นให้ใส่ค่าว่าง",
  "color": "สีตัวถังรถหลักเป็นภาษาไทย เช่น ขาว ดำ แดง น้ำเงิน เทา เงิน เขียว...",
  "brand": "ยี่ห้อรถ เช่น Toyota Honda Mazda... ถ้าไม่แน่ใจให้ใส่ null",
  "confidence": ตัวเลข 0-100 บอกความมั่นใจในการอ่านป้ายทะเบียน
}
```

**เหตุผลที่ใช้ `temperature: 0.1`** — ลด creativity ให้ผลลัพธ์ stable ไม่สุ่มเปลี่ยน  
**เหตุผลที่ใช้ `responseMimeType: application/json`** — บังคับให้ Gemini ส่ง valid JSON เสมอ

---

## 6. Tech Stack

| Layer | Technology | Version | ใช้ทำอะไร |
|-------|-----------|---------|----------|
| Web Framework | Laravel | 11 | Web framework (MVC) |
| Language (Backend) | PHP | 8.4 | Backend language |
| HTTP Client | Laravel HTTP (Guzzle) | — | เรียก Gemini REST API |
| **AI Vision** | **Google Gemini** | **2.5 Flash** | **วิเคราะห์รูปรถ** |
| Database | PostgreSQL | 15+ | บันทึกผลสแกน |
| Authentication | Laravel Breeze | — | ระบบ Login |

---

## 7. Database Schema

### ตาราง `license_plate_scans`

| Column | Type | คำอธิบาย |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `device_id` | FK nullable | กล้อง IoT (ถ้ามาจากอุปกรณ์) |
| `user_id` | FK nullable | ผู้ที่ upload รูป |
| `vehicle_id` | FK nullable | รถในระบบที่ทะเบียนตรง |
| `license_plate` | string | ผล OCR จาก Gemini |
| `color` | string | สีรถภาษาไทย |
| `brand` | string nullable | ยี่ห้อรถ หรือ null |
| `confidence` | float | ความมั่นใจ 0–100 |
| `is_suspicious` | boolean | flag รถใน blacklist |
| `source` | string | `manual_upload` / `device` |
| `image_path` | string | path รูปใน storage |
| `scan_time` | timestamp | เวลาสแกน |

---

## 8. Laravel Architecture

### Service Layer

```php
// app/Services/CarScanService.php
class CarScanService {

    public function detect(string $imagePath): array
    {
        // 1. อ่านรูป → base64
        // 2. POST → Gemini Vision API (Laravel HTTP Client)
        // 3. Parse JSON response
        // 4. Return [license_plate, color, brand, confidence]
    }

    public function scanAndSave(UploadedFile $file, int $userId): LicensePlateScan
    {
        // 1. Store image → storage/app/public/car-scans/
        // 2. Call detect() → Gemini API
        // 3. Match license plate → vehicles table
        // 4. Check blacklist → suspicious_vehicles table
        // 5. Save to license_plate_scans
    }
}
```

### Routes

```php
// Admin
Route::prefix('admin')->middleware(['auth','role:admin'])->group(function () {
    Route::get('scan',         [CarScanController::class, 'create'])->name('admin.scan.create');
    Route::post('scan',        [CarScanController::class, 'store'])->name('admin.scan.store');
    Route::get('scan/history', [CarScanController::class, 'history'])->name('admin.scan.history');
});

// User
Route::prefix('user')->middleware(['auth','role:user'])->group(function () {
    Route::get('scan',  [CarScanController::class, 'create'])->name('user.scan.create');
    Route::post('scan', [CarScanController::class, 'store'])->name('user.scan.store');
});
```

---

## 9. ผลการทดสอบ (Test Results)

| รูปทดสอบ | ทะเบียนจริง | OCR ได้ | สีจริง | ระบบได้ | ยี่ห้อจริง | ระบบได้ | Confidence |
|---------|------------|---------|-------|---------|----------|---------|-----------|
| Honda Accord สีเงิน | 5กก 6285 | ✓ 5กก 6285 | เงิน | ✓ เงิน | Honda | ✓ Honda | 95% |
| Honda สีเทา | 6ขน 4257 | ✓ 6ขน 4257 | เทา | ✓ เทา | Honda | ✓ Honda | 90% |
| รถไม่ชัด | — | — | — | — | — | null | 0% |

---

## 10. เปรียบเทียบก่อน/หลัง

| รายการ | เดิม (Python Local) | ปัจจุบัน (Gemini API) |
|--------|--------------------|-----------------------|
| เวลาวิเคราะห์ | ~15–30 วินาที | ~3–5 วินาที |
| ความแม่นยำ OCR ไทย | ปานกลาง | สูงมาก |
| ความแม่นยำสีรถ | ปานกลาง | สูงมาก |
| ความแม่นยำยี่ห้อ | ต้องมี template | ไม่ต้องมี template |
| Dependencies | Python 3.11, EasyOCR, OpenCV, PyTorch | ไม่มี |
| การติดตั้ง | ซับซ้อน (หลาย package) | แค่ GEMINI_API_KEY |
| Free Quota | ไม่มี (ใช้ CPU เครื่อง) | 1,500 req/วัน |

---

## 11. ข้อจำกัดและแนวทางพัฒนา

| ข้อจำกัด | สาเหตุ | แนวทางพัฒนา |
|---------|--------|-------------|
| ต้องการ internet | เรียก Cloud API | Cache ผลลัพธ์, fallback offline |
| Free Tier 1,500 req/วัน | Gemini Free Quota | Upgrade เป็น Pay-as-you-go |
| ขึ้นอยู่กับ Google | External dependency | เพิ่ม fallback model (Gemini Pro) |
| ข้อมูลรูปส่งออก cloud | Privacy concern | ใช้ Vertex AI on-premise แทน |

---

## 12. การติดตั้งและตั้งค่า

### Environment Variables

```env
# .env
GEMINI_API_KEY=AIzaSy...        # ขอได้ฟรีที่ aistudio.google.com/apikey
CARSCAN_MODEL=gemini-2.5-flash  # หรือ gemini-2.5-pro สำหรับความแม่นสูงสุด
```

### โมเดลที่ใช้ได้

| Model ID | ความเร็ว | ความแม่น | Free Quota |
|----------|---------|---------|-----------|
| `gemini-2.5-flash` | เร็ว | ดี | 1,500/วัน ✅ แนะนำ |
| `gemini-2.5-flash-lite` | เร็วสุด | ปานกลาง | 1,500/วัน |
| `gemini-2.5-pro` | ช้า | ดีสุด | น้อยกว่า |

### ไม่ต้องติดตั้งอะไรเพิ่ม
- Laravel HTTP Client (Guzzle) มีอยู่แล้วใน Laravel
- ไม่ต้อง Python, ไม่ต้อง pip install

---

*เอกสารฉบับนี้จัดทำโดย Smart Parking System Project*
*สถาบัน: — | ปีการศึกษา: 2566–2567*
