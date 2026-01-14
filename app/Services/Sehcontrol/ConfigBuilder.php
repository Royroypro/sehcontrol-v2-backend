<?php

namespace App\Services\Sehcontrol;

use App\Models\Device;
use App\Models\Subscription;
use Carbon\Carbon;

class ConfigBuilder
{
    public function build(Device $device, ?Subscription $sub): array
    {
        $domain = config('sehcontrol.domain');
        $port   = (int) config('sehcontrol.rendezvous_port', 21116);
        $relay  = config('sehcontrol.relay', $domain);
        $key    = config('sehcontrol.key');

        $endsOn = $sub?->ends_on ? Carbon::parse($sub->ends_on)->toIso8601String() : null;
        $maxDevices = (int)($sub->max_devices ?? 0);

        return [
            'customer_id' => $device->customer_id,
            'device' => [
                'device_uid'  => $device->device_uid,
                'rustdesk_id' => $device->rustdesk_id,
                'alias'       => $device->device_name,
                'platform'    => $device->platform,
                'version'     => $device->version,
                'status'      => $device->status,
            ],
            'subscription' => [
                'status'      => $sub?->status,
                'ends_on'     => $endsOn,
                'max_devices' => $maxDevices,
            ],
            'servers' => [
                'api_base'   => config('sehcontrol.api_base'),
                'rendezvous' => "{$domain}:{$port}",
                'relay'      => $relay,
                'key'        => $key,
            ],
            'agent' => [
                'heartbeat_seconds' => (int) config('sehcontrol.heartbeat_seconds', 300),
                'poll_seconds'      => (float) config('sehcontrol.poll_seconds', 1.0),
            ],
            'ui' => [
                'title'   => $device->ui_title ?? 'SehControl',
                'message' => $device->ui_message ?? '',
                'level'   => 'info',
            ],
            'version' => 2,
        ];
    }
}
