<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
