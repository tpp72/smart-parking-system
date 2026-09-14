<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** คำร้องลาออกของ Owner (project-plan.md §16) */
class OwnerResignation extends Model
{
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $guarded = [];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'result'      => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
