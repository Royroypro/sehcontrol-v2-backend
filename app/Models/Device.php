<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Device extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'customer_id',
        'device_uid',
        'uuid_machine',
        'unlock_pin',
        'rustdesk_id',
        'device_name',
        'platform',
        'version',
        'status',
        'paired_at',
        'revoked_at',

        // network / seen
        'last_seen_at',
        'last_ip',

        // heartbeat / health (ESTÁNDAR)
        'last_heartbeat_at',
        'heartbeat_interval_s',
        'agent_status',
        'sehcontrol_running',
        'policy_fingerprint_applied',
        'last_error_code',
        'last_error_at',
        'last_hostname',
        'last_heartbeat_payload',

        // UI
        'ui_title',
        'ui_message',
    ];

    protected $casts = [
        'paired_at'         => 'datetime',
        'revoked_at'        => 'datetime',
        'last_seen_at'      => 'datetime',

        // heartbeat / health
        'last_heartbeat_at' => 'datetime',
        'last_error_at'     => 'datetime',
        'sehcontrol_running'=> 'boolean',
        'last_heartbeat_payload' => 'array',
    ];

    /**
     * ONLINE/OFFLINE/STALE calculado por last_heartbeat_at y heartbeat_interval_s.
     * - online:  <= 2x intervalo
     * - stale:   <= 3x intervalo
     * - offline: > 3x intervalo
     */
    public function getOnlineStatusAttribute(): string
    {
        $ts = $this->last_heartbeat_at ?? $this->last_seen_at;
        if (!$ts) return 'offline';

        $interval = (int) ($this->heartbeat_interval_s ?? config('sehcontrol.heartbeat_s', 300));
        if ($interval <= 0) $interval = 300;

        $seconds = now()->diffInSeconds($ts);

        if ($seconds <= 2 * $interval) return 'online';
        if ($seconds <= 3 * $interval) return 'stale';
        return 'offline';
    }

    /**
     * Segundos desde el último latido (útil para admin/panel)
     */
    public function getHeartbeatAgeSecondsAttribute(): ?int
    {
        $ts = $this->last_heartbeat_at ?? $this->last_seen_at;
        if (!$ts) return null;
        return now()->diffInSeconds($ts);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function reports()
    {
        return $this->hasMany(DeviceReport::class);
    }
}
