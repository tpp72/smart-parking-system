<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ParkingLot extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function slots()
    {
        return $this->hasMany(ParkingSlot::class);
    }

    public function parkingLogs()
    {
        return $this->hasMany(ParkingLog::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** ผู้รับแจ้งเตือนของลาน: Owner ของลาน (ถ้ามี) + Admin ทุกคน */
    public function staffRecipientIds(): Collection
    {
        $ids = User::where('role', 'admin')->pluck('id');

        if ($this->owner_id) {
            $ids->push($this->owner_id);
        }

        return $ids->unique()->values();
    }

    /** แจ้งเตือน Owner ของลาน + Admin */
    public function notifyStaff(string $title, string $message): void
    {
        foreach ($this->staffRecipientIds() as $recipientId) {
            notify_user($recipientId, $title, $message);
        }
    }

    public function scopeReservable($query)
    {
        return $query->where('reservations_enabled', true);
    }

    /** ลานที่ยังมี Slot ว่าง — ลานเต็มไม่แสดงให้จอง */
    public function scopeWithAvailableSlot($query)
    {
        return $query->whereHas('slots', fn ($q) => $q->where('status', 'available'));
    }

    /** ลานจอดที่ยังไม่มีเจ้าของ — อยู่ในความดูแลของ Admin */
    public function scopeUnowned($query)
    {
        return $query->whereNull('owner_id');
    }

    /** ลานจอดของ owner คนที่ระบุ */
    public function scopeOwnedBy($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }
}
