<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevicePairing extends Model
{
    protected $fillable = ['customer_id','pair_code','status','used_at','device_id'];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
}
