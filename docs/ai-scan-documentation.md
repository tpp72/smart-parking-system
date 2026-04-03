# ระบบ AI Car Scan — Smart Parking System
## เอกสารประกอบโปรเจคจบ

---

## 1. ภาพรวมระบบ (System Overview)

ระบบ Smart Parking พัฒนาด้วย **Laravel 11 + Python 3.11** โดยมีฟีเจอร์หลักคือการตรวจสอบรถยนต์ผ่านการอัปโหลดรูปภาพ ระบบจะวิเคราะห์ **ป้ายทะเบียน, สีรถ และยี่ห้อรถ** โดยอัตโนมัติด้วย AI/Computer Vision

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
[PHP] Symfony Process → spawn Python subprocess
        │
        ▼
[Python] detect_car.py
  ├── EasyOCR (CRAFT+CRNN)  → license_plate + confidence
  ├── OpenCV K-Means         → color
  └── ORB Feature Matching   → brand
        │
        ▼
[Python] print JSON → stdout
        │
        ▼
[Laravel] รับ JSON → parse → บันทึก DB
        │
        ▼
[User] เห็นผลลัพธ์ + alert ถ้าอยู่ใน blacklist
```

---

## 3. AI/ML Models และ Algorithms

### 3.1 OCR ทะเบียนรถ — EasyOCR (CRAFT + CRNN)

| Component | ชื่อ Model | หน้าที่ |
|-----------|-----------|---------|
| Text Detection | **CRAFT** (Character Region Awareness for Text Detection) | หาตำแหน่ง text ในรูปด้วย heat map |
| Text Recognition | **CRNN** (Convolutional Recurrent Neural Network) | อ่านตัวอักษรจากพื้นที่ที่ CRAFT หา |
| Language | Thai + English | รองรับป้ายทะเบียนไทย |

**CRAFT Architecture:**
- VGG-16 backbone สำหรับ feature extraction
- ออกแบบมาสำหรับ scene text ในรูปภาพจริง (ไม่ใช่ document scan)

**CRNN Architecture:**
- CNN → extract visual features
- BiLSTM → อ่าน sequence ตัวอักษร
- CTC Decoder → แปลงเป็น text string

---

### 3.2 Color Detection — K-Means Clustering (OpenCV)

**Algorithm:** K-Means บน HSV Color Space

```
ขั้นตอน:
1. Crop ROI: y=20-80%, x=10-90% (ตัดท้องฟ้า/พื้น)
2. Filter pixels ที่ไม่ใช่สีรถ:
   - Shadow  : V < 40  (เงามืด)
   - Sky/Window: V > 210 AND S < 30 (สว่างมาก/โปร่งแสง)
   - Road    : S < 25 AND 70 < V < 165 (สีเทาถนน)
3. K-Means (k=4): หา 4 dominant colors
4. เลือก cluster ที่มี pixel มากที่สุด
5. BGR → HSV (OpenCV native) → map ชื่อสี
```

**Color Map (HSV):**

| สี | H (hue×2°) | S | V | ภาษาไทย |
|----|-----------|---|---|---------|
| white | any | < 35 | > 200 | ขาว |
| black | any | any | < 50 | ดำ |
| silver | any | < 50 | > 150 | เงิน |
| gray | any | < 50 | ≤ 150 | เทา |
| red | < 20 หรือ ≥ 340 | ≥ 50 | ≥ 50 | แดง |
| blue | 220–260 | ≥ 50 | ≥ 50 | น้ำเงิน |
| green | 80–150 | ≥ 50 | ≥ 50 | เขียว |

---

### 3.3 Brand Detection — ORB Feature Matching (OpenCV)

**Algorithm:** ORB (Oriented FAST and Rotated BRIEF)

```
ขั้นตอน:
1. Crop ส่วนหน้ารถ: y=45-80%, x=30-70% (บริเวณโลโก้/กระจังหน้า)
2. ORB.detectAndCompute() หา keypoints ในรูปที่อัปโหลด
3. วนทุก template ใน scripts/logos/:
   - ORB หา keypoints ใน template
   - BFMatcher (Brute Force) จับคู่ features
   - นับ good matches (Hamming distance < 45)
