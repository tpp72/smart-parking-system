<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $guarded = [];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function adminActions()
    {
        return $this->hasMany(AdminAction::class, 'admin_id');
    }

    public function reservationChanges()
    {
        return $this->hasMany(ReservationLog::class, 'changed_by');
    }

    public function suspiciousVehiclesAdded()
    {
        return $this->hasMany(SuspiciousVehicle::class, 'added_by');
    }

    public function ownedParkingLots()
    {
        return $this->hasMany(ParkingLot::class, 'owner_id');
    }

    public function ownerApplication()
    {
        return $this->hasOne(OwnerApplication::class);
    }

    public function isApprovedOwner(): bool
    {
        return $this->role === 'owner' && $this->owner_status === 'approved';
    }
}
