<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

class Payment extends Model
{
    /** เงินมัดจำ — เกิดตอนสร้าง Reservation ก่อน Check-in */
    const TYPE_DEPOSIT = 'deposit';

    /** ยอดคงเหลือหลัง Check-out */
    const TYPE_CHECKOUT = 'checkout';

    const STATUS_UNPAID = 'unpaid';
    const STATUS_PAID   = 'paid';

    /** ยกเลิกรายการโดยไม่มีการรับเงิน */
    const STATUS_VOID   = 'void';

    protected $guarded = [];

    protected $casts = [
        'hourly_rate'          => 'decimal:2',
        'total_hours'          => 'decimal:2',
        'parking_fee'          => 'decimal:2',
        'deposit_deduction'    => 'decimal:2',
        'reservation_discount' => 'decimal:2',
        // ยอดค่าจอดที่ชำระไปแล้วในใบก่อนหน้าของการจอดครั้งเดียวกัน (ชำระแล้วไม่ออกภายในเวลา → ใบใหม่เก็บส่วนที่เกิน)
        'prior_paid'           => 'decimal:2',
        'total_amount'         => 'decimal:2',
        'paid_at'              => 'datetime',
    ];

    public function parkingLog()
    {
        return $this->belongsTo(ParkingLog::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    /** ผู้ยืนยันรับเงิน (Mark as Paid) */
    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * join ใบค่าจอดใบล่าสุดของแต่ละการจอด (ไม่นับใบที่ยกเลิก)
     *
     * การจอดครั้งเดียวมีได้หลายใบเมื่อชำระแล้วไม่สแกนออกภายในเวลา (§12.6) — leftJoin ตรง ๆ จะได้แถวซ้ำ
     * ใบล่าสุดอธิบายยอดทั้งหมดได้ในตัวเอง: ยอดรวม = prior_paid + total_amount
     */
    public static function joinLatestCheckout(QueryBuilder $query, string $alias = 'p', string $logColumn = 'pl.id'): QueryBuilder
    {
        return $query->leftJoin("payments as {$alias}", fn ($join) => $join->whereRaw(
            "{$alias}.id = (select max(x.id) from payments x where x.parking_log_id = {$logColumn} and x.type = ? and x.payment_status <> ?)",
            [self::TYPE_CHECKOUT, self::STATUS_VOID]
        ));
    }
}