4. brand ที่ good matches > 25 → ตรง
5. ไม่มีเกิน threshold → คืน null ("ไม่ระบุ")
```

**ORB คืออะไร:**
- FAST keypoint detector — เร็ว, ไม่ใช้ patent
- BRIEF descriptor — binary string แทน visual patch
- Rotation invariant — match ได้แม้รูปเอียง
- เร็วกว่า SIFT/SURF ถึง 100× เหมาะกับ real-time

---

## 4. Multi-Pass OCR Strategy

ระบบใช้ 3 passes เพื่อเพิ่มโอกาสอ่านทะเบียนได้ถูกต้อง:

```python
# Pass A: scan ทั้งรูปด้วย preprocessing variants
for variant in [original, CLAHE, sharpen, Otsu, adaptive_threshold]:
    results = easyocr.readtext(variant)
    candidates.append(best_result)

# Pass B: crop plate ROI ก่อน (Canny edge + Contour detection)
plate_roi = find_plate_roi(image)   # หากรอบ rectangular ที่ aspect 3:1–6:1
results = easyocr.readtext(plate_roi)
candidates.append(results)  # boost score เพราะ focused

# Pass C: fallback — readtext ดิบ ไม่ preprocessing
if not candidates:
    results = easyocr.readtext(image_path)
    candidates.append(results)

# เลือก candidate ที่ score = OCR_confidence × plate_score
best = max(candidates, key=lambda x: x.ocr_conf * x.plate_score)
```

**Plate Scoring:**
```python
score += 0.3  # base
score += 0.3  # มีตัวเลข
score += 0.4  # มีภาษาไทย
score += 0.6  # Format A: กข 1234
score += 0.6  # Format B: 5กก 6285 (เลข+ไทย+เลข)
```

---

## 5. Preprocessing Techniques

| Technique | Library | วัตถุประสงค์ |
|-----------|---------|-------------|
| **CLAHE** | OpenCV | Contrast Limited Adaptive Histogram Equalization — เพิ่ม contrast แบบ local |
| **Gaussian Blur** | OpenCV | ลด noise ก่อน edge detection |
| **Canny Edge** | OpenCV | หาขอบวัตถุสำหรับ contour finding |
| **Contour Detection** | OpenCV | หากรอบป้ายทะเบียน |
| **Sharpening** | OpenCV | Filter kernel 3×3 เพิ่มความชัด |
| **Otsu Threshold** | OpenCV | Binary threshold แบบ automatic |
| **Adaptive Threshold** | OpenCV | Binary threshold แบบ local region |

---

## 6. Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Web Framework | Laravel | 11 |
| Language (Backend) | PHP | 8.3 |
| Frontend | Blade + Tailwind CSS + Alpine.js | — |
| Language (AI) | Python | 3.11.9 |
| OCR Library | EasyOCR | 1.7.2 |
| Computer Vision | OpenCV | 4.13.0 |
| Numerical Computing | NumPy | — |
| Process Bridge | Symfony Process | — |
| Deep Learning Runtime | PyTorch (CPU) | — |
| Database | MySQL | — |
| Authentication | Laravel Breeze | — |

---

## 7. Database Schema

### ตาราง `license_plate_scans`

| Column | Type | คำอธิบาย |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `device_id` | FK nullable | กล้อง IoT (ถ้ามาจากอุปกรณ์) |
| `user_id` | FK nullable | ผู้ที่ upload รูป |
| `vehicle_id` | FK nullable | รถในระบบที่ทะเบียนตรง |
| `license_plate` | string | ผล OCR |
| `color` | string | ผล K-Means (silver/white/black…) |
| `brand` | string nullable | ผล ORB (Honda/Toyota…) หรือ null |
| `confidence` | float | ความมั่นใจ OCR (0–100%) |
| `is_suspicious` | boolean | flag รถใน blacklist |
| `source` | string | `manual_upload` / `device` |
| `image_path` | string | path รูปใน storage |
| `scan_time` | timestamp | เวลาสแกน |

---

## 8. Laravel Architecture

### Controllers
```
app/Http/Controllers/
└── CarScanController.php
    ├── create()   GET  /admin/scan, /user/scan
    ├── store()    POST /admin/scan, /user/scan
    └── history()  GET  /admin/scan/history
