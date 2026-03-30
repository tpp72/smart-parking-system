<?php

namespace App\Services;

use App\Models\LicensePlateScan;
use App\Models\SuspiciousVehicle;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class CarScanService
{
    /**
     * Run the Python detection script on the given image file.
     * Returns raw detection array: license_plate, color, brand, confidence
     */
    public function detect(string $absoluteImagePath): array
    {
        $pythonBin  = config('carscan.python_bin', 'python');
        $scriptPath = base_path('scripts/detect_car.py');

        $process = new Process([$pythonBin, $scriptPath, $absoluteImagePath]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('[CarScan] Python error: ' . $process->getErrorOutput());
            throw new \RuntimeException('AI script failed: ' . $process->getErrorOutput());
        }

        $output = trim($process->getOutput());

        // Extract last JSON object from stdout (EasyOCR may print warnings before it)
        if (preg_match('/(\{.*\})\s*$/s', $output, $matches)) {
            $output = $matches[1];
        }

        $data = json_decode($output, true);

        if (!is_array($data)) {
            throw new \RuntimeException('AI script returned invalid JSON: ' . $output);
        }

        return $data;
    }

    /**
     * Store the uploaded image, call Python, persist scan record.
     * Returns the saved LicensePlateScan model.
     */
    public function scanAndSave(UploadedFile $file, int $userId): LicensePlateScan
    {
        // 1. Store file
        $storedPath = $file->store('car-scans', 'public');   // storage/app/public/car-scans/
        $absolutePath = storage_path('app/public/' . $storedPath);

        // 2. Run AI
        $result = $this->detect($absolutePath);

        $licensePlate = strtoupper(trim($result['license_plate'] ?? ''));
        $color        = $result['color']       ?? null;
        $brand        = $result['brand']       ?? null;
        $confidence   = isset($result['confidence']) ? (float) $result['confidence'] : null;

        // 3. Match vehicle in DB (if plate found)
        $vehicleId = null;
        if ($licensePlate !== '') {
            $vehicle = Vehicle::where('license_plate', $licensePlate)->first();
            $vehicleId = $vehicle?->id;

            // Update vehicle color/brand if we got better data and it has no value yet
            if ($vehicle) {
                $updates = [];
                if ($color && !$vehicle->color) {
                    $updates['color'] = $color;
                }
                if ($brand && !$vehicle->brand) {
                    $updates['brand'] = $brand;
                }
                if ($updates) {
                    $vehicle->update($updates);
                }
            }
        }

        // 4. Check blacklist
        $isSuspicious = false;
        if ($licensePlate !== '') {
            $isSuspicious = SuspiciousVehicle::where('license_plate', $licensePlate)->exists();
        }

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
