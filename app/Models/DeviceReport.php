<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceReport extends Model
{
    protected $fillable = [
        'device_id',
        'reported_at',
        'agent_status',
        'sehcontrol_running',
        'policy_fingerprint_applied',
        'last_error_code',
        'last_error_at',
        'meta',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'sehcontrol_running' => 'boolean',
        'last_error_at' => 'datetime',
        'meta' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
