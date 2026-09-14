<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Notification ภายในเว็บ — สถานะ ยังไม่อ่าน / อ่านแล้ว (project-plan.md §15) */
class Notification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
