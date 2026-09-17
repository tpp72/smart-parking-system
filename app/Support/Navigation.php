<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\OwnerApplication;
use App\Models\OwnerResignation;
use App\Models\ParkingLot;
use App\Models\Payment;
use App\Models\User;

/**
 * เมนูของ App Shell (Hybrid Navigation — docs/PRODUCT.md)
 * Admin / Owner: sidebar จัดกลุ่ม + แถบล่างบนมือถือ · User: แถบบน + แท็บล่าง 5 ช่อง
 * ทุกหน้าที่ผู้ใช้มีสิทธิ์ต้องเข้าได้จากเมนู — ใช้ route name จริง ไม่สร้าง route ใหม่
 */
final class Navigation
{
    public const ROLE_LABELS = [
        'admin' => 'ผู้ดูแลระบบ',
        'owner' => 'เจ้าของลาน',
        'user'  => 'ผู้ใช้',
    ];

    public static function for(User $user): array
    {
        // ต้องตั้งรหัสผ่านใหม่ / ยังไม่ยืนยันอีเมล → ใช้ได้แค่โปรไฟล์และออกจากระบบ (project-plan.md §19.4)
        if ($user->force_password_reset || ! $user->hasVerifiedEmail()) {
            return self::limited($user);
        }

        return match ($user->role) {
            'admin' => self::admin($user),
            'owner' => self::owner($user),
            default => self::user($user),
        };
    }

    private static function admin(User $user): array
    {
        $unpaid = self::unpaidPayments(ParkingLot::unowned()->select('id'));

        $groups = [
            self::group('ปฏิบัติการ', [
                self::item('dashboard', 'ภาพรวม', 'admin.dashboard', 'dashboard'),
                self::item('reservations', 'การจอง', 'admin.reservations.index', 'ticket', 'admin.reservations.*'),
                self::item('scan', 'AI สแกน', 'admin.scan.create', 'scan', ['admin.scan.create', 'admin.scan.store']),
                self::item('scan-history', 'ประวัติสแกน', 'admin.scan.history', 'scan-history'),
            ]),
            self::group('ลานจอด', [
                self::item('lots', 'ลานจอด', 'admin.parking-lots.index', 'lot', 'admin.parking-lots.*'),
                self::item('slots', 'ช่องจอด', 'admin.parking-slots.index', 'slots', 'admin.parking-slots.*'),
            ]),
            self::group('การเงิน', [
                self::item('payments', 'ชำระเงิน', 'admin.payments.index', 'payment', 'admin.payments.*',
                    badge: $unpaid, badgeLabel: "ค้างชำระ {$unpaid} รายการ"),
            ]),
            self::group('ผู้ใช้และความปลอดภัย', [
                self::item('users', 'ผู้ใช้', 'admin.users.index', 'users', 'admin.users.*'),
                self::item('owner-applications', 'คำขอเป็นเจ้าของลาน', 'admin.owner-applications.index', 'application',
                    'admin.owner-applications.*', badge: $applications = OwnerApplication::where('status', 'pending')->count(),
                    badgeLabel: "รอพิจารณา {$applications} คำขอ"),
                self::item('owner-resignations', 'คำร้องลาออก', 'admin.owner-resignations.index', 'resignation',
                    'admin.owner-resignations.*', badge: $resignations = OwnerResignation::where('status', OwnerResignation::STATUS_PENDING)->count(),
                    badgeLabel: "รอพิจารณา {$resignations} คำร้อง"),
                self::item('blacklist', 'บัญชีดำ', 'admin.suspicious-vehicles.index', 'blacklist', 'admin.suspicious-vehicles.*'),
            ]),
            self::group('รายงาน', [
                self::item('parking-logs', 'ประวัติการจอด', 'admin.parking-logs.index', 'parking-log', 'admin.parking-logs.*'),
                self::item('reservation-logs', 'Log การจอง', 'admin.reservation-logs.index', 'reservation-log', 'admin.reservation-logs.*'),
                self::item('audit-log', 'Audit Log', 'admin.admin-actions.index', 'audit', 'admin.admin-actions.*'),
                self::item('exports', 'ส่งออก CSV', 'admin.exports.index', 'export', 'admin.exports.*'),
            ]),
        ];

        return self::staff($user, 'ทุกลานในระบบ', 'admin.dashboard', $groups,
            bottom: ['dashboard', 'reservations', 'payments', 'scan'],
            account: [self::item('profile', 'โปรไฟล์', 'profile.edit', 'user')]);
    }

