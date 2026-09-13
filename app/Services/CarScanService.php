<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Models\AdminAction;
use App\Models\LicensePlateScan;
use App\Models\SuspiciousVehicle;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CarScanService
{
    private Client $client;
    private string $model;

    public function __construct()
    {
        $guzzle = new GuzzleClient(['verify' => (bool) config('carscan.verify_ssl', true)]);

        $this->client = new Client(
            apiKey: config('carscan.anthropic_api_key', ''),
            requestOptions: RequestOptions::with(transporter: $guzzle),
        );
        $this->model = config('carscan.model', 'claude-opus-4-8');
    }

    /**
     * Send car image to Claude Vision API and extract detection data.
     * Returns: license_plate, province, color, brand, confidence
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

        $provinceList = implode(', ', config('thai_provinces'));

        $prompt = <<<PROMPT
วิเคราะห์รูปรถยนต์นี้แล้วตอบกลับเป็น JSON เท่านั้น ไม่มีข้อความอื่น ไม่มี markdown:

{
  "license_plate": "ป้ายทะเบียนรถ (เฉพาะเลขทะเบียน ไม่รวมจังหวัด) เช่น กข 1234 หรือ 5กก 6285 ถ้าไม่เห็นให้ใส่ค่าว่าง",
  "province": "ชื่อจังหวัดที่พิมพ์อยู่ด้านล่างของป้ายทะเบียน เลือกจากรายการจังหวัดด้านล่างเท่านั้น ถ้าไม่เห็นหรืออ่านไม่ออกให้ใส่ค่าว่าง",
  "color": "เลือกจากรายการด้านล่างเท่านั้น ห้ามตอบนอกรายการ",
  "brand": "ยี่ห้อรถ เช่น Toyota Honda Mazda Isuzu Ford Mitsubishi Nissan Suzuki Hyundai KIA ถ้าไม่แน่ใจให้ใส่ null",
  "confidence": ตัวเลข 0-100 บอกความมั่นใจในการอ่านป้ายทะเบียน
}

รายการจังหวัดที่ใช้ได้ (เลือก 1 จังหวัดเท่านั้น ห้ามตอบนอกรายการ):
{$provinceList}

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
- license_plate: อ่านตัวอักษรและเลขไทย/อังกฤษบนป้ายทะเบียนให้ครบ รูปแบบ "กข 1234" หรือ "5กก 6285" (ไม่รวมชื่อจังหวัด)
- province: อ่านเฉพาะข้อความชื่อจังหวัดที่อยู่ด้านล่างป้ายทะเบียน แยกจาก license_plate
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
     * AI Scan pipeline: เก็บรูป → AI อ่านข้อมูล → จัดผลตามเกณฑ์ Accuracy → ตรวจ Blacklist → บันทึก Scan
     * (ผู้เรียกแจ้งเตือนด้วย alertStaff() หลังรู้ผล Auto Check-in — ลานเต็มใช้ discardForFullLot() แทน)
     *
     * @param int $parkingLotId ลานที่กล้องติดตั้ง (Upload จำลอง Camera Input)
     */
    public function scanAndSave(UploadedFile $file, int $userId, int $parkingLotId): LicensePlateScan
    {
        // 1. Store file
        $storedPath   = $file->store('car-scans', 'public');
        $absolutePath = storage_path('app/public/' . $storedPath);

        // 2. Run AI (Claude Vision)
        $result = $this->detect($absolutePath);

        $licensePlate = trim((string) ($result['license_plate'] ?? '')) ?: null;
        $province     = trim((string) ($result['province'] ?? '')) ?: null;
        $color        = $result['color']       ?? null;
        $brand        = $result['brand']       ?? null;
        $confidence   = isset($result['confidence']) && is_numeric($result['confidence'])
            ? max(0.0, min(100.0, (float) $result['confidence']))
            : null;

        // 3. จัดผลการตรวจ: passed (> เกณฑ์) / low_accuracy / unreadable (อ่านทะเบียนหรือจังหวัดไม่ได้)
        $scanResult = LicensePlateScan::classify($licensePlate, $province, $confidence);

        // 4. Check blacklist (active entries only) — ตรวจด้วย ทะเบียน + จังหวัด
        $isSuspicious = $licensePlate !== null && $province !== null
            && SuspiciousVehicle::active()
                ->where('license_plate', $licensePlate)
                ->where('plate_province', $province)
                ->exists();

        // 5. Persist scan record (AI Scan Log)
        $scan = LicensePlateScan::create([
            'user_id'        => $userId,
            'parking_lot_id' => $parkingLotId,
            'license_plate'  => $licensePlate,
            'plate_province' => $province,
            'color'          => $color,
            'brand'          => $brand,
            'confidence'     => $confidence,
            'result'         => $scanResult,
            'is_suspicious'  => $isSuspicious,
            'source'         => 'manual_upload',
            'image_path'     => $storedPath,
            'scan_time'      => now(),
        ]);

        return $scan;
    }

    /**
     * แจ้ง Owner ของลาน (ถ้ามี) + Admin ทุกคน เมื่อ AI Accuracy ไม่ผ่านเกณฑ์ / อ่านทะเบียนไม่ได้ / พบรถ Blacklist
     * (Blacklist ไม่ Block การเข้าจอด — แจ้งเตือนเพื่อเฝ้าระวังเท่านั้น)
     */
    public function alertStaff(LicensePlateScan $scan): void
    {
        $lot = $scan->parkingLot;
        $lotName = $lot->name;
        $when = $scan->scan_time->format('d/m/Y H:i');

        $carDetail = $this->carDetail($scan);
        $accuracy = $scan->confidence !== null ? number_format($scan->confidence, 1) . '%' : 'ไม่ทราบ';

        $alerts = [];

        if ($scan->result === LicensePlateScan::RESULT_UNREADABLE) {
            $alerts[] = ['AI อ่านทะเบียนไม่ได้', "สแกน #{$scan->id} ที่ลาน {$lotName} เวลา {$when} — AI อ่านทะเบียนไม่ได้ (Accuracy {$accuracy}) กรุณาตรวจสอบรถคันนี้"];
        } elseif ($scan->result === LicensePlateScan::RESULT_LOW_ACCURACY) {
            $alerts[] = ['AI Accuracy ไม่ผ่านเกณฑ์', sprintf(
                'สแกน #%d ที่ลาน %s เวลา %s — %s · Accuracy %s ไม่เกินเกณฑ์ %s%% จึงไม่เช็คอินอัตโนมัติจากผลนี้',
                $scan->id, $lotName, $when, $carDetail, $accuracy, rtrim(rtrim(number_format((float) config('carscan.accuracy_threshold', 85), 2), '0'), '.')
            )];
        }

        if ($scan->is_suspicious) {
            $alerts[] = ['⚠ พบรถต้องสงสัย (Blacklist)', "{$carDetail} ตรวจพบที่ลาน {$lotName} เวลา {$when} (สแกน #{$scan->id})"];
        }

        if (!$alerts) {
            return;
        }

        foreach ($lot->staffRecipientIds() as $recipientId) {
            foreach ($alerts as [$title, $message]) {
                notify_user($recipientId, $title, $message);
            }
        }
    }

    /**
     * Walk-in เข้าลานเต็ม: ไม่บันทึกผล Scan (ลบทั้ง record และรูป) ยกเว้นเหตุการณ์ Blacklist
     * ที่ต้องแจ้ง Admin + Owner และบันทึกลง Audit Log (project-plan.md §11.5)
     */
    public function discardForFullLot(LicensePlateScan $scan): void
    {
        $lot = $scan->parkingLot;

        if ($scan->is_suspicious) {
            $blacklist = SuspiciousVehicle::active()
                ->where('license_plate', $scan->license_plate)
                ->where('plate_province', $scan->plate_province)
                ->first();

            $lot->notifyStaff('⚠ พบรถต้องสงสัย (Blacklist)', sprintf(
                '%s ตรวจพบที่ลาน %s เวลา %s — ลานเต็ม รถไม่ได้เข้าจอด',
                $this->carDetail($scan), $lot->name, $scan->scan_time->format('d/m/Y H:i')
            ));

            AdminAction::create([
                'actor_id'     => null,
                'actor_role'   => 'system',
                'action'       => 'blacklist.detected',
                'subject_type' => 'SuspiciousVehicle',
                'subject_id'   => $blacklist?->id,
                'meta'         => [
                    'license_plate'  => $scan->license_plate,
                    'plate_province' => $scan->plate_province,
                    'brand'          => $scan->brand,
                    'color'          => $scan->color,
                    'confidence'     => $scan->confidence,
                    'parking_lot_id' => $lot->id,
                    'lot_full'       => true,
                ],
            ]);
        }

        if ($scan->image_path) {
            Storage::disk('public')->delete($scan->image_path);
        }

        $scan->delete();
    }

    private function carDetail(LicensePlateScan $scan): string
    {
        $car = trim(($scan->license_plate ?? '') . ' ' . ($scan->plate_province ?? ''));

        return ($car !== '' ? "ทะเบียน {$car}" : 'ทะเบียนอ่านไม่ได้')
            . ($scan->brand ? " ยี่ห้อ {$scan->brand}" : '')
            . ($scan->color ? " สี {$scan->color}" : '');
    }
}
