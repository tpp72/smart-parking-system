<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    /** ผู้กระทำ (user / owner / admin) — null เมื่อเป็นระบบ */
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