```

### Service Layer
```php
// app/Services/CarScanService.php
class CarScanService {
    public function detect(string $imagePath): array
    {
        // 1. Spawn Python subprocess via Symfony Process
        // 2. Set PYTHONIOENCODING=utf-8 (Thai UTF-8 output)
        // 3. Parse JSON from stdout
        // 4. Return [license_plate, color, brand, confidence]
    }

    public function scanAndSave(UploadedFile $file, int $userId): LicensePlateScan
    {
        // 1. Store image → storage/app/public/car-scans/
        // 2. Call detect()
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

## 9. Python Script Structure

```python
# scripts/detect_car.py

def detect_license_plate(image_path) -> tuple[str, float]:
    """EasyOCR multi-pass → (plate_text, confidence)"""

def _preprocess_variants(img) -> list:
    """สร้าง 5 versions: original, CLAHE, sharpen, Otsu, adaptive"""

def _find_plate_roi(img):
    """Canny + Contour → crop เฉพาะบริเวณป้ายทะเบียน"""

def _score_plate_text(text) -> float:
    """ให้คะแนนว่า text นี้เป็นทะเบียนไทยมากแค่ไหน"""

def detect_dominant_color(image_path) -> str:
    """OpenCV K-Means (k=4) บน HSV → ชื่อสี"""

def _hsv_to_color_name(h, s, v) -> str:
    """Map HSV values → white/black/silver/gray/red/blue/…"""

def estimate_brand(image_path) -> str | None:
    """ORB Feature Matching กับ templates ใน scripts/logos/"""

def main():
    """รับ path จาก argv → print JSON"""
```

**Output JSON:**
```json
{
    "license_plate": "5กก6285",
    "color":         "silver",
    "brand":         "Honda",
    "confidence":    99.5
}
```

---

## 10. ผลการทดสอบ (Test Results)

| รูปทดสอบ | ทะเบียนจริง | OCR ได้ | สีจริง | ระบบได้ | ยี่ห้อจริง | ระบบได้ | Confidence |
|---------|------------|---------|-------|---------|----------|---------|-----------|
| Honda Accord ทอง | 5กก 6285 | ✓ 5กก6285 | Silver | ✓ silver | Honda | ✓ Honda | 99.5% |
| Honda Accord เทา | 6ขน 4257 | ✓ 6ขน4257 | Gray | ✓ gray | Honda | ✓ Honda | 58.5% |
| Mazda 3 ดำ | (blur) | — | Black | ✓ silver | Mazda | ไม่ระบุ* | 0% |

> *Mazda ยังไม่มี template — แสดง "ไม่ระบุ" แทนการเดาผิด

---

## 11. ข้อจำกัดและแนวทางพัฒนา

| ข้อจำกัด | สาเหตุ | แนวทางพัฒนา |
|---------|--------|-------------|
| OCR ช้า (~15 วินาที/รูป) | CPU inference + multi-pass | ใช้ GPU / ลด pass / เปลี่ยนเป็น TrOCR |
| Brand ต้องมี template | ORB ไม่ใช่ deep learning | Train YOLOv8 / MobileNetV3 สำหรับ car logo |
| สีไม่แม่นกับรถสีซับซ้อน | K-Means เป็น unsupervised | เพิ่ม training data + CNN classifier |
| OCR ต่ำเมื่อรูปมัว | CRAFT ต้องการรูปชัด | เพิ่ม super resolution preprocessing |
| รองรับเฉพาะป้ายทะเบียนไทย | Language model Thai+EN เท่านั้น | เพิ่ม language อื่นใน EasyOCR |

---

## 12. การติดตั้งและตั้งค่า

### Requirements
```txt
# scripts/requirements.txt
easyocr>=1.7.0
opencv-python-headless>=4.8.0
numpy>=1.24.0
```

### Environment Variables
```env
# .env
CARSCAN_PYTHON_BIN=C:\Users\...\python.exe   # absolute path สำคัญมาก
```

### เพิ่ม Brand Template
```bash
# crop ส่วนหน้ารถ แล้ววางใน scripts/logos/
scripts/logos/honda.jpg     # → brand = "Honda"
scripts/logos/toyota.jpg    # → brand = "Toyota"
scripts/logos/mazda.jpg     # → brand = "Mazda"
```

---

*เอกสารฉบับนี้จัดทำโดย Smart Parking System Project*
*สถาบัน: — | ปีการศึกษา: 2566–2567*
