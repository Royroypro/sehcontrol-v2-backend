<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'customer_id',
        'status',
        'starts_on',
        'ends_on',
        'max_devices',
        // si luego agregas estos campos:
        // 'plan_code', 'grace_days', 'manual_until'
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on'   => 'date',
        // 'manual_until' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
