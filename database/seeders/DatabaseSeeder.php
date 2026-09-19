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
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * ข้อมูลตัวอย่างตาม Data Model ใหม่ (Plate-based):
 * - Reservation เก็บ ทะเบียน + จังหวัด + ยี่ห้อ + สี (ไม่มี Vehicle)
 * - Deposit = hourly_rate × 1 เป็น Payment จริง (type = deposit) · reservation_fee = ส่วนลด = hourly_rate
 * - Slot ถูก Lock (reserved) เมื่อยืนยันรับเงิน Deposit แล้วเท่านั้น
 * - Walk-in = Reservation ของ "Walkin User" · Deposit 0 · ไม่มีส่วนลด
 * - จองล่วงหน้าไม่เกิน 1 วัน · Expire หลัง reserve_start 1 ชั่วโมง
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** ช่องจอดที่ยังว่าง (available) ต่อ 1 ลาน — แจกให้รายการที่ถือครอง Slot อยู่ตอนนี้ */
    private array $slotPool = [];

    /** ช่องจอดทั้งหมดต่อ 1 ลาน — ใช้กับรายการที่จบไปแล้ว (ไม่กระทบสถานะช่องปัจจุบัน) */
    private array $allSlots = [];

    /** รถที่ใช้ไปแล้ว (ทะเบียน|จังหวัด) กันชนกัน */
    private array $usedCars = [];

    private array $brands = ['Toyota', 'Honda', 'Isuzu', 'Ford', 'Mazda', 'Nissan', 'BMW', 'Mercedes-Benz', 'Mitsubishi', 'Suzuki'];

    private array $provinces = ['กรุงเทพมหานคร', 'เชียงใหม่', 'ชลบุรี', 'ภูเก็ต', 'นนทบุรี', 'ปทุมธานี', 'สมุทรปราการ', 'ขอนแก่น', 'นครราชสีมา', 'สงขลา'];

    private array $colors = [];

    public function run(): void
    {
        $this->colors = config('car_colors');

        DB::transaction(function () {
            $admin = $this->seedAdmin();
            $demoUser = $this->seedDemoUser();
            [$owners, $pendingApplicant, $rejectedApplicant] = $this->seedOwnersAndApplications($admin);
            $generalUsers = $this->seedGeneralUsers(22);

            $renters = array_merge([$demoUser, $pendingApplicant, $rejectedApplicant], $generalUsers);

            $lots = $this->seedParkingLots($owners);
            $this->seedParkingSlots($lots);

            $this->seedReservations($renters, $lots, $admin);
            $this->seedWalkInReservations(User::walkin(), $lots, $admin);

            $blacklist = $this->seedSuspiciousVehicles($admin);
            $this->seedLicensePlateScans($renters, $admin, $lots, $blacklist);
            $this->seedNotifications($renters, $owners);
            $this->seedAuditLogs($admin);
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
        foreach ($ownerSeeds as $seed) {
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
            // ลานของ Admin (owner_id = null) — Admin ทุกคนใช้ร่วมกัน
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

    // ================= Cars (Plate-based) =================

    /** @return array{license_plate: string, plate_province: string, brand: string, color: string} */
    private function makeCar(): array
    {
        $consonantPairs = ['กข', 'ขค', 'คง', 'งจ', 'จฉ', 'ชซ', 'ฎฏ', 'ทธ', 'พร', 'สต', 'อบ', 'ผม', 'ฮย'];

        do {
            $plate = $consonantPairs[array_rand($consonantPairs)] . ' ' . random_int(1000, 9999);
            $province = $this->provinces[array_rand($this->provinces)];
        } while (isset($this->usedCars["{$plate}|{$province}"]));

        $this->usedCars["{$plate}|{$province}"] = true;

        return [
            'license_plate' => $plate,
            'plate_province' => $province,
            'brand' => $this->brands[array_rand($this->brands)],
            'color' => $this->colors[array_rand($this->colors)],
        ];
    }

    /** ผู้ยืนยันรับเงินของลาน: Owner ของลาน หรือ Admin ถ้าเป็นลานของ Admin */
    private function cashierId(ParkingLot $lot, User $admin): int
    {
        return $lot->owner_id ?? $admin->id;
    }

    // ================= Reservations =================

    /**
     * @param array<int,User> $renters
     * @param array<int,ParkingLot> $lots
     */
    private function seedReservations(array $renters, array $lots, User $admin): void
    {
        $specs = [
            'completed' => 28,
            'checked_in' => 9,
            'confirmed' => 12,
            'pending' => 12,
            'cancelled' => 8,
            'expired' => 8,
        ];

        foreach ($specs as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $user = $renters[array_rand($renters)];
                $lot = $lots[array_rand($lots)];

                $this->createReservationCase($status, $user, $lot, $admin);
            }
        }
    }

    private function createReservationCase(string $status, User $user, ParkingLot $lot, User $admin): void
    {
        $rate = (float) $lot->hourly_rate;
        $cashierId = $this->cashierId($lot, $admin);

        $base = array_merge($this->makeCar(), [
            'user_id' => $user->id,
            'parking_lot_id' => $lot->id,
            'deposit_amount' => $rate,   // Deposit = hourly_rate × 1
            'reservation_fee' => $rate,  // ส่วนลด = hourly_rate
        ]);

        switch ($status) {
            case 'completed':
                $checkedInAt = now()->subDays(random_int(1, 25))->subHours(random_int(0, 10))->setMinute(random_int(0, 59))->setSecond(0);
                $reserveStart = $checkedInAt->copy()->subMinutes(random_int(5, 20));
                $bookedAt = $reserveStart->copy()->subHours(random_int(1, 20));
                $paidAt = $bookedAt->copy()->addMinutes(random_int(5, 50));
                $completedAt = $checkedInAt->copy()->addMinutes(random_int(35, 360));
                $slotId = $this->pickAnySlot($lot->id);

                $reservation = Reservation::create($base + [
                    'parking_slot_id' => $slotId,
                    'reserve_start' => $reserveStart,
                    'checked_in_at' => $checkedInAt,
                    'completed_at' => $completedAt,
                    'status' => 'completed',
                    'created_at' => $bookedAt,
                    'updated_at' => $completedAt,
                ]);

                $this->logTransition($reservation, null, 'pending', $user->id, 'ผู้ใช้สร้างการจอง', $bookedAt);
                $this->depositPayment($reservation, 'paid', $cashierId, $bookedAt, $paidAt);
                $this->logTransition($reservation, 'pending', 'confirmed', $cashierId, 'ยืนยันรับเงินมัดจำ — ระบบจัดสรรและล็อกช่องจอด', $paidAt);
                $this->logTransition($reservation, 'confirmed', 'checked_in', null, 'Check-in อัตโนมัติ: รถเข้าจอด', $checkedInAt);
                $this->logTransition($reservation, 'checked_in', 'completed', null, 'Check-out อัตโนมัติ: รถออกจากลานแล้ว', $completedAt);

                $log = $this->parkingLog($reservation, $slotId, $checkedInAt, $completedAt);
                $this->checkoutPayment($log, $reservation, $rate, $cashierId);
                break;

            case 'checked_in':
                $slotId = $this->pickSlotFromPool($lot->id);
                if (!$slotId) {
                    return;
                }

                $checkedInAt = now()->subMinutes(random_int(10, 180));
                $reserveStart = $checkedInAt->copy()->subMinutes(random_int(0, 15));
                $bookedAt = $reserveStart->copy()->subHours(random_int(1, 20));
                $paidAt = $bookedAt->copy()->addMinutes(random_int(5, 50));

                ParkingSlot::whereKey($slotId)->update(['status' => 'occupied']);

                $reservation = Reservation::create($base + [
                    'parking_slot_id' => $slotId,
                    'reserve_start' => $reserveStart,
                    'checked_in_at' => $checkedInAt,
                    'status' => 'checked_in',
                    'created_at' => $bookedAt,
                    'updated_at' => $checkedInAt,
                ]);

                $this->logTransition($reservation, null, 'pending', $user->id, 'ผู้ใช้สร้างการจอง', $bookedAt);
                $this->depositPayment($reservation, 'paid', $cashierId, $bookedAt, $paidAt);
                $this->logTransition($reservation, 'pending', 'confirmed', $cashierId, 'ยืนยันรับเงินมัดจำ — ระบบจัดสรรและล็อกช่องจอด', $paidAt);
                $this->logTransition($reservation, 'confirmed', 'checked_in', null, 'Check-in อัตโนมัติ: รถเข้าจอด', $checkedInAt);

                $this->parkingLog($reservation, $slotId, $checkedInAt, null);
                break;

            case 'confirmed':
                // Slot ถูก Lock เมื่อยืนยันรับเงิน Deposit แล้ว
                $slotId = $this->pickSlotFromPool($lot->id);
                if (!$slotId) {
                    return;
                }

                $bookedAt = now()->subMinutes(random_int(60, 180));
                $paidAt = $bookedAt->copy()->addMinutes(random_int(5, 30));
                $reserveStart = now()->addMinutes(random_int(30, 20 * 60)); // ไม่เกิน 1 วันจากเวลาจอง

                ParkingSlot::whereKey($slotId)->update(['status' => 'reserved']);

                $reservation = Reservation::create($base + [
                    'parking_slot_id' => $slotId,
                    'reserve_start' => $reserveStart,
                    'status' => 'confirmed',
                    'created_at' => $bookedAt,
                    'updated_at' => $paidAt,
                ]);

                $this->logTransition($reservation, null, 'pending', $user->id, 'ผู้ใช้สร้างการจอง', $bookedAt);
                $this->depositPayment($reservation, 'paid', $cashierId, $bookedAt, $paidAt);
                $this->logTransition($reservation, 'pending', 'confirmed', $cashierId, 'ยืนยันรับเงินมัดจำ — ระบบจัดสรรและล็อกช่องจอด', $paidAt);
                break;

            case 'pending':
                // ยังไม่ยืนยันรับเงิน → ไม่ถือครอง Slot
                $bookedAt = now()->subMinutes(random_int(0, 360));
                $reserveStart = now()->addMinutes(random_int(60, 17 * 60));

                $reservation = Reservation::create($base + [
                    'parking_slot_id' => null,
                    'reserve_start' => $reserveStart,
                    'status' => 'pending',
                    'created_at' => $bookedAt,
                    'updated_at' => $bookedAt,
                ]);

                $this->logTransition($reservation, null, 'pending', $user->id, 'ผู้ใช้สร้างการจอง', $bookedAt);
                $this->depositPayment($reservation, 'unpaid', null, $bookedAt, null);
                break;

            case 'cancelled':
                $reserveStart = now()->addHours(random_int(-72, 20));
                $bookedAt = $reserveStart->copy()->subHours(random_int(2, 20))->min(now()->subMinutes(20));
                $windowEnd = $reserveStart->copy()->min(now());
                $window = max(10, (int) $bookedAt->diffInMinutes($windowEnd));
                $cancelledAt = $bookedAt->copy()->addMinutes(intdiv($window, 2));
                $wasPaid = random_int(1, 10) <= 5;

                $reservation = Reservation::create($base + [
                    'parking_slot_id' => null,
                    'reserve_start' => $reserveStart,
                    'status' => 'cancelled',
                    'created_at' => $bookedAt,
                    'updated_at' => $cancelledAt,
                ]);

                $this->logTransition($reservation, null, 'pending', $user->id, 'ผู้ใช้สร้างการจอง', $bookedAt);

                if ($wasPaid) {
                    // ยกเลิกหลังชำระ Deposit → ไม่คืนเงิน (Payment คงสถานะ paid)
                    $paidAt = $bookedAt->copy()->addMinutes(intdiv($window, 4));
                    $this->depositPayment($reservation, 'paid', $cashierId, $bookedAt, $paidAt);
                    $this->logTransition($reservation, 'pending', 'confirmed', $cashierId, 'ยืนยันรับเงินมัดจำ — ระบบจัดสรรและล็อกช่องจอด', $paidAt);
                    $this->logTransition($reservation, 'confirmed', 'cancelled', $user->id, 'ผู้ใช้ยกเลิกการจอง', $cancelledAt);
                } else {
                    // ยกเลิกก่อนชำระ Deposit → void
                    $this->depositPayment($reservation, 'void', null, $bookedAt, null, $cancelledAt);
                    $this->logTransition($reservation, 'pending', 'cancelled', $user->id, 'ผู้ใช้ยกเลิกการจอง', $cancelledAt);
                }
                break;

            case 'expired':
                $reserveStart = now()->subHours(random_int(2, 72));
                $bookedAt = $reserveStart->copy()->subHours(random_int(1, 20));
                $expiredAt = $reserveStart->copy()->addMinutes(60);
                $wasPaid = random_int(1, 10) <= 5;

                $reservation = Reservation::create($base + [
                    'parking_slot_id' => null,
                    'reserve_start' => $reserveStart,
                    'status' => 'expired',
                    'created_at' => $bookedAt,
                    'updated_at' => $expiredAt,
                ]);

                $this->logTransition($reservation, null, 'pending', $user->id, 'ผู้ใช้สร้างการจอง', $bookedAt);

                if ($wasPaid) {
                    $paidAt = $bookedAt->copy()->addMinutes(random_int(10, 50));
                    $this->depositPayment($reservation, 'paid', $cashierId, $bookedAt, $paidAt);
                    $this->logTransition($reservation, 'pending', 'confirmed', $cashierId, 'ยืนยันรับเงินมัดจำ — ระบบจัดสรรและล็อกช่องจอด', $paidAt);
                    $this->logTransition($reservation, 'confirmed', 'expired', null, 'หมดอายุอัตโนมัติ: ไม่ Check-in ภายใน 60 นาทีหลังเวลาจอง', $expiredAt);
                } else {
                    $this->depositPayment($reservation, 'void', null, $bookedAt, null, $expiredAt);
                    $this->logTransition($reservation, 'pending', 'expired', null, 'หมดอายุอัตโนมัติ: ไม่ Check-in ภายใน 60 นาทีหลังเวลาจอง', $expiredAt);
                }
                break;
        }
    }

    // ================= Walk-in (Reservation ของ Walkin User) =================

    /** @param array<int,ParkingLot> $lots */
    private function seedWalkInReservations(User $walkin, array $lots, User $admin): void
    {
        // Walk-in ที่จบไปแล้ว
        for ($i = 0; $i < 15; $i++) {
            $lot = $lots[array_rand($lots)];
            $checkIn = now()->subDays(random_int(1, 30))->subHours(random_int(0, 12))->setSecond(0);
            $checkOut = $checkIn->copy()->addMinutes(random_int(30, 300));
            $slotId = $this->pickAnySlot($lot->id);

            $reservation = Reservation::create(array_merge($this->makeCar(), [
                'user_id' => $walkin->id,
                'is_walk_in' => true,
                'parking_lot_id' => $lot->id,
                'parking_slot_id' => $slotId,
                'reserve_start' => $checkIn,
                'checked_in_at' => $checkIn,
                'completed_at' => $checkOut,
                'deposit_amount' => 0,
                'reservation_fee' => 0,
                'status' => 'completed',
                'created_at' => $checkIn,
                'updated_at' => $checkOut,
            ]));

            $this->logTransition($reservation, null, 'checked_in', null, 'Walk-in: ระบบสร้างการจองและเช็คอินอัตโนมัติ', $checkIn);
            $this->logTransition($reservation, 'checked_in', 'completed', null, 'Check-out อัตโนมัติ: รถออกจากลานแล้ว', $checkOut);

            $log = $this->parkingLog($reservation, $slotId, $checkIn, $checkOut);
            $this->checkoutPayment($log, $reservation, (float) $lot->hourly_rate, $this->cashierId($lot, $admin));
        }

        // Walk-in ที่กำลังจอดอยู่ตอนนี้
        for ($i = 0; $i < 3; $i++) {
            $lot = $lots[array_rand($lots)];
            $slotId = $this->pickSlotFromPool($lot->id);
            if (!$slotId) {
                continue;
            }
            ParkingSlot::whereKey($slotId)->update(['status' => 'occupied']);

            $checkIn = now()->subMinutes(random_int(5, 120));

            $reservation = Reservation::create(array_merge($this->makeCar(), [
                'user_id' => $walkin->id,
                'is_walk_in' => true,
                'parking_lot_id' => $lot->id,
                'parking_slot_id' => $slotId,
                'reserve_start' => $checkIn,
                'checked_in_at' => $checkIn,
                'deposit_amount' => 0,
                'reservation_fee' => 0,
                'status' => 'checked_in',
                'created_at' => $checkIn,
                'updated_at' => $checkIn,
            ]));

            $this->logTransition($reservation, null, 'checked_in', null, 'Walk-in: ระบบสร้างการจองและเช็คอินอัตโนมัติ', $checkIn);
            $this->parkingLog($reservation, $slotId, $checkIn, null);
        }
    }

    // ================= Logs / Payments helpers =================

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

    private function parkingLog(Reservation $reservation, ?int $slotId, Carbon $checkIn, ?Carbon $checkOut): ParkingLog
    {
        return ParkingLog::create([
            'parking_lot_id' => $reservation->parking_lot_id,
            'parking_slot_id' => $slotId,
            'reservation_id' => $reservation->id,
            'license_plate' => $reservation->license_plate,
            'plate_province' => $reservation->plate_province,
            'brand' => $reservation->brand,
            'color' => $reservation->color,
            'hourly_rate' => ParkingLot::whereKey($reservation->parking_lot_id)->value('hourly_rate'),
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            'created_at' => $checkIn,
            'updated_at' => $checkOut ?? $checkIn,
        ]);
    }

    /** Deposit Payment — status: unpaid (รอรับเงิน) / paid (ยืนยันรับเงินแล้ว) / void (ยกเลิกหรือหมดอายุก่อนชำระ) */
    private function depositPayment(Reservation $reservation, string $status, ?int $paidBy, Carbon $createdAt, ?Carbon $paidAt, ?Carbon $voidAt = null): Payment
    {
        return Payment::create([
            'type' => Payment::TYPE_DEPOSIT,
            'reservation_id' => $reservation->id,
            'parking_log_id' => null,
            'hourly_rate' => $reservation->deposit_amount,
            'total_hours' => null,
            'parking_fee' => 0,
            'deposit_deduction' => 0,
            'reservation_discount' => 0,
            'total_amount' => $reservation->deposit_amount,
            'payment_status' => $status,
            'paid_by' => $status === 'paid' ? $paidBy : null,
            'paid_at' => $status === 'paid' ? $paidAt : null,
            'created_at' => $createdAt,
            'updated_at' => $paidAt ?? $voidAt ?? $createdAt,
        ]);
    }

    /** Checkout Payment — ค่าจอดจริง − Deposit − reservation_fee (ไม่ติดลบ) */
    private function checkoutPayment(ParkingLog $log, Reservation $reservation, float $rate, int $cashierId): Payment
    {
        $totalHours = max(1, (int) ceil($log->check_in_time->diffInMinutes($log->check_out_time) / 60));
        $parkingFee = round($totalHours * $rate, 2);
        $depositDeduction = min((float) $reservation->deposit_amount, $parkingFee);
        $discount = min((float) $reservation->reservation_fee, $parkingFee - $depositDeduction);
        $totalAmount = round($parkingFee - $depositDeduction - $discount, 2);

        $autoPaid = $totalAmount <= 0;
        $paid = $autoPaid || random_int(1, 10) <= 8;
        $paidAt = $autoPaid ? $log->check_out_time : $log->check_out_time->copy()->addMinutes(random_int(5, 120));

        return Payment::create([
            'type' => Payment::TYPE_CHECKOUT,
            'reservation_id' => $reservation->id,
            'parking_log_id' => $log->id,
            'hourly_rate' => $rate,
            'total_hours' => $totalHours,
            'parking_fee' => $parkingFee,
            'deposit_deduction' => $depositDeduction,
            'reservation_discount' => $discount,
            'total_amount' => $totalAmount,
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'paid_by' => $paid && !$autoPaid ? $cashierId : null,
            'paid_at' => $paid ? $paidAt : null,
            'created_at' => $log->check_out_time,
            'updated_at' => $paid ? $paidAt : $log->check_out_time,
        ]);
    }

    // ================= Suspicious Vehicles (Blacklist: ทะเบียน + จังหวัด) =================

    private function seedSuspiciousVehicles(User $admin): Collection
    {
        $reasons = [
            'แจ้งความรถหาย',
            'ค้างชำระค่าจอดหลายครั้ง',
            'พบพฤติกรรมต้องสงสัยจากกล้องวงจรปิด',
            'ตำรวจแจ้งเตือนให้เฝ้าระวัง',
            'ป้ายทะเบียนปลอม / ไม่ตรงกับฐานข้อมูลกรมขนส่ง',
            'มีประวัติทำลายทรัพย์สินในลานจอด',
        ];

        // 2 รายการ อ้างอิงจากรถที่เคยเข้าใช้บริการ (จำลองเคสที่ตรวจพบ)
        $sample = Reservation::inRandomOrder()->limit(2)->get(['license_plate', 'plate_province']);
        foreach ($sample as $car) {
            SuspiciousVehicle::create([
                'license_plate' => $car->license_plate,
                'plate_province' => $car->plate_province,
                'reason' => $reasons[array_rand($reasons)],
                'level' => 'high',
                'is_active' => true,
                'added_by' => $admin->id,
                'created_at' => now()->subDays(random_int(1, 30)),
            ]);
        }

        // อีก 4 รายการ เป็นรถต้องสงสัยทั่วไป
        for ($i = 0; $i < 4; $i++) {
            $car = $this->makeCar();
            SuspiciousVehicle::create([
                'license_plate' => $car['license_plate'],
                'plate_province' => $car['plate_province'],
                'reason' => $reasons[array_rand($reasons)],
                'level' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                'is_active' => random_int(1, 10) <= 8,
                'added_by' => $admin->id,
                'created_at' => now()->subDays(random_int(1, 60)),
            ]);
        }

        return SuspiciousVehicle::all();
    }

    // ================= License Plate Scans (AI Scan — Upload จำลองกล้อง) =================

    /**
     * @param array<int,User> $renters
     * @param array<int,ParkingLot> $lots
     */
    private function seedLicensePlateScans(array $renters, User $admin, array $lots, Collection $blacklist): void
    {
        $activeBlacklist = $blacklist->where('is_active', true)->values();

        for ($i = 0; $i < 20; $i++) {
            $lot = $lots[array_rand($lots)];
            // การอัปโหลดเป็นการจำลองกล้อง ไม่ขึ้นกับผู้อัปโหลด
            $uploaderId = match (random_int(1, 3)) {
                1 => $lot->owner_id ?? $admin->id,
                2 => $admin->id,
                default => $renters[array_rand($renters)]->id,
            };

            $roll = random_int(1, 20);
            if ($roll === 1) {
                // AI อ่านทะเบียนไม่ได้
                $plate = null;
                $province = null;
                $brand = null;
                $color = null;
                $confidence = random_int(1000, 4000) / 100;
            } else {
                $car = ($roll <= 4 && $activeBlacklist->isNotEmpty())
                    ? $activeBlacklist->random()
                    : Reservation::inRandomOrder()->first();

                $plate = $car->license_plate;
                $province = $car->plate_province;
                $brand = $car->brand ?? $this->brands[array_rand($this->brands)];
                $color = $car->color ?? $this->colors[array_rand($this->colors)];
                $confidence = random_int(7000, 9950) / 100;
            }

            $isSuspicious = $plate !== null && $activeBlacklist
                ->where('license_plate', $plate)
                ->where('plate_province', $province)
                ->isNotEmpty();

            $scanTime = now()->subDays(random_int(0, 45))->subHours(random_int(0, 20));

            LicensePlateScan::create([
                'user_id' => $uploaderId,
                'parking_lot_id' => $lot->id,
                'license_plate' => $plate,
                'plate_province' => $province,
                'brand' => $brand,
                'color' => $color,
                'confidence' => $confidence,
                'result' => LicensePlateScan::classify($plate, $province, $confidence),
                'is_suspicious' => $isSuspicious,
                'source' => 'manual_upload',
                // ข้อมูลตัวอย่างไม่มีไฟล์ภาพจริง จึงไม่ใส่ path (ถ้าใส่ หน้าประวัติสแกนจะโหลดภาพไม่ได้ = 403)
                'image_path' => null,
                'scan_time' => $scanTime,
                'created_at' => $scanTime,
                'updated_at' => $scanTime,
            ]);
        }
    }

    // ================= Notifications =================

    /**
     * @param array<int,User> $renters
     * @param array<int,User> $owners
     */
    private function seedNotifications(array $renters, array $owners): void
    {
        $templates = [
            ['title' => 'การจองได้รับการยืนยัน', 'body' => 'ยืนยันรับเงินมัดจำแล้ว การจองของคุณได้รับการยืนยัน กรุณาเช็คอินภายในเวลาที่กำหนด'],
            ['title' => 'เช็คอินสำเร็จ', 'body' => 'รถของคุณเข้าจอดในระบบเรียบร้อยแล้ว'],
            ['title' => 'เช็คเอาท์เรียบร้อย', 'body' => 'รถของคุณออกจากลานแล้ว ขอบคุณที่ใช้บริการ'],
            ['title' => 'การจองถูกยกเลิก', 'body' => 'การจองของคุณถูกยกเลิกเรียบร้อยแล้ว'],
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

    // ================= Audit Logs (ทุก Role + System) =================

    private function audit(?User $actor, string $action, ?string $subjectType, ?int $subjectId, array $meta, Carbon $at): void
    {
        AdminAction::create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role ?? 'system',
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta ?: null,
            'ip_address' => $actor ? '127.0.0.1' : null,
            'user_agent' => $actor ? 'Mozilla/5.0 (Seeder)' : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function seedAuditLogs(User $admin): void
    {
        $users = User::all()->keyBy('id');

        // Admin: force password reset
        $targets = User::where('role', 'user')->where('is_system', false)->inRandomOrder()->limit(3)->get();
        foreach ($targets as $target) {
            $target->update(['force_password_reset' => true]);
            $this->audit($admin, 'user.force_reset', 'User', $target->id, ['force_password_reset' => true], now()->subDays(random_int(1, 20)));
        }

        // User: ส่งคำขอเป็น Owner · Admin: อนุมัติ/ปฏิเสธ
        foreach (OwnerApplication::all() as $app) {
            $applicant = $users[$app->user_id];
            $this->audit($applicant, 'owner_application.submit', 'OwnerApplication', $app->id, [], $app->created_at);

            if ($app->status === 'approved') {
                $this->audit($admin, 'owner_application.approve', 'OwnerApplication', $app->id, [], $app->reviewed_at);
            } elseif ($app->status === 'rejected') {
                $this->audit($admin, 'owner_application.reject', 'OwnerApplication', $app->id, ['reason' => $app->rejection_reason], $app->reviewed_at);
            }
        }

        // Admin: เพิ่มรายการ Blacklist
        foreach (SuspiciousVehicle::all() as $sv) {
            $this->audit($admin, 'suspicious_vehicle.create', 'SuspiciousVehicle', $sv->id, [
                'license_plate' => $sv->license_plate,
                'plate_province' => $sv->plate_province,
                'level' => $sv->level,
            ], $sv->created_at);
        }

        // User: สร้าง / ยกเลิกการจอง (ReservationLog ที่ผู้ใช้เป็นผู้กระทำ)
        $userLogs = ReservationLog::whereNotNull('changed_by')
            ->whereIn('new_status', ['pending', 'cancelled'])
            ->get();
        foreach ($userLogs as $log) {
            $actor = $users[$log->changed_by];
            $action = $log->new_status === 'pending' ? 'reservation.create' : 'reservation.cancel';
            $this->audit($actor, $action, 'Reservation', $log->reservation_id, [], $log->created_at);

            // Payment Log: Deposit เกิดพร้อมการจอง · ยกเลิกก่อนชำระ → void
            $deposit = Payment::where('reservation_id', $log->reservation_id)->where('type', Payment::TYPE_DEPOSIT)->first();
            if ($deposit && $log->new_status === 'pending') {
                $this->audit($actor, 'payment.deposit_created', 'Payment', $deposit->id, ['reservation_id' => $log->reservation_id, 'total_amount' => $deposit->total_amount], $log->created_at);
            } elseif ($deposit?->payment_status === Payment::STATUS_VOID) {
                $this->audit($actor, 'payment.void', 'Payment', $deposit->id, ['reservation_id' => $log->reservation_id, 'reason' => 'reservation_cancelled'], $log->created_at);
            }
        }

        // Admin / Owner: ยืนยันรับเงิน (Mark as Paid)
        foreach (Payment::whereNotNull('paid_by')->get() as $payment) {
            $this->audit($users[$payment->paid_by], 'payment.mark_paid', 'Payment', $payment->id, [
                'type' => $payment->type,
                'reservation_id' => $payment->reservation_id,
                'total_amount' => $payment->total_amount,
            ], $payment->paid_at);
        }

        // System: Walk-in / Auto check-in / Auto check-out / Expire
        $systemLogs = ReservationLog::whereNull('changed_by')
            ->whereIn('new_status', ['checked_in', 'completed', 'expired'])
            ->get();
        foreach ($systemLogs as $log) {
            [$action, $meta] = match (true) {
                $log->new_status === 'expired'   => ['reservation.expire', []],
                $log->new_status === 'completed' => ['reservation.check_out', ['mode' => 'auto']],
                $log->old_status === null        => ['reservation.walk_in', []],
                default                          => ['reservation.check_in', ['mode' => 'auto']],
            };
            $this->audit(null, $action, 'Reservation', $log->reservation_id, $meta, $log->created_at);
        }

        // Admin: ตัวอย่างประวัติการเพิ่ม/ลบผู้ใช้ (audit trail ของบัญชีที่ไม่มีอยู่แล้ว)
        $this->audit($admin, 'user.create', 'User', 9990, ['role' => 'user'], now()->subDays(40));
        $this->audit($admin, 'user.delete', 'User', 9991, ['role' => 'owner', 'lots_deleted' => 1, 'reservations_cancelled' => 2], now()->subDays(12));
    }
}
