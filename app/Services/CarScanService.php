<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Models\LicensePlateScan;
use App\Models\Reservation;
use App\Models\SuspiciousVehicle;
use App\Models\Vehicle;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CarScanService
{
    private Client $client;
    private string $model;

    public function __construct()
    {
        // Windows local dev: disable SSL verify (CA bundle not configured)
        $guzzle = new GuzzleClient(['verify' => false]);

        $this->client = new Client(
            apiKey: config('carscan.anthropic_api_key', ''),
            requestOptions: RequestOptions::with(transporter: $guzzle),
        );
        $this->model = config('carscan.model', 'claude-opus-4-8');
    }

    /**
     * Send car image to Claude Vision API and extract detection data.
     * Returns: license_plate, color, brand, confidence
     */
    public function detect(string $absoluteImagePath): array
    {
        if (empty(config('carscan.anthropic_api_key', ''))) {
            throw new \RuntimeException('ANTHROPIC_API_KEY ยังไม่ได้ตั้งค่าใน .env');
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
  "color": "เลือกจากรายการด้านล่างเท่านั้น ห้ามตอบนอกรายการ",
  "brand": "ยี่ห้อรถ เช่น Toyota Honda Mazda Isuzu Ford Mitsubishi Nissan Suzuki Hyundai KIA ถ้าไม่แน่ใจให้ใส่ null",
  "confidence": ตัวเลข 0-100 บอกความมั่นใจในการอ่านป้ายทะเบียน
}

รายการสีที่ใช้ได้ (เลือก 1 สีเท่านั้น ห้ามตอบนอกรายการ):
- ขาว = ขาวทุกเฉด
- ดำ = ดำทุกเฉด
- เทา = เทาทุกเฉด
- เงิน = เงินทุกเฉด
- ทอง = ทองทุกเฉด รวม แชมเปญ เบจ ครีม บรอนซ์ gold metallic
- น้ำตาล = น้ำตาลทุกเฉด รวม กาแฟ ช็อกโกแลต
- แดง = แดงทุกเฉด รวม เบอร์กันดี ไวน์แดง
- ส้ม = ส้มทุกเฉด
- เหลือง = เหลืองทุกเฉด
- เขียว = เขียวทุกเฉด
- น้ำเงิน = น้ำเงินและฟ้าทุกเฉด รวม กรมท่า ฟ้าอ่อน
- ม่วง = ม่วงทุกเฉด
- ชมพู = ชมพูทุกเฉด

หลักเกณฑ์:
- license_plate: อ่านตัวอักษรและเลขไทย/อังกฤษบนป้ายทะเบียนให้ครบ รูปแบบ "กข 1234" หรือ "5กก 6285"
- color: ดูสีตัวถังรถเท่านั้น ไม่ใช่สีกระจก หลังคา หรือล้อ เลือกจากรายการด้านบนเท่านั้น
- brand: ดูจากโลโก้หน้ารถหรือรูปทรง
- ตอบเป็น JSON เท่านั้น ไม่มี ```json ไม่มีคำอธิบายเพิ่ม
PROMPT;

        $message = $this->client->messages->create(
            model: $this->model,
            maxTokens: 1024,
            messages: [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'image',
                            'source' => [
                                'type'      => 'base64',
                                'mediaType' => $mimeType,
                                'data'      => $imageData,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        );

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text = $block->text;
                break;
            }
        }

        Log::info('[CarScan] Claude response: ' . substr($text, 0, 500));

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

        throw new \RuntimeException('Claude ตอบกลับรูปแบบไม่ถูกต้อง: ' . $text);
    }

    /**
     * Store the uploaded image, call Claude Vision API, persist scan record.
     * Returns the saved LicensePlateScan model.
     */
    public function scanAndSave(UploadedFile $file, int $userId): LicensePlateScan
    {
        // 1. Store file
        $storedPath   = $file->store('car-scans', 'public');
        $absolutePath = storage_path('app/public/' . $storedPath);

        // 2. Run AI (Claude Vision)
        $result = $this->detect($absolutePath);

        $licensePlate = trim($result['license_plate'] ?? '');
        $color        = $result['color']       ?? null;
        $brand        = $result['brand']       ?? null;
        $confidence   = isset($result['confidence']) ? (float) $result['confidence'] : null;

        // 3. Match vehicle in DB
        $vehicleId = null;
        if ($licensePlate !== '') {
            $vehicle   = $this->platePrefixMatch(Vehicle::query(), $licensePlate)->first();
            $vehicleId = $vehicle?->id;

            if ($vehicle) {
                $updates = [];
                if ($color && !$vehicle->color) $updates['color'] = $color;
                if ($brand && !$vehicle->brand) $updates['brand'] = $brand;
                if ($updates) $vehicle->update($updates);
            }
        }

        // 4. Check blacklist (active entries only)
        $isSuspicious = $licensePlate !== ''
            && $this->platePrefixMatch(SuspiciousVehicle::active(), $licensePlate)->exists();

        // 5. Persist scan record
        $scan = LicensePlateScan::create([
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

    /**
     * Find an active reservation (confirmed or checked_in) for a given license plate.
     * Returns the earliest upcoming reservation, or null if none found.
     */
    public function findMatchingReservation(string $licensePlate): ?Reservation
    {
        $plate = trim($licensePlate);
        if ($plate === '') {
            return null;
        }

        // จับคู่ด้วยป้ายทะเบียนที่กรอกตอนจองโดยตรง (flow หลัก) หรือผ่าน vehicle_id (legacy/admin check-in)
        $vehicle = $this->platePrefixMatch(Vehicle::query(), $plate)->first();

        return Reservation::with(['vehicle', 'parkingLot:id,name', 'parkingSlot:id,slot_number', 'user:id,name'])
            ->where(function ($q) use ($plate, $vehicle) {
                $q->where('license_plate', $plate)
                    ->orWhere('license_plate', 'like', $plate . ' %');
                if ($vehicle) {
                    $q->orWhere('vehicle_id', $vehicle->id);
                }
            })
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->orderBy('reserve_start')
            ->first();
    }

    /**
     * เทียบป้ายทะเบียนแบบ "ขึ้นต้นด้วย" แทน exact match — เพราะ Claude Vision อ่านได้แค่ตัวเลขทะเบียน
     * (เช่น "กพ 961") โดยไม่มีจังหวัดต่อท้าย ในขณะที่ข้อมูลที่เก็บจริงในระบบ (Vehicle/Reservation/
     * SuspiciousVehicle) อาจมีจังหวัดต่อท้ายด้วย (เช่น "กพ 961 กาญจนบุรี")
     */
    private function platePrefixMatch($query, string $plate)
    {
        return $query->where(function ($q) use ($plate) {
            $q->where('license_plate', $plate)
                ->orWhere('license_plate', 'like', $plate . ' %');
        });
    }
}