    private static function owner(User $user): array
    {
        $unpaid = self::unpaidPayments(ParkingLot::ownedBy($user->id)->select('id'));

        $groups = [
            self::group('ปฏิบัติการ', [
                self::item('dashboard', 'ภาพรวม', 'owner.dashboard', 'dashboard'),
                self::item('reservations', 'การจอง', 'owner.reservations.index', 'ticket', 'owner.reservations.*'),
                self::item('scan', 'AI สแกน', 'owner.scan.create', 'scan', ['owner.scan.create', 'owner.scan.store']),
                self::item('scan-history', 'ประวัติสแกน', 'owner.scan.history', 'scan-history'),
            ]),
            self::group('ลานจอด', [
                self::item('lots', 'ลานจอด', 'owner.parking-lots.index', 'lot', 'owner.parking-lots.*'),
                self::item('slots', 'ช่องจอด', 'owner.parking-slots.index', 'slots', 'owner.parking-slots.*'),
            ]),
            self::group('การเงิน', [
                self::item('payments', 'ชำระเงิน', 'owner.payments.index', 'payment', 'owner.payments.*',
                    badge: $unpaid, badgeLabel: "ค้างชำระ {$unpaid} รายการ"),
                self::item('revenue', 'รายได้', 'owner.revenue.index', 'revenue', 'owner.revenue.*'),
            ]),
            self::group('รายงาน', [
                self::item('parking-logs', 'ประวัติการจอด', 'owner.parking-logs.index', 'parking-log', 'owner.parking-logs.*'),
                self::item('reservation-logs', 'Log การจอง', 'owner.reservation-logs.index', 'reservation-log', 'owner.reservation-logs.*'),
            ]),
        ];

        return self::staff($user, 'ลานของฉัน', 'owner.dashboard', $groups,
            bottom: ['dashboard', 'reservations', 'payments', 'scan'],
            account: [
                self::item('profile', 'โปรไฟล์', 'profile.edit', 'user'),
                self::item('owner-status', 'สถานะเจ้าของลาน', 'owner.application.show', 'application', 'owner.application.*'),
                self::item('owner-resign', 'ลาออกจากการเป็นเจ้าของลาน', 'owner.dashboard', 'resignation', [], fragment: 'owner-resignation'),
            ]);
    }

    private static function user(User $user): array
    {
        $unread = self::unreadNotifications($user);
        $hasApplication = OwnerApplication::where('user_id', $user->id)->exists();

        $home = self::item('home', 'หน้าหลัก', 'user.dashboard', 'home');
        $book = self::item('book', 'จองที่จอด', 'user.reservations.create', 'plus');
        $reservations = self::item('reservations', 'การจองของฉัน', 'user.reservations.index', 'ticket',
            ['user.reservations.index', 'user.reservations.show', 'user.reservations.edit']);
        $history = self::item('history', 'ประวัติการจอด', 'user.parking-logs.index', 'parking-log', 'user.parking-logs.*');
        $scan = self::item('scan', 'AI สแกน', 'user.scan.create', 'scan', 'user.scan.*');
        $notifications = self::item('notifications', 'แจ้งเตือน', 'notifications.index', 'bell', 'notifications.*',
            badge: $unread, badgeLabel: "ยังไม่อ่าน {$unread} รายการ");
        $owner = $hasApplication
            ? self::item('owner-application', 'คำขอเป็นเจ้าของลาน', 'owner.application.show', 'store', 'owner.application.*')
            : self::item('owner-application', 'สมัครเป็นเจ้าของลาน', 'owner.application.create', 'store', 'owner.application.*');
        $profile = self::item('profile', 'โปรไฟล์', 'profile.edit', 'user');

        $account = [$profile, $history, $scan, $owner];

        return [
            'role' => 'user',
            'staff' => false,
            'limited' => false,
            'name' => $user->name,
            'email' => $user->email,
            'roleLabel' => self::ROLE_LABELS['user'],
            'homeHref' => route('user.dashboard'),
            // Desktop: แถบบน (จองที่จอดแยกเป็นปุ่มหลัก)
            'top' => [$home, $reservations, $history, $scan],
            'primary' => $book,
            // มือถือ: หน้าหลัก / จอง / การจอง / แจ้งเตือน / บัญชี
            'bottom' => [
                [...$home],
                [...$book, 'label' => 'จอง'],
                [...$reservations, 'label' => 'การจอง'],
                $notifications,
                self::drawerTrigger('account', 'บัญชี', 'user', collect($account)->contains('active', true)),
            ],
            'account' => $account,
            'notifications' => $notifications,
            'current' => self::current([['label' => null, 'items' => [...$account, $home, $book, $reservations, $notifications]]]),
        ];
    }

