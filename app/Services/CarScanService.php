<?php

namespace App\Services;

use App\Models\LicensePlateScan;
use App\Models\SuspiciousVehicle;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CarScanService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('carscan.gemini_api_key', '');
        $this->model  = config('carscan.model', 'gemini-2.5-flash');
    }

    /**
     * Send car image to Gemini Vision API and extract detection data.
     * Returns: license_plate, color, brand, confidence
     */
    public function detect(string $absoluteImagePath): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY ยังไม่ได้ตั้งค่าใน .env');
        }

        $imageData = base64_encode(file_get_contents($absoluteImagePath));
        $mimeType  = mime_content_type($absoluteImagePath) ?: 'image/jpeg';

        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            $mimeType = 'image/jpeg';
        }

        $prompt = <<<'PROMPT'
วิเคราะห์รูปรถยนต์นี้แล้วตอบกลับเป็น JSON เท่านั้น ไม่มีข้อความอื่น ไม่มี markdown:

{
  "license_plate": "ป้ายทะเบียนรถ เช่น กข 1234 หรือ 5กก 6285 ถ้าไม่เห็นให้ใส่ค่าว่าง",
  "color": "สีตัวถังรถหลักเป็นภาษาไทย เช่น ขาว ดำ แดง น้ำเงิน เทา เงิน เขียว ส้ม เหลือง ม่วง",
  "brand": "ยี่ห้อรถ เช่น Toyota Honda Mazda Isuzu Ford Mitsubishi Nissan Suzuki Hyundai KIA ถ้าไม่แน่ใจให้ใส่ null",
  "confidence": ตัวเลข 0-100 บอกความมั่นใจในการอ่านป้ายทะเบียน
}

หลักเกณฑ์:
- license_plate: อ่านตัวอักษรและเลขไทย/อังกฤษบนป้ายทะเบียนให้ครบ รูปแบบ "กข 1234" หรือ "5กก 6285"
- color: ดูสีตัวถังรถ ไม่ใช่สีกระจกหรือล้อ
- brand: ดูจากโลโก้หน้ารถหรือรูปทรง
- ตอบเป็น JSON เท่านั้น ไม่มี ```json ไม่มีคำอธิบายเพิ่ม
PROMPT;

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $this->model,
            $this->apiKey
        );

        $response = Http::timeout(30)->withOptions(['verify' => false])->post($url, [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data'     => $imageData,
                            ],
                        ],
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens'  => 1024,
                'temperature'      => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException('Gemini API error: ' . $error);
        }

        $text = $response->json('candidates.0.content.parts.0.text', '');
        Log::info('[CarScan] Gemini response: ' . substr($text, 0, 500));

        // Try direct JSON decode first (responseMimeType: application/json)
        $data = json_decode($text, true);
        if (is_array($data)) {
            return $data;
        }

        // Fallback: strip markdown fences and extract JSON object
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $data = json_decode($matches[0], true);
            if (is_array($data)) {
                return $data;
            }
        }

        throw new \RuntimeException('Gemini ตอบกลับรูปแบบไม่ถูกต้อง: ' . $text);
    }

    /**
     * Store the uploaded image, call Gemini API, persist scan record.
     * Returns the saved LicensePlateScan model.
     */
    public function scanAndSave(UploadedFile $file, int $userId): LicensePlateScan
    {
        // 1. Store file
        $storedPath   = $file->store('car-scans', 'public');
        $absolutePath = storage_path('app/public/' . $storedPath);

        // 2. Run AI (Gemini Vision)
        $result = $this->detect($absolutePath);

        $licensePlate = trim($result['license_plate'] ?? '');
        $color        = $result['color']       ?? null;
        $brand        = $result['brand']       ?? null;
        $confidence   = isset($result['confidence']) ? (float) $result['confidence'] : null;

        // 3. Match vehicle in DB
        $vehicleId = null;
        if ($licensePlate !== '') {
            $vehicle   = Vehicle::where('license_plate', $licensePlate)->first();
            $vehicleId = $vehicle?->id;

            if ($vehicle) {
                $updates = [];
                if ($color && !$vehicle->color) $updates['color'] = $color;
                if ($brand && !$vehicle->brand) $updates['brand'] = $brand;
                if ($updates) $vehicle->update($updates);
            }
        }

        // 4. Check blacklist
        $isSuspicious = $licensePlate !== ''
            && SuspiciousVehicle::where('license_plate', $licensePlate)->exists();

        // 5. Persist scan record
        $scan = LicensePlateScan::create([
            'device_id'     => null,
            'user_id'       => $userId,
            'vehicle_id'    => $vehicleId,
            'license_plate' => $licensePlate,
            'color'         => $color,
            'brand'         => $brand,
            'confidence'    => $confidence,
            'is_suspicious' => $isSuspicious,
            'source'        => 'manual_upload',
            'image_path'    => $storedPath,
            'scan_time'     => now(),
        ]);

        return $scan->load(['vehicle.user']);
    }
}
