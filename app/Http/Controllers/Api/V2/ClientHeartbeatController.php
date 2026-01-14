<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

use App\Models\Device;
use App\Models\DeviceReport;

class ClientHeartbeatController extends Controller
{
    public function __invoke(Request $request)
    {
        // ============================================================
        // 1) AUTENTICACIÓN
        // ============================================================
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
                'reason'  => 'unauthorized',
            ], 401);
        }

        // ============================================================
        // 2) VALIDACIÓN INPUT
        // ============================================================
        $data = $request->validate([
            'device_uid'   => ['required', 'string', 'max:128'],

            // metadata opcional
            'rustdesk_id'  => ['nullable', 'string', 'max:64'],
            'platform'     => ['nullable', 'string', 'max:50'],
            'version'      => ['nullable', 'string', 'max:50'],
            'uuid_machine' => ['nullable', 'string', 'max:64'],

            // health (estándar A)
            'agent_status'  => ['nullable', 'string', 'in:running,idle,error'],
            'sehcontrol_running' => ['nullable', 'boolean'],
            'policy_fingerprint_applied' => ['nullable', 'string', 'size:64'],

            // errores
            'last_error_code' => ['nullable', 'string', 'max:64'],
            'last_error_at'   => ['nullable', 'date'],

            // contrato heartbeat
            'heartbeat_interval_s' => ['nullable', 'integer', 'min:10', 'max:86400'],

            // meta libre
            'meta' => ['nullable', 'array'],
        ]);

        // ============================================================
        // 3) BUSCAR DEVICE
        // ============================================================
        $device = Device::where('device_uid', $data['device_uid'])->first();
        if (!$device) {
            return response()->json([
                'message' => 'Device not registered',
                'reason'  => 'not_registered',
            ], 404);
        }

        // ============================================================
        // 4) VALIDACIONES DE PERTENENCIA / PAREO
        // ============================================================
        if (!$device->customer_id) {
            return response()->json([
                'message' => 'Device not paired',
                'reason'  => 'not_paired',
            ], 403);
        }

        if (!empty($user->customer_id) && (int) $device->customer_id !== (int) $user->customer_id) {
            return response()->json([
                'message' => 'Device mismatch',
                'reason'  => 'device_mismatch',
            ], 403);
        }

        $token = $user->currentAccessToken();
        if ($token) {
            $expectedName = 'seh-agent:' . $device->device_uid;
            if ($token->name !== $expectedName) {
                return response()->json([
                    'message'  => 'Token device mismatch',
                    'reason'   => 'token_device_mismatch',
                    'expected' => $expectedName,
                    'got'      => $token->name,
                ], 403);
            }
        }

        // ============================================================
        // 5) ACTUALIZAR METADATA (solo si viene valor válido)
        // ============================================================
        foreach (['rustdesk_id', 'platform', 'version'] as $f) {
            if (array_key_exists($f, $data) && $data[$f] !== null && $data[$f] !== '') {
                $device->{$f} = $data[$f];
            }
        }

        // uuid_machine: solo se setea si viene y aún no existe
        if (!empty($data['uuid_machine']) && empty($device->uuid_machine)) {
            $device->uuid_machine = $data['uuid_machine'];
        }

        // ============================================================
        // 6) LAST SEEN + HEALTH FIELDS
        // ============================================================
        $now = Carbon::now();

        // base
        $device->last_seen_at = $now;
        $device->last_heartbeat_at = $now;
        $device->last_ip = $request->ip();

        // health estándar A
        if (!empty($data['agent_status'])) {
            $device->agent_status = $data['agent_status'];
        }
        if (array_key_exists('sehcontrol_running', $data)) {
            $device->sehcontrol_running = (bool) $data['sehcontrol_running'];
        }
        if (!empty($data['policy_fingerprint_applied'])) {
            $device->policy_fingerprint_applied = $data['policy_fingerprint_applied'];
        }
        if (!empty($data['last_error_code'])) {
            $device->last_error_code = $data['last_error_code'];
        }
        if (!empty($data['last_error_at'])) {
            $device->last_error_at = $data['last_error_at'];
        }
        if (!empty($data['heartbeat_interval_s'])) {
            $device->heartbeat_interval_s = (int) $data['heartbeat_interval_s'];
        }

        // hostname + payload
        $hostname = null;
        if (!empty($data['meta']) && is_array($data['meta'])) {
            $hostname = $data['meta']['hostname'] ?? null;
        }
        if (is_string($hostname) && trim($hostname) !== '') {
            $device->last_hostname = trim($hostname);
        }

        $device->last_heartbeat_payload = $request->all();

        // ------------------------------------------------------------
        // Compatibilidad (B) mientras limpias duplicados:
        // Si existen columnas duplicadas, las llenamos también.
        // (tu tabla actual las tiene)
        // ------------------------------------------------------------
        if (property_exists($device, 'last_agent_version')) {
            // en tu tabla existe last_agent_version; úsalo como espejo de version
            $device->last_agent_version = $device->version ?? null;
        }
        if (property_exists($device, 'last_agent_status') && !empty($data['agent_status'])) {
            $device->last_agent_status = $data['agent_status'];
        }
        if (property_exists($device, 'last_sehcontrol_running') && array_key_exists('sehcontrol_running', $data)) {
            $device->last_sehcontrol_running = (bool) $data['sehcontrol_running'];
        }
        if (property_exists($device, 'last_policy_fp_applied') && !empty($data['policy_fingerprint_applied'])) {
            $device->last_policy_fp_applied = $data['policy_fingerprint_applied'];
        }
        if (property_exists($device, 'last_heartbeat_interval_s') && !empty($data['heartbeat_interval_s'])) {
            $device->last_heartbeat_interval_s = (int) $data['heartbeat_interval_s'];
        }

        $device->save();

        // ============================================================
        // 7) DEVICE REPORT (opcional)
        // ============================================================
        DeviceReport::create([
            'device_id' => $device->id,
            'reported_at' => $now,
            'agent_status' => $data['agent_status'] ?? null,
            'sehcontrol_running' => array_key_exists('sehcontrol_running', $data) ? (bool)$data['sehcontrol_running'] : null,
            'policy_fingerprint_applied' => $data['policy_fingerprint_applied'] ?? null,
            'last_error_code' => $data['last_error_code'] ?? null,
            'last_error_at' => $data['last_error_at'] ?? null,
            'meta' => $data['meta'] ?? null,
        ]);

        // ============================================================
        // 8) RESPUESTA
        // ============================================================
        return response()->json([
            'server_time' => $now->toIso8601String(),
            'message'     => 'HEARTBEAT ok',
            'device_id'   => $device->id,
            'device_uid'  => $device->device_uid,
            'ack'         => true,

            'last_seen_at'      => optional($device->last_seen_at)->toIso8601String(),
            'last_heartbeat_at' => optional($device->last_heartbeat_at)->toIso8601String(),
            'last_ip'           => $device->last_ip ?? null,
        ], 200);
    }
}