    private static function limited(User $user): array
    {
        $profile = self::item('profile', 'โปรไฟล์', 'profile.edit', 'user');

        return [
            'role' => $user->role,
            'staff' => false,
            'limited' => true,
            'limitedReason' => $user->force_password_reset
                ? 'ตั้งรหัสผ่านใหม่ก่อนจึงจะใช้เมนูอื่นได้'
                : 'ยืนยันอีเมลก่อนจึงจะใช้เมนูอื่นได้',
            'name' => $user->name,
            'email' => $user->email,
            'roleLabel' => self::ROLE_LABELS[$user->role] ?? self::ROLE_LABELS['user'],
            'homeHref' => route('profile.edit'),
            'top' => [],
            'primary' => null,
            'bottom' => [],
            'account' => [$profile],
            'notifications' => null,
            'current' => $profile['active'] ? ['group' => null, 'label' => $profile['label']] : null,
        ];
    }

    private static function staff(User $user, string $scope, string $homeRoute, array $groups, array $bottom, array $account): array
    {
        $unread = self::unreadNotifications($user);
        $items = collect($groups)->flatMap(fn ($group) => $group['items'])->keyBy('key');
        $notifications = self::item('notifications', 'แจ้งเตือน', 'notifications.index', 'bell', 'notifications.*',
            badge: $unread, badgeLabel: "ยังไม่อ่าน {$unread} รายการ");

        $menuActive = ! collect($bottom)->contains(fn ($key) => $items[$key]['active']);
        $groupBadge = $items->except($bottom)->sum('badge');

        return [
            'role' => $user->role,
            'staff' => true,
            'limited' => false,
            'name' => $user->name,
            'email' => $user->email,
            'roleLabel' => self::ROLE_LABELS[$user->role],
            'scope' => $scope,
            'homeHref' => route($homeRoute),
            'groups' => $groups,
            'bottom' => [
                ...collect($bottom)->map(fn ($key) => $items[$key])->all(),
                self::drawerTrigger('menu', 'เมนู', 'menu', $menuActive, $groupBadge,
                    $groupBadge ? "มีรายการรอดำเนินการ {$groupBadge} รายการ" : null),
            ],
            'account' => $account,
            'notifications' => $notifications,
            'current' => self::current([...$groups, ['label' => null, 'items' => [...$account, $notifications]]]),
        ];
    }

    private static function group(string $label, array $items): array
    {
        return ['label' => $label, 'items' => $items];
    }

    private static function item(
        string $key,
        string $label,
        string $route,
        string $icon,
        string|array|null $active = null,
        int $badge = 0,
        ?string $badgeLabel = null,
        ?string $fragment = null,
    ): array {
        $patterns = (array) ($active ?? $route);

        return [
            'key' => $key,
            'label' => $label,
            'href' => route($route).($fragment ? "#{$fragment}" : ''),
            'icon' => $icon,
            'active' => $patterns !== [] && request()->routeIs(...$patterns),
            'badge' => $badge,
            'badgeLabel' => $badge > 0 ? $badgeLabel : null,
            'drawer' => null,
        ];
    }

    /** ปุ่มแถบล่างที่เปิด Drawer (เมนู / บัญชี) แทนการลิงก์ไปหน้าใหม่ */
    private static function drawerTrigger(string $key, string $label, string $icon, bool $active, int $badge = 0, ?string $badgeLabel = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'href' => null,
            'icon' => $icon,
            'active' => $active,
            'badge' => $badge,
            'badgeLabel' => $badgeLabel,
            'drawer' => 'sp-nav',
        ];
    }

    /** ตำแหน่งปัจจุบันสำหรับ breadcrumb: ['group' => ?, 'label' => ?] */
    private static function current(array $groups): ?array
    {
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                if ($item['active'] && $item['href'] !== null) {
                    return ['group' => $group['label'], 'label' => $item['label']];
                }
            }
        }

        return null;
    }

    /** งานการเงินที่ต้องดำเนินการ = มัดจำค้าง + ค่าจอดค้าง ในลานตามขอบเขต (ตรงกับหน้าชำระเงิน) */
    private static function unpaidPayments($lotIds): int
    {
        return Payment::where('payment_status', Payment::STATUS_UNPAID)
            ->where(fn ($q) => $q
                ->whereHas('parkingLog', fn ($x) => $x->whereIn('parking_lot_id', $lotIds))
                ->orWhereHas('reservation', fn ($x) => $x->whereIn('parking_lot_id', $lotIds)))
            ->count();
    }

    private static function unreadNotifications(User $user): int
    {
        return Notification::where('user_id', $user->id)->where('is_read', false)->count();
    }
}
