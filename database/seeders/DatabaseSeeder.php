<?php

namespace Database\Seeders;

use App\Models\AdminAction;
use App\Models\LicensePlateScan;
use App\Models\Notification;
use App\Models\OwnerApplication;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\SuspiciousVehicle;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** ช่องจอดที่ยังไม่ถูกใช้ (available) ต่อ 1 ลาน — ใช้แจกให้ checked_in / confirmed แบบเลือกช่องล่วงหน้า */
    private array $slotPool = [];

    /** ช่องจอดทั้งหมดต่อ 1 ลาน — ใช้อ้างอิงกับรายการที่ "จบไปแล้ว" (ไม่กระทบสถานะช่องปัจจุบัน) */
    private array $allSlots = [];

    /** ทะเบียนรถที่ถูกใช้ไปแล้ว กันชนกัน */
    private array $usedPlates = [];

    private array $brands = ['Toyota', 'Honda', 'Isuzu', 'Ford', 'Mazda', 'Nissan', 'BMW', 'Mercedes-Benz', 'Mitsubishi', 'Suzuki'];

    /** 13 สีตามที่ Claude Vision ใช้ตอบ */
    private array $colors = ['ขาว', 'ดำ', 'เทา', 'เงิน', 'ทอง', 'น้ำตาล', 'แดง', 'ส้ม', 'เหลือง', 'เขียว', 'น้ำเงิน', 'ม่วง', 'ชมพู'];

    private array $provinces = ['กรุงเทพมหานคร', 'เชียงใหม่', 'ชลบุรี', 'ภูเก็ต', 'นนทบุรี', 'ปทุมธานี', 'สมุทรปราการ', 'ขอนแก่น', 'นครราชสีมา', 'สงขลา'];

    public function run(): void
    {
        DB::transaction(function () {
            $admin = $this->seedAdmin();
            $demoUser = $this->seedDemoUser();
            [$owners, $pendingApplicant, $rejectedApplicant] = $this->seedOwnersAndApplications($admin);
            $generalUsers = $this->seedGeneralUsers(22);

            $renters = array_merge([$demoUser, $pendingApplicant, $rejectedApplicant], $generalUsers);

            $lots = $this->seedParkingLots($owners);
            $this->seedParkingSlots($lots);

            $vehiclesByUser = $this->seedVehicles($renters);
            $allVehicles = collect($vehiclesByUser)->flatten(1);

            $this->seedSuspiciousVehicles($admin, $allVehicles);

            [$activeVehicleIds] = $this->seedReservationsAndLogs($renters, $vehiclesByUser, $lots, $admin);
            $this->seedWalkInParkingLogs($lots, $allVehicles, $activeVehicleIds);
            $this->seedLicensePlateScans($renters, $allVehicles, $admin);
            $this->seedNotifications($renters, $owners, $admin);
            $this->seedAdditionalAdminActions($admin, $owners);
            $this->seedAdminActionsForUnownedLots($admin);
        });
    }

    // ================= Users =================

    private function seedAdmin(): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin@demo.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'owner_status' => null,
            'email_verified_at' => now(),
        ]);
    }

    private function seedDemoUser(): User
    {
        return User::create([
            'name' => 'Normal User',
            'email' => 'user@demo.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'owner_status' => null,
            'email_verified_at' => now(),
        ]);
    }

    /** @return array{0: array<int,User>, 1: User, 2: User} */
    private function seedOwnersAndApplications(User $admin): array
    {
        // 3 owner ที่อนุมัติแล้ว (มีลานจอด) — ชื่อ/ธุรกิจเป็นข้อมูลสมมติทั้งหมด ไม่อ้างอิงบุคคลหรือบริษัทจริง (PDPA)
        $ownerSeeds = [
            ['name' => 'Owner User', 'email' => 'owner@demo.com', 'business' => 'ทดสอบ พาร์คกิ้ง จำกัด'],
            ['name' => 'เจ้าของลานทดสอบ 2', 'email' => 'owner2@demo.com', 'business' => 'ตัวอย่าง ปาร์คกิ้ง เซอร์วิส'],
            ['name' => 'เจ้าของลานทดสอบ 3', 'email' => 'owner3@demo.com', 'business' => 'สาธิต พร็อพเพอร์ตี้'],
        ];

        $owners = [];
        foreach ($ownerSeeds as $i => $seed) {
            $owner = User::create([
                'name' => $seed['name'],
                'email' => $seed['email'],
                'password' => Hash::make('password'),
                'role' => 'owner',
                'owner_status' => 'approved',
                'email_verified_at' => now(),
            ]);
            $owners[] = $owner;

            OwnerApplication::create([
                'user_id' => $owner->id,
                'applicant_type' => 'company',
                'business_name' => $seed['business'],
                'contact_name' => $seed['name'],
                'phone' => '08' . random_int(1, 9) . '-' . random_int(100, 999) . '-' . random_int(1000, 9999),
                'email' => $seed['email'],
                'parking_lot_name' => $seed['business'] . ' (สาขาแรก)',
                'address' => 'อาคารเลขที่ ' . random_int(1, 999),
                'district' => 'บางรัก',
                'province' => 'กรุงเทพมหานคร',
                'description' => 'สมัครเป็นเจ้าของลานจอดรถเพื่อให้บริการลูกค้าในย่านธุรกิจ',
                'estimated_slots' => random_int(20, 50),
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(random_int(10, 60)),
                'created_at' => now()->subDays(random_int(61, 90)),
            ]);
        }

        // ผู้สมัครที่ยังรอพิจารณา (pending) — ชื่อสมมติ ไม่ใช่บุคคลจริง (PDPA)
        $pendingApplicant = User::create([
            'name' => 'ผู้สมัครทดสอบ (รออนุมัติ)',
            'email' => 'pending.owner@demo.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'owner_status' => 'pending',
            'email_verified_at' => now(),
        ]);

        OwnerApplication::create([
            'user_id' => $pendingApplicant->id,
            'applicant_type' => 'individual',
            'business_name' => null,
            'contact_name' => 'ผู้สมัครทดสอบ (รออนุมัติ)',
            'phone' => '089-123-4567',
            'email' => 'pending.owner@demo.com',
            'parking_lot_name' => 'ลานจอดรถ ตัวอย่าง (รออนุมัติ)',
            'address' => '55/1 ถ.รามคำแหง',
            'district' => 'บางกะปิ',
            'province' => 'กรุงเทพมหานคร',
            'description' => 'มีที่ดินว่างหน้าบ้าน ต้องการเปิดเป็นลานจอดรถให้เช่ารายชั่วโมง',
            'estimated_slots' => 12,
            'status' => 'pending',
            'created_at' => now()->subDays(random_int(1, 5)),
        ]);

        // ผู้สมัครที่ถูกปฏิเสธ (rejected — ยัง role=user) — ชื่อสมมติ ไม่ใช่บุคคลจริง (PDPA)
        $rejectedApplicant = User::create([
            'name' => 'ผู้สมัครทดสอบ (ถูกปฏิเสธ)',
            'email' => 'rejected.owner@demo.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'owner_status' => 'rejected',
            'email_verified_at' => now(),
        ]);

        OwnerApplication::create([
            'user_id' => $rejectedApplicant->id,
            'applicant_type' => 'individual',
            'business_name' => null,
            'contact_name' => 'ผู้สมัครทดสอบ (ถูกปฏิเสธ)',
            'phone' => '082-555-1234',
            'email' => 'rejected.owner@demo.com',
            'parking_lot_name' => 'ลานจอดรถ ตัวอย่าง (ถูกปฏิเสธ)',
            'address' => '10 ถ.เพชรบุรีตัดใหม่',
            'district' => 'ห้วยขวาง',
            'province' => 'กรุงเทพมหานคร',
            'description' => 'ที่ดินริมถนนใกล้ทางด่วน',
            'estimated_slots' => 8,
            'status' => 'rejected',
            'rejection_reason' => 'เอกสารสิทธิ์ที่ดินไม่ชัดเจน และพื้นที่ไม่มีทางเข้า-ออกที่ปลอดภัยเพียงพอ กรุณาแนบเอกสารเพิ่มเติมและส่งคำขอใหม่',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now()->subDays(random_int(3, 15)),
            'created_at' => now()->subDays(random_int(16, 25)),
        ]);

        return [$owners, $pendingApplicant, $rejectedApplicant];
    }

    /**
     * ชื่อ/อีเมลเป็นข้อมูลสมมติล้วน ("ผู้ใช้ทดสอบ N") ไม่อ้างอิงชื่อ-นามสกุลบุคคลจริง
     * เพื่อหลีกเลี่ยงประเด็น PDPA ในข้อมูลจำลอง
     *
     * @return array<int,User>
     */
    private function seedGeneralUsers(int $count): array
    {
        $users = [];

        for ($i = 1; $i <= $count; $i++) {
            $users[] = User::create([
                'name' => "ผู้ใช้ทดสอบ {$i}",
                'email' => "testuser{$i}@example.com",
                'password' => Hash::make('password'),
                'role' => 'user',
                'owner_status' => null,
                'email_verified_at' => random_int(1, 10) <= 9 ? now()->subDays(random_int(1, 120)) : null,
                'created_at' => now()->subDays(random_int(1, 150)),
            ]);
        }

        return $users;
    }

    // ================= Parking Lots & Slots =================

    /** @param array<int,User> $owners @return array<int,ParkingLot> */
    private function seedParkingLots(array $owners): array
    {
        $lotSeeds = [
            ['owner' => 0, 'name' => 'ลานจอดรถ เซ็นทรัลพลาซา ลาดพร้าว', 'address' => '1693 ถ.พหลโยธิน', 'district' => 'จตุจักร', 'province' => 'กรุงเทพมหานคร', 'landmark' => 'ตรงข้ามสวนจตุจักร', 'total_slots' => 40, 'hourly_rate' => 30],
            ['owner' => 0, 'name' => 'ลานจอดรถ อาคารสำนักงาน สาทรทาวเวอร์', 'address' => '90 ถ.สาทรเหนือ', 'district' => 'บางรัก', 'province' => 'กรุงเทพมหานคร', 'landmark' => 'ใกล้ BTS ช่องนนทรี', 'total_slots' => 25, 'hourly_rate' => 40],
            ['owner' => 1, 'name' => 'ลานจอดรถ ตลาดนัดจตุจักร โซน D', 'address' => '587 ถ.กำแพงเพชร 2', 'district' => 'จตุจักร', 'province' => 'กรุงเทพมหานคร', 'landmark' => 'ประตูฝั่งเหนือ', 'total_slots' => 60, 'hourly_rate' => 20],
            ['owner' => 1, 'name' => 'ลานจอดรถ คอนโด ริเวอร์ไซด์ นนทบุรี', 'address' => '99 ถ.รัตนาธิเบศร์', 'district' => 'เมืองนนทบุรี', 'province' => 'นนทบุรี', 'landmark' => 'ริมแม่น้ำเจ้าพระยา', 'total_slots' => 20, 'hourly_rate' => 15],
            ['owner' => 2, 'name' => 'ลานจอดรถ โรงพยาบาลรวมแพทย์ เชียงใหม่', 'address' => '8 ถ.ช้างเผือก', 'district' => 'เมืองเชียงใหม่', 'province' => 'เชียงใหม่', 'landmark' => 'ติดโรงพยาบาล', 'total_slots' => 35, 'hourly_rate' => 20],
            ['owner' => 2, 'name' => 'ลานจอดรถ นิคมอุตสาหกรรม แหลมฉบัง', 'address' => '200 หมู่ 4 ถ.สุขุมวิท', 'district' => 'ศรีราชา', 'province' => 'ชลบุรี', 'landmark' => 'ประตู 3', 'total_slots' => 50, 'hourly_rate' => 25],
            // ลานของหน่วยงานรัฐ — ไม่มีเจ้าของเอกชน จึงอยู่ในความดูแลของ Admin โดยตรง (owner_id = null)
            ['owner' => null, 'name' => 'ลานจอดรถ เทศบาลนครระยอง', 'address' => 'ถ.สุขุมวิท', 'district' => 'เมืองระยอง', 'province' => 'ระยอง', 'landmark' => 'หน้าศาลากลางจังหวัด', 'total_slots' => 30, 'hourly_rate' => 15],
            ['owner' => null, 'name' => 'ลานจอดรถ สาธารณะ สนามบินภูเก็ต โซน B', 'address' => '222 ถ.เทพกระษัตรี', 'district' => 'ถลาง', 'province' => 'ภูเก็ต', 'landmark' => 'โซนจอดรถสาธารณะ', 'total_slots' => 45, 'hourly_rate' => 35],
        ];

        $lots = [];
        foreach ($lotSeeds as $seed) {
            $lots[] = ParkingLot::create([
                'owner_id' => $seed['owner'] === null ? null : $owners[$seed['owner']]->id,
                'name' => $seed['name'],
                'location' => $seed['address'] . ', ' . $seed['district'] . ', ' . $seed['province'],
                'address' => $seed['address'],
                'district' => $seed['district'],
                'province' => $seed['province'],
                'landmark' => $seed['landmark'],
                'total_slots' => $seed['total_slots'],
                'hourly_rate' => $seed['hourly_rate'],
                'is_active' => true,
                'reservations_enabled' => true,
                'created_at' => now()->subDays(random_int(90, 200)),
            ]);
        }

        return $lots;
    }

    /** @param array<int,ParkingLot> $lots */
    private function seedParkingSlots(array $lots): void
    {
        foreach ($lots as $i => $lot) {
            $prefix = chr(65 + $i); // A, B, C, ...
            $ids = [];

            for ($n = 1; $n <= $lot->total_slots; $n++) {
                $slot = ParkingSlot::create([
                    'parking_lot_id' => $lot->id,
                    'slot_number' => $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                    'status' => 'available',
                ]);
                $ids[] = $slot->id;
            }

            $this->allSlots[$lot->id] = $ids;
            $pool = $ids;
            shuffle($pool);
            $this->slotPool[$lot->id] = $pool;
        }
    }

    private function pickSlotFromPool(int $lotId): ?int
    {
        if (empty($this->slotPool[$lotId])) {
            return null;
        }
        return array_shift($this->slotPool[$lotId]);
    }

    private function pickAnySlot(int $lotId): ?int
    {
        $all = $this->allSlots[$lotId] ?? [];
        return $all ? $all[array_rand($all)] : null;
    }

    // ================= Vehicles =================

    /** @param array<int,User> $users @return array<int,array<int,Vehicle>> keyed by user_id */
    private function seedVehicles(array $users): array
    {
        $consonantPairs = ['กข', 'ขค', 'คง', 'งจ', 'จฉ', 'ชซ', 'ฎฏ', 'ทธ', 'พร', 'สต', 'อบ', 'ผม', 'ฮย'];
        $byUser = [];

        foreach ($users as $user) {
            $count = random_int(1, 2);
            $vehicles = [];

            for ($i = 0; $i < $count; $i++) {
                $plate = $this->makeUniquePlate($consonantPairs);

                $vehicles[] = Vehicle::create([
                    'user_id' => $user->id,
                    'license_plate' => $plate,
                    'brand' => $this->brands[array_rand($this->brands)],
                    'color' => $this->colors[array_rand($this->colors)],
                    'created_at' => now()->subDays(random_int(1, 140)),
                ]);
            }

            $byUser[$user->id] = $vehicles;
        }

        return $byUser;
    }

    private function makeUniquePlate(array $consonantPairs): string
    {
        do {
            $plate = $consonantPairs[array_rand($consonantPairs)] . ' '
                . random_int(1000, 9999) . ' '
                . $this->provinces[array_rand($this->provinces)];
        } while (isset($this->usedPlates[$plate]));

        $this->usedPlates[$plate] = true;
        return $plate;
    }

    // ================= Suspicious Vehicles =================

    private function seedSuspiciousVehicles(User $admin, $allVehicles): void
    {
        $reasons = [
            'แจ้งความรถหาย',
            'ค้างชำระค่าจอดหลายครั้ง',
            'พบพฤติกรรมต้องสงสัยจากกล้องวงจรปิด',
            'ตำรวจแจ้งเตือนให้เฝ้าระวัง',
            'ป้ายทะเบียนปลอม / ไม่ตรงกับฐานข้อมูลกรมขนส่ง',
            'มีประวัติทำลายทรัพย์สินในลานจอด',
        ];

        // 2 รายการ อ้างอิงจากรถที่มีอยู่จริงในระบบ (จำลองเคสที่ตรวจพบ)
        $sample = $allVehicles->random(min(2, $allVehicles->count()));
        foreach ($sample as $vehicle) {
            SuspiciousVehicle::create([
                'license_plate' => $vehicle->license_plate,
                'reason' => $reasons[array_rand($reasons)],
                'level' => 'high',
                'is_active' => true,
                'added_by' => $admin->id,
                'created_at' => now()->subDays(random_int(1, 30)),
            ]);
        }

        // อีก 4 รายการ เป็นทะเบียนต้องสงสัยทั่วไป (ไม่ผูกกับรถในระบบ)
        $consonantPairs = ['ฆฒ', 'ฌญ', 'ฐฑ', 'ณด'];
        for ($i = 0; $i < 4; $i++) {
            SuspiciousVehicle::create([
                'license_plate' => $consonantPairs[$i] . ' ' . random_int(1000, 9999),
                'reason' => $reasons[array_rand($reasons)],
                'level' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                'is_active' => random_int(1, 10) <= 8,
                'added_by' => $admin->id,
                'created_at' => now()->subDays(random_int(1, 60)),
            ]);
        }
    }

    // ================= Reservations, Logs, Parking Logs, Payments =================

    /**
     * @param array<int,User> $renters
     * @param array<int,array<int,Vehicle>> $vehiclesByUser
     * @param array<int,ParkingLot> $lots
     * @return array{0: array<int,int>} [activeVehicleIds]
     */
    private function seedReservationsAndLogs(array $renters, array $vehiclesByUser, array $lots, User $admin): array
    {
        $activeVehicleIds = [];

        $specs = [
            'completed' => 28,
            'checked_in' => 9,
            'confirmed' => 12,
            'pending' => 12,
            'cancelled' => 6,
            'expired' => 7,
        ];

        foreach ($specs as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $user = $renters[array_rand($renters)];
                $userVehicles = $vehiclesByUser[$user->id];
                $vehicle = $userVehicles[array_rand($userVehicles)];
                $lot = $lots[array_rand($lots)];

                $this->createReservationCase($status, $user, $vehicle, $lot, $admin, $activeVehicleIds);
            }
        }

        return [$activeVehicleIds];
    }

    private function createReservationCase(string $status, User $user, Vehicle $vehicle, ParkingLot $lot, User $admin, array &$activeVehicleIds): void
    {
        $hourlyRate = (float) $lot->hourly_rate;

        switch ($status) {
            case 'completed':
                $checkedInAt = now()->subDays(random_int(1, 25))->subHours(random_int(0, 10))->setMinute(random_int(0, 59))->setSecond(0);
                $reserveStart = $checkedInAt->copy()->subMinutes(random_int(5, 20));
                $durationMinutes = random_int(35, 360);
                $completedAt = $checkedInAt->copy()->addMinutes($durationMinutes);
                $bookedAt = $reserveStart->copy()->subHours(random_int(1, 48));

                $reservation = Reservation::create([
                    'user_id' => $user->id,
                    'license_plate' => $vehicle->license_plate,
                    'vehicle_id' => $vehicle->id,
                    'parking_lot_id' => $lot->id,
                    'parking_slot_id' => null,
                    'reserve_start' => $reserveStart,
                    'checked_in_at' => $checkedInAt,
                    'completed_at' => $completedAt,
                    'reservation_fee' => $hourlyRate,
                    'status' => 'completed',
                    'created_at' => $bookedAt,
                    'updated_at' => $completedAt,
                ]);

                $this->logTransition($reservation, null, 'confirmed', $admin->id, 'Admin ยืนยันการจอง', $bookedAt->copy()->addMinutes(random_int(5, 60)));
                $this->logTransition($reservation, 'confirmed', 'checked_in', null, "Auto check-in: รถเข้าจอด", $checkedInAt);
                $this->logTransition($reservation, 'checked_in', 'completed', null, 'Auto completed: รถออกจากลานแล้ว', $completedAt);

                $slotId = $this->pickAnySlot($lot->id);
                $log = ParkingLog::create([
                    'vehicle_id' => $vehicle->id,
                    'parking_lot_id' => $lot->id,
                    'parking_slot_id' => $slotId,
                    'reservation_id' => $reservation->id,
                    'check_in_time' => $checkedInAt,
                    'check_out_time' => $completedAt,
                    'created_at' => $checkedInAt,
                    'updated_at' => $completedAt,
                ]);

                $this->createPayment($log, $checkedInAt, $completedAt, $hourlyRate, $reservation->reservation_fee);
                break;

            case 'checked_in':
                $checkedInAt = now()->subMinutes(random_int(10, 180));
                $reserveStart = $checkedInAt->copy()->subMinutes(random_int(0, 15));
                $bookedAt = $reserveStart->copy()->subHours(random_int(1, 24));

                $reservation = Reservation::create([
                    'user_id' => $user->id,
                    'license_plate' => $vehicle->license_plate,
                    'vehicle_id' => $vehicle->id,
                    'parking_lot_id' => $lot->id,
                    'parking_slot_id' => null,
                    'reserve_start' => $reserveStart,
                    'checked_in_at' => $checkedInAt,
                    'completed_at' => null,
                    'reservation_fee' => $hourlyRate,
                    'status' => 'checked_in',
                    'created_at' => $bookedAt,
                    'updated_at' => $checkedInAt,
                ]);

                $this->logTransition($reservation, null, 'confirmed', $admin->id, 'Admin ยืนยันการจอง', $bookedAt->copy()->addMinutes(random_int(5, 60)));
                $this->logTransition($reservation, 'confirmed', 'checked_in', null, 'Auto check-in: รถเข้าจอด', $checkedInAt);

                $slotId = $this->pickSlotFromPool($lot->id);
                if ($slotId) {
                    ParkingSlot::whereKey($slotId)->update(['status' => 'occupied']);
                }

                ParkingLog::create([
                    'vehicle_id' => $vehicle->id,
                    'parking_lot_id' => $lot->id,
                    'parking_slot_id' => $slotId,
                    'reservation_id' => $reservation->id,
                    'check_in_time' => $checkedInAt,
                    'check_out_time' => null,
                    'created_at' => $checkedInAt,
                    'updated_at' => $checkedInAt,
                ]);

                $activeVehicleIds[] = $vehicle->id;
                break;

            case 'confirmed':
                $reserveStart = now()->addHours(random_int(0, 20))->addMinutes(random_int(0, 59));
                $bookedAt = now()->subHours(random_int(1, 30));

                $preselect = random_int(1, 10) <= 5;
                $slotId = $preselect ? $this->pickSlotFromPool($lot->id) : null;
                if ($slotId) {
                    ParkingSlot::whereKey($slotId)->update(['status' => 'reserved']);
                }

                $reservation = Reservation::create([
                    'user_id' => $user->id,
                    'license_plate' => $vehicle->license_plate,
                    'vehicle_id' => $vehicle->id,
                    'parking_lot_id' => $lot->id,
                    'parking_slot_id' => $slotId,
                    'reserve_start' => $reserveStart,
                    'reservation_fee' => $hourlyRate,
                    'status' => 'confirmed',
                    'created_at' => $bookedAt,
                    'updated_at' => $bookedAt->copy()->addMinutes(random_int(5, 60)),
                ]);

                $this->logTransition($reservation, null, 'confirmed', $admin->id, 'Admin ยืนยันการจอง', $reservation->updated_at);
                break;

            case 'pending':
                $reserveStart = now()->addHours(random_int(1, 23))->addMinutes(random_int(0, 59));
                $bookedAt = now()->subHours(random_int(0, 6));

                Reservation::create([
                    'user_id' => $user->id,
                    'license_plate' => $vehicle->license_plate,
                    'vehicle_id' => $vehicle->id,
                    'parking_lot_id' => $lot->id,
                    'parking_slot_id' => null,
                    'reserve_start' => $reserveStart,
                    'reservation_fee' => $hourlyRate,
                    'status' => 'pending',
                    'created_at' => $bookedAt,
                    'updated_at' => $bookedAt,
                ]);
                break;

            case 'cancelled':
                $reserveStart = now()->addDays(random_int(-3, 3))->addHours(random_int(0, 12));
                $bookedAt = $reserveStart->copy()->subHours(random_int(2, 48));
                $cancelledAt = $bookedAt->copy()->addHours(random_int(1, 20));

                $reservation = Reservation::create([
                    'user_id' => $user->id,
                    'license_plate' => $vehicle->license_plate,
                    'vehicle_id' => $vehicle->id,
                    'parking_lot_id' => $lot->id,
                    'parking_slot_id' => null,
                    'reserve_start' => $reserveStart,
                    'reservation_fee' => $hourlyRate,
                    'status' => 'cancelled',
                    'created_at' => $bookedAt,
                    'updated_at' => $cancelledAt,
                ]);

                $byUser = random_int(1, 10) <= 7;
                $this->logTransition(
                    $reservation,
                    'pending',
                    'cancelled',
                    $byUser ? null : $admin->id,
                    $byUser ? 'ผู้ใช้ยกเลิกการจองด้วยตนเอง' : 'ยกเลิกโดยผู้ดูแลระบบ',
                    $cancelledAt
                );
                break;

            case 'expired':
                $reserveStart = now()->subHours(random_int(2, 72));
                $bookedAt = $reserveStart->copy()->subHours(random_int(1, 24));
                $expiredAt = $reserveStart->copy()->addMinutes(random_int(31, 90));
                $wasConfirmed = random_int(1, 10) <= 5;

                $reservation = Reservation::create([
                    'user_id' => $user->id,
                    'license_plate' => $vehicle->license_plate,
                    'vehicle_id' => $vehicle->id,
                    'parking_lot_id' => $lot->id,
                    'parking_slot_id' => null,
                    'reserve_start' => $reserveStart,
                    'reservation_fee' => $hourlyRate,
                    'status' => 'expired',
                    'created_at' => $bookedAt,
                    'updated_at' => $expiredAt,
                ]);

                if ($wasConfirmed) {
                    $this->logTransition($reservation, 'pending', 'confirmed', $admin->id, 'Admin ยืนยันการจอง', $bookedAt->copy()->addHours(1));
                    $this->logTransition($reservation, 'confirmed', 'expired', null, 'หมดเวลาจอง (เลย grace period โดยไม่ check-in)', $expiredAt);
                } else {
                    $this->logTransition($reservation, 'pending', 'expired', null, 'หมดเวลาจอง (เลย grace period โดยไม่ check-in)', $expiredAt);
                }
                break;
        }
    }

    private function logTransition(Reservation $reservation, ?string $old, string $new, ?int $changedBy, string $note, Carbon $at): void
    {
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'old_status' => $old,
            'new_status' => $new,
            'changed_by' => $changedBy,
            'note' => $note,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function createPayment(ParkingLog $log, Carbon $checkIn, Carbon $checkOut, float $hourlyRate, float $reservationFee = 0): Payment
    {
        $diffMinutes = $checkIn->diffInMinutes($checkOut);
        $totalHours = max(1, (int) ceil($diffMinutes / 60));
        $parkingFee = round($totalHours * $hourlyRate, 2);
        $deposit = min($reservationFee, $parkingFee);
        $totalAmount = round($parkingFee - $deposit, 2);

        // ส่วนใหญ่ชำระแล้ว บาง record ยังค้างจ่ายเพื่อความสมจริง
        $paid = $totalAmount <= 0 || random_int(1, 10) <= 8;

        return Payment::create([
            'parking_log_id' => $log->id,
            'reservation_id' => $log->reservation_id,
            'total_hours' => $totalHours,
            'hourly_rate' => $hourlyRate,
            'parking_fee' => $parkingFee,
            'reservation_discount' => $deposit,
            'total_amount' => $totalAmount,
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'created_at' => $checkOut,
            'updated_at' => $checkOut,
        ]);
    }

    // ================= Walk-in Parking Logs (ไม่มีการจองล่วงหน้า) =================

    /** @param array<int,ParkingLot> $lots */
    private function seedWalkInParkingLogs(array $lots, $allVehicles, array $activeVehicleIds): void
    {
        // ประวัติ walk-in ที่จบไปแล้ว
        for ($i = 0; $i < 15; $i++) {
            $lot = $lots[array_rand($lots)];
            $vehicle = $allVehicles->random();
            $hourlyRate = (float) $lot->hourly_rate;

            $checkIn = now()->subDays(random_int(1, 30))->subHours(random_int(0, 12));
            $checkOut = $checkIn->copy()->addMinutes(random_int(30, 300));
            $slotId = $this->pickAnySlot($lot->id);

            $log = ParkingLog::create([
                'vehicle_id' => $vehicle->id,
                'parking_lot_id' => $lot->id,
                'parking_slot_id' => $slotId,
                'reservation_id' => null,
                'check_in_time' => $checkIn,
                'check_out_time' => $checkOut,
                'created_at' => $checkIn,
                'updated_at' => $checkOut,
            ]);

            $this->createPayment($log, $checkIn, $checkOut, $hourlyRate, 0);
        }

        // รถที่ walk-in เข้ามาจอดอยู่ตอนนี้ (ไม่ผ่านการจอง)
        $eligibleVehicles = $allVehicles->reject(fn ($v) => in_array($v->id, $activeVehicleIds, true));

        for ($i = 0; $i < 3 && $eligibleVehicles->count() > 0; $i++) {
            $vehicle = $eligibleVehicles->random();
            $eligibleVehicles = $eligibleVehicles->reject(fn ($v) => $v->id === $vehicle->id);

            $lot = $lots[array_rand($lots)];
            $slotId = $this->pickSlotFromPool($lot->id);
            if (!$slotId) {
                continue;
            }
            ParkingSlot::whereKey($slotId)->update(['status' => 'occupied']);

            $checkIn = now()->subMinutes(random_int(5, 120));

            ParkingLog::create([
                'vehicle_id' => $vehicle->id,
                'parking_lot_id' => $lot->id,
                'parking_slot_id' => $slotId,
                'reservation_id' => null,
                'check_in_time' => $checkIn,
                'check_out_time' => null,
                'created_at' => $checkIn,
                'updated_at' => $checkIn,
            ]);
        }
    }

    // ================= License Plate Scans (AI Car Scan history) =================

    private function seedLicensePlateScans(array $renters, $allVehicles, User $admin): void
    {
        $suspiciousPlates = SuspiciousVehicle::where('is_active', true)->pluck('license_plate')->all();

        for ($i = 0; $i < 20; $i++) {
            $matched = random_int(1, 10) <= 7;
            $vehicle = $matched ? $allVehicles->random() : null;
            $user = $vehicle ? User::find($vehicle->user_id) : $renters[array_rand($renters)];

            $isSuspicious = !empty($suspiciousPlates) && random_int(1, 10) <= 2;
            $plate = $isSuspicious
                ? $suspiciousPlates[array_rand($suspiciousPlates)]
                : ($vehicle->license_plate ?? ('กก ' . random_int(1000, 9999) . ' ' . $this->provinces[array_rand($this->provinces)]));

            $scanTime = now()->subDays(random_int(0, 45))->subHours(random_int(0, 20));

            LicensePlateScan::create([
                'user_id' => $user?->id,
                'vehicle_id' => $vehicle?->id,
                'license_plate' => $plate,
                'color' => $this->colors[array_rand($this->colors)],
                'brand' => $this->brands[array_rand($this->brands)],
                'confidence' => round(random_int(750, 990) / 1000, 2),
                'is_suspicious' => $isSuspicious,
                'source' => random_int(1, 10) <= 6 ? 'manual_upload' : 'auto_checkin',
                'image_path' => 'car-scans/' . $scanTime->format('Ymd_His') . '_' . random_int(1000, 9999) . '.jpg',
                'scan_time' => $scanTime,
                'created_at' => $scanTime,
                'updated_at' => $scanTime,
            ]);
        }
    }

    // ================= Notifications =================

    private function seedNotifications(array $renters, array $owners, User $admin): void
    {
        $templates = [
            ['title' => 'การจองได้รับการยืนยัน', 'body' => 'การจองของคุณได้รับการยืนยันแล้ว กรุณาเช็คอินภายในเวลาที่กำหนด'],
            ['title' => 'เช็คอินสำเร็จ', 'body' => 'รถของคุณเข้าจอดในระบบเรียบร้อยแล้ว'],
            ['title' => 'เช็คเอาท์เรียบร้อย', 'body' => 'รถของคุณออกจากลานแล้ว ขอบคุณที่ใช้บริการ'],
            ['title' => 'การจองถูกยกเลิก', 'body' => 'การจองของคุณถูกยกเลิกโดยผู้ดูแลระบบ'],
            ['title' => 'การจองหมดอายุ', 'body' => 'การจองของคุณหมดอายุเนื่องจากไม่ได้เช็คอินภายในเวลาที่กำหนด'],
        ];

        foreach ($renters as $user) {
            $n = random_int(1, 4);
            for ($i = 0; $i < $n; $i++) {
                $tpl = $templates[array_rand($templates)];
                $createdAt = now()->subDays(random_int(0, 45))->subHours(random_int(0, 20));

                Notification::create([
                    'user_id' => $user->id,
                    'title' => $tpl['title'],
                    'message' => $tpl['body'],
                    'is_read' => random_int(1, 10) <= 6,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }

        // แจ้งเตือนเจ้าของลานจอด
        foreach ($owners as $owner) {
            $createdAt = now()->subDays(random_int(30, 80));
            Notification::create([
                'user_id' => $owner->id,
                'title' => 'คำขอเจ้าของลานจอดได้รับการอนุมัติ! 🎉',
                'message' => 'ยินดีด้วย! คำขอของคุณได้รับการอนุมัติแล้ว คุณสามารถเริ่มเพิ่มลานจอดได้ทันที',
                'is_read' => true,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // แจ้งผลผู้สมัครที่ pending/rejected
        $rejected = User::where('email', 'rejected.owner@demo.com')->first();
        if ($rejected) {
            $createdAt = now()->subDays(random_int(3, 15));
            Notification::create([
                'user_id' => $rejected->id,
                'title' => 'คำขอเจ้าของลานจอดไม่ได้รับการอนุมัติ',
                'message' => 'คำขอของคุณไม่ได้รับการอนุมัติ เหตุผล: เอกสารสิทธิ์ที่ดินไม่ชัดเจน คุณสามารถแก้ไขและส่งคำขอใหม่ได้',
                'is_read' => false,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    // ================= Admin Actions (extra, ไม่ซ้ำกับที่สร้างระหว่าง flow อื่น) =================

    private function seedAdditionalAdminActions(User $admin, array $owners): void
    {
        // force password reset ให้ user สุ่มบางคน
        $targets = User::where('role', 'user')->inRandomOrder()->limit(3)->get();
        foreach ($targets as $target) {
            $target->update(['force_password_reset' => true]);

            AdminAction::create([
                'admin_id' => $admin->id,
                'action' => 'user.force_reset',
                'subject_type' => 'User',
                'subject_id' => $target->id,
                'meta' => ['reason' => 'นโยบายความปลอดภัยประจำไตรมาส'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Seeder)',
                'created_at' => now()->subDays(random_int(1, 20)),
            ]);
        }

        // owner_application.approve / reject ของ 3 owner ที่อนุมัติแล้ว
        foreach (OwnerApplication::where('status', 'approved')->get() as $app) {
            AdminAction::create([
                'admin_id' => $admin->id,
                'action' => 'owner_application.approve',
                'subject_type' => 'OwnerApplication',
                'subject_id' => $app->id,
                'meta' => [],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Seeder)',
                'created_at' => $app->reviewed_at,
            ]);
        }

        foreach (OwnerApplication::where('status', 'rejected')->get() as $app) {
            AdminAction::create([
                'admin_id' => $admin->id,
                'action' => 'owner_application.reject',
                'subject_type' => 'OwnerApplication',
                'subject_id' => $app->id,
                'meta' => ['reason' => $app->rejection_reason],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Seeder)',
                'created_at' => $app->reviewed_at,
            ]);
        }

        // suspicious_vehicle.create
        foreach (SuspiciousVehicle::all() as $sv) {
            AdminAction::create([
                'admin_id' => $admin->id,
                'action' => 'suspicious_vehicle.create',
                'subject_type' => 'SuspiciousVehicle',
                'subject_id' => $sv->id,
                'meta' => ['license_plate' => $sv->license_plate, 'level' => $sv->level],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Seeder)',
                'created_at' => $sv->created_at,
            ]);
        }
    }

    /**
     * Audit log สำหรับกิจกรรมที่ Admin ทำเองบนลานที่ไม่มีเจ้าของ (รัฐ/สาธารณะ)
     * — ให้หน้า Admin Actions Log มีตัวอย่างครบทุก action type ที่เพิ่มเข้ามาใหม่
     * (reservation.confirm/update, parking_log.check_in/check_out, payment.mark_paid, user.create/delete)
     */
    private function seedAdminActionsForUnownedLots(User $admin): void
    {
        $unownedLotIds = ParkingLot::unowned()->pluck('id');
        if ($unownedLotIds->isEmpty()) {
            return;
        }

        // reservation.confirm — จาก ReservationLog จริงที่ Admin เป็นคนยืนยัน บนลานที่ไม่มีเจ้าของ
        $confirmLogs = ReservationLog::where('new_status', 'confirmed')
            ->where('changed_by', $admin->id)
            ->whereHas('reservation', fn($q) => $q->whereIn('parking_lot_id', $unownedLotIds))
            ->get(['id', 'reservation_id', 'created_at']);

        foreach ($confirmLogs as $log) {
            AdminAction::create([
                'admin_id' => $admin->id,
                'action' => 'reservation.confirm',
                'subject_type' => 'Reservation',
                'subject_id' => $log->reservation_id,
                'meta' => ['status' => 'confirmed'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Seeder)',
                'created_at' => $log->created_at,
            ]);
        }

        // reservation.update — จาก ReservationLog จริงที่ Admin เป็นคนยกเลิก บนลานที่ไม่มีเจ้าของ
        $cancelLogs = ReservationLog::where('new_status', 'cancelled')
            ->where('changed_by', $admin->id)
            ->whereHas('reservation', fn($q) => $q->whereIn('parking_lot_id', $unownedLotIds))
            ->get(['id', 'reservation_id', 'created_at']);

        // การันตีให้มีอย่างน้อย 1 รายการ (ไม่พึ่งดวงสุ่มล้วนๆ) — หยิบ cancelled log ใดก็ได้บนลานที่ไม่มีเจ้าของ มาระบุว่า Admin เป็นคนยกเลิก
        if ($cancelLogs->isEmpty()) {
            $fallbackLog = ReservationLog::where('new_status', 'cancelled')
                ->whereHas('reservation', fn($q) => $q->whereIn('parking_lot_id', $unownedLotIds))
                ->first();

            if ($fallbackLog) {
                $fallbackLog->update(['changed_by' => $admin->id, 'note' => 'ยกเลิกโดยผู้ดูแลระบบ']);
                $cancelLogs = collect([$fallbackLog]);
            }
        }

        foreach ($cancelLogs as $log) {
            AdminAction::create([
                'admin_id' => $admin->id,
                'action' => 'reservation.update',
                'subject_type' => 'Reservation',
                'subject_id' => $log->reservation_id,
                'meta' => ['changed' => ['status']],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Seeder)',
                'created_at' => $log->created_at,
            ]);
        }

        // parking_log.check_in / parking_log.check_out — จาก ParkingLog จริงบนลานที่ไม่มีเจ้าของ
        $logs = ParkingLog::whereIn('parking_lot_id', $unownedLotIds)
            ->get(['id', 'parking_lot_id', 'parking_slot_id', 'reservation_id', 'check_in_time', 'check_out_time']);

        foreach ($logs as $log) {
            AdminAction::create([
                'admin_id' => $admin->id,
                'action' => 'parking_log.check_in',
                'subject_type' => 'ParkingLog',
                'subject_id' => $log->id,
                'meta' => [
                    'parking_lot_id' => $log->parking_lot_id,
                    'parking_slot_id' => $log->parking_slot_id,
                    'reservation_id' => $log->reservation_id,
                ],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Seeder)',
                'created_at' => $log->check_in_time,
            ]);

            if ($log->check_out_time) {
                AdminAction::create([
                    'admin_id' => $admin->id,
                    'action' => 'parking_log.check_out',
                    'subject_type' => 'ParkingLog',
                    'subject_id' => $log->id,
                    'meta' => [],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Seeder)',
                    'created_at' => $log->check_out_time,
                ]);
            }
        }

        // payment.mark_paid — จาก Payment จริงที่ชำระแล้วและมียอด > 0 (ต้องมีคนกดยืนยันรับเงิน)
        $paidPayments = Payment::whereHas('parkingLog', fn($q) => $q->whereIn('parking_lot_id', $unownedLotIds))
            ->where('payment_status', 'paid')
            ->where('total_amount', '>', 0)
            ->get(['id', 'total_amount', 'updated_at']);

        foreach ($paidPayments as $p) {
            AdminAction::create([
                'admin_id' => $admin->id,
                'action' => 'payment.mark_paid',
                'subject_type' => 'ParkingLog',
                'subject_id' => $p->id,
                'meta' => ['payment_id' => $p->id, 'total_amount' => $p->total_amount],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Seeder)',
                'created_at' => $p->updated_at,
            ]);
        }

        // user.create / user.delete — ตัวอย่างประวัติการเพิ่ม/ลบผู้ใช้ (เป็น audit trail ของบัญชีที่ไม่มีอยู่แล้ว
        // เหมือนสถานการณ์จริงหลังลบ user — admin_actions ไม่มี FK ผูกกับ users จึงเก็บ record ไว้ได้)
        AdminAction::create([
            'admin_id' => $admin->id,
            'action' => 'user.create',
            'subject_type' => 'User',
            'subject_id' => 9990,
            'meta' => ['role' => 'user'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Seeder)',
            'created_at' => now()->subDays(40),
        ]);

        AdminAction::create([
            'admin_id' => $admin->id,
            'action' => 'user.delete',
            'subject_type' => 'User',
            'subject_id' => 9991,
            'meta' => ['role' => 'owner', 'lots_deleted' => 1, 'reservations_cancelled' => 2],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Seeder)',
            'created_at' => now()->subDays(12),
        ]);
    }
}
