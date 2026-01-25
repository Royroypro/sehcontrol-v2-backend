<?php

namespace App\Services\Sehcontrol;

use App\Models\Device;
use Carbon\Carbon;

class PolicyEngine
{
    public function __construct(
        private SubscriptionService $subs
    ) {}

    public function evaluate(string $deviceUid): array
{
    $device = Device::where('device_uid', $deviceUid)->first();

    // ✅ IMPORTANTE: NO devolver 404 al agente.
    // Si el device no existe (borrado/no registrado), devolvemos 200 con policy DENY controlada
    // para que el agente pueda reaccionar (mostrar PAIR / re-vincular) sin tratarlo como “error de servidor”.
    if (!$device) {
        return $this->deny(
            'device_not_found',
            'register',
            'Equipo no registrado',
            "Este equipo no está registrado o fue eliminado del panel.\n\nVincúlelo nuevamente usando un código PAIR.",
            'warning',
            200
        );
    }

    // estado del device
    if ($device->status !== 'active') {
        return $this->deny(
            'blocked',
            'terminate',
            'Acceso denegado',
            'Equipo bloqueado. Contacte a soporte.',
            'error',
            403,
            $device
        );
    }

    if (!$device->customer_id) {
        return $this->deny(
            'unregistered',
            'register',
            'Sin cuenta',
            "Este equipo aún no está vinculado a un cliente.\n\nGenere un código PAIR en el panel y vincule este equipo.",
            'warning',
            200,
            $device
        );
    }

    $sub = $this->subs->getActiveForCustomer((int) $device->customer_id);

    if (!$sub || $sub->status !== 'active') {
        return $this->deny(
            'license_required',
            'terminate',
            'Licencia requerida',
            'Su suscripción no está activa.',
            'error',
            403,
            $device
        );
    }

    if ($sub->ends_on) {
        $ends = Carbon::parse($sub->ends_on)->endOfDay();
        if ($ends->lt(Carbon::now())) {
            return $this->deny(
                'expired',
                'terminate',
                'Suscripción vencida',
                'Renueve para continuar.',
                'error',
                403,
                $device
            );
        }
    }

    // cupo
    $max = (int) ($sub->max_devices ?? 0);
    if ($max > 0) {
        $activeCount = Device::where('customer_id', $device->customer_id)
            ->where('status', 'active')
            ->count();

        if ($activeCount > $max) {
            return $this->deny(
                'device_limit_reached',
                'terminate',
                'Límite de equipos',
                "Plan permite {$max}. Actualmente {$activeCount}.",
                'warning',
                403,
                $device
            );
        }
    }

    return [
        'http' => 200,
        'device' => $device,
        'sub' => $sub,
        'payload' => [
            'allow_connect' => true,
            'action' => 'continue',
            'reason' => 'active',
            'ui' => [
                'title' => $device->ui_title ?? 'SehControl',
                'message' => $device->ui_message ?? 'Servicio activo.',
                'level' => 'info',
            ],
        ],
    ];
}


    private function deny(string $reason, string $action, string $title, string $message, string $level, int $http, ?Device $device = null): array
    {
        return [
            'http' => $http,
            'device' => $device,
            'sub' => null,
            'payload' => [
                'allow_connect' => false,
                'action' => $action,
                'reason' => $reason,
                'ui' => [
                    'title' => $title,
                    'message' => $message,
                    'level' => $level,
                ],
            ],
        ];
    }
}
