<?php

namespace App\Models;

use App\Models\Concerns\MatchesLicensePlate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingLog extends Model
{
    use HasFactory;
    use MatchesLicensePlate;

    protected $guarded = [];

    protected $casts = [
        'check_in_time'         => 'datetime',
        'check_out_time'        => 'datetime',
        // เวลาที่คนขับกด Check-out = เวลาที่ล็อกยอดไว้ (§12.6) · null = ยังไม่ได้กด
        'checkout_requested_at' => 'datetime',
        // อัตราค่าจอด ณ ตอน Check-in
        'hourly_rate'           => 'decimal:2',
    ];

    public function parkingLot()
    {
        return $this->belongsTo(ParkingLot::class);
    }

    public function parkingSlot()
    {
        return $this->belongsTo(ParkingSlot::class);
    }

    /**
     * ยอดค่าจอดใบล่าสุด — อธิบายยอดทั้งหมดของการจอดครั้งนี้ได้ในตัวเอง (prior_paid = ที่ชำระในใบก่อน ๆ)
     * การจอดครั้งเดียวมีได้หลายใบเมื่อชำระแล้วไม่สแกนออกภายในเวลา (§12.6)
     */
    public function payment()
    {
        return $this->hasOne(Payment::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('type', Payment::TYPE_CHECKOUT)->where('payment_status', '!=', Payment::STATUS_VOID)
        );
    }

    /** ยอดค่าจอดทุกใบของการจอดครั้งนี้ */
    public function checkoutPayments()
    {
        return $this->hasMany(Payment::class)->where('type', Payment::TYPE_CHECKOUT);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
