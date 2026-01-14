<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

use App\Models\Device;
use App\Models\Subscription;
use App\Services\SubscriptionEvaluator;

class ClientPolicyController extends Controller
{
    private const POLICY_VERSION = 1;
    private const POLICY_TTL_SECONDS = 3600; // útil para cache/control del agente

    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['reason' => 'unauthorized'], 401);
        }

        if (empty($user->customer_id)) {
            return response()->json([
                'message' => 'Sin cliente asociado',
                'reason'  => 'no_customer',
            ], 403);
        }

        $deviceUid = $this->resolveDeviceUid($request);
        if (!$deviceUid) {
            return response()->json([
                'message' => 'device_uid es requerido',
                'reason'  => 'missing_device_uid',
            ], 422);
        }

        $device = Device::where('device_uid', $deviceUid)->first();
        if (!$device) {
            return response()->json(['reason' => 'device_not_found'], 404);
        }

        if ((int) $device->customer_id !== (int) $user->customer_id) {
            return response()->json(['reason' => 'device_mismatch'], 403);
        }

        $now = Carbon::now();
        $serverTime = $now->toIso8601String();

        // Umbral para avisos en ACTIVE (si lo quieres “no molestar”, lo puede usar el agente)
        $notifyBeforeDays = (int) (config('sehcontrol.notify_before_days', 10));

        // ============================================================
        // 1) BLOQUEO MANUAL DE DISPOSITIVO
        // ============================================================
        if ($device->status !== 'active') {
            $actions = [
                'allow_run'          => false,
                'force_close'        => true,
                'lock_startup'       => true,
                'show_message'       => true,
                'message_title'      => $device->ui_title ?? 'Acceso denegado',
                'message_body'       => $device->ui_message ?? 'Este equipo ha sido desactivado por el administrador.',
                'message_level'      => 'danger',
                'reason'             => 'device_disabled',
                'days_left'          => null,
                'notify_before_days' => $notifyBeforeDays,
            ];

            return $this->policyResponse(
                actions: $actions,
                reason: 'device_disabled',
                serverTime: $serverTime,
                sub: null,
                device: $device,
                cfg: [],
                eval: []
            );
        }

        // ============================================================
        // 2) EVALUAR SUSCRIPCIÓN
        // ============================================================
        $sub = Subscription::where('customer_id', $device->customer_id)
            ->orderByDesc('ends_on')
            ->first();

        if (!$sub) {
            $actions = [
                'allow_run'          => false,
                'force_close'        => true,
                'lock_startup'       => true,
                'show_message'       => true,
                'message_title'      => 'Sin suscripción',
                'message_body'       => 'No se encontró una licencia activa para su cuenta.',
                'message_level'      => 'danger',
                'reason'             => 'no_subscription',
                'days_left'          => null,
                'notify_before_days' => $notifyBeforeDays,
            ];

            return $this->policyResponse(
                actions: $actions,
                reason: 'no_subscription',
                serverTime: $serverTime,
                sub: null,
                device: $device,
                cfg: [],
                eval: []
            );
        }

        // Evaluación de estado de suscripción
        $eval  = SubscriptionEvaluator::evaluate($sub, $now);
        $state = (string) ($eval['state'] ?? 'unknown');     // active, warning, grace, expired, suspended, etc.
        $allow = (bool)   ($eval['allow'] ?? false);
        $days  = $eval['days_left'] ?? null;
        $daysInt = is_numeric($days) ? (int) $days : null;

        // show_message:
        // - si NO es active => true
        // - si es active => solo si faltan <= notifyBeforeDays
        $activeShouldPopup = ($state === 'active' && $daysInt !== null && $daysInt <= $notifyBeforeDays);
        $shouldShow = ($state !== 'active') || $activeShouldPopup;

        // ============================================================
        // 3) MENSAJE UI POR ESTADO
        // ============================================================
        [$uiTitle, $uiBody, $uiLevel] = $this->buildUiMessage($state, $daysInt);

        // ============================================================
        // 4) CONFIG (TOML)
        // ============================================================
        $cfg = $this->buildConfig();

        // ============================================================
        // 5) ACTIONS (lo que obedece el agente)
        // ============================================================
        $actions = [
            'allow_run'          => $allow,
            'force_close'        => !$allow,
            'lock_startup'       => in_array($state, ['expired', 'suspended', 'banned'], true),
            'show_message'       => $shouldShow,

            'message_title'      => $uiTitle,
            'message_body'       => $uiBody,
            'message_level'      => $uiLevel,
            'reason'             => $state,

            'days_left'          => $daysInt,
            'notify_before_days' => $notifyBeforeDays,
        ];

        return $this->policyResponse(
            actions: $actions,
            reason: $state,
            serverTime: $serverTime,
            sub: $sub,
            device: $device,
            cfg: $cfg,
            eval: $eval
        );
    }

    // ============================================================
    // Helpers
    // ============================================================
    private function resolveDeviceUid(Request $request): ?string
    {
        $uid = $request->query('device_uid')
            ?? $request->header('X-Device-Uid')
            ?? $request->input('device_uid');

        $uid = is_string($uid) ? trim($uid) : null;
        return $uid ?: null;
    }

    private function buildUiMessage(string $state, ?int $daysInt): array
    {
        $uiTitle = 'Estado de Licencia';
        $uiBody  = 'Su servicio está activo.';
        $uiLevel = 'info';

        switch ($state) {
            case 'active':
                $uiTitle = 'Protección Activa';
                $uiBody  = ($daysInt !== null)
                    ? "Todo en orden. Su licencia vence en {$daysInt} días."
                    : "Todo en orden. Su licencia está activa.";
                $uiLevel = 'info';
                break;

            case 'warning':
                $uiTitle = 'SEHCONTROL.EXE - Aviso de Renovación';
                $uiBody  = ($daysInt !== null)
                    ? "¡Atención! Su suscripción vence pronto (quedan {$daysInt} días). Renueve para evitar el cierre del servicio."
                    : "¡Atención! Su suscripción vence pronto. Renueve para evitar el cierre del servicio.";
                $uiLevel = 'warning';
                break;

            case 'grace':
                $uiTitle = 'Periodo de Gracia';
                $uiBody  = ($daysInt !== null)
                    ? "Su suscripción ha vencido, pero dispone de un período de cortesía de {$daysInt} días. Por favor, regularice su pago."
                    : "Su suscripción ha vencido, pero dispone de un período de cortesía. Por favor, regularice su pago.";
                $uiLevel = 'warning';
                break;

            case 'expired':
                $uiTitle = 'Membresía Expirada';
                $uiBody  = "Su acceso a SEHCONTROL ha caducado. El servicio permanecerá bloqueado hasta que se realice la renovación.";
                $uiLevel = 'danger';
                break;

            case 'suspended':
                $uiTitle = 'Servicio Suspendido';
                $uiBody  = "Su cuenta presenta una suspensión administrativa. Contacte a soporte técnico.";
                $uiLevel = 'danger';
                break;

            case 'maintenance':
                $uiTitle = 'Mantenimiento';
                $uiBody  = "Estamos realizando mejoras en el servidor. El servicio se reanudará en breve.";
                $uiLevel = 'warning';
                break;

            default:
                $uiTitle = 'Estado de Licencia';
                $uiBody  = 'No se pudo determinar el estado de su servicio.';
                $uiLevel = 'warning';
                break;
        }

        return [$uiTitle, $uiBody, $uiLevel];
    }

    private function buildConfig(): array
    {
        $domain = (string) config('sehcontrol.domain');
        $rPort  = (string) config('sehcontrol.rendezvous_port');
        $relay  = (string) config('sehcontrol.relay');
        $key    = (string) config('sehcontrol.key');
        $pin    = (string) config('sehcontrol.unlock_pin');

        // Evitar "domain:" si falta port, etc.
        $rendezvous = $domain && $rPort ? "{$domain}:{$rPort}" : $domain;

        return [
            'custom-rendezvous-server' => $rendezvous,
            'relay-server'             => $relay,
            'key'                      => $key,
            'unlock_pin'               => $pin,
        ];
    }

    private function stableJson(mixed $data): string
    {
        $data = $this->ksortRecursive($data);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function ksortRecursive(mixed $data): mixed
    {
        if (is_array($data)) {
            // Ordena llaves si es array asociativo
            if ($this->isAssoc($data)) {
                ksort($data);
            }
            foreach ($data as $k => $v) {
                $data[$k] = $this->ksortRecursive($v);
            }
        }
        return $data;
    }

    private function isAssoc(array $arr): bool
    {
        $keys = array_keys($arr);
        return array_keys($keys) !== $keys;
    }

    private function policyResponse(
        array $actions,
        string $reason,
        string $serverTime,
        ?Subscription $sub,
        Device $device,
        array $cfg = [],
        array $eval = []
    ) {
        // fingerprint estable (ordenado) para evitar cambios fantasma
        $fingerprint = hash('sha256', $this->stableJson([
            'config'  => $cfg,
            'actions' => $actions,
        ]));

        $allow = (bool) ($actions['allow_run'] ?? false);

        return response()->json([
            'policy_version'      => self::POLICY_VERSION,
            'policy_ttl_seconds'  => self::POLICY_TTL_SECONDS,
            'server_time'         => $serverTime,
            'policy_fingerprint'  => $fingerprint,
            'config'              => $cfg,
            'actions'             => $actions,
            'subscription_state'  => $reason,
            'reason'              => $reason,
            'subscription'        => $sub ? [
                'status'    => $sub->status,
                'ends_on'   => optional($sub->ends_on)->toDateString() ?? $sub->ends_on,
                'days_left' => $eval['days_left'] ?? null,
            ] : null,
            'device'              => [
                'status' => $device->status,
            ],
        ], $allow ? 200 : 403);
    }
}
