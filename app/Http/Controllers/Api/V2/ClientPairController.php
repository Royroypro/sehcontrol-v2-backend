<?php
// ClientPairController.php
/*
 * 1) VALIDACIÓN INPUT
 * 2) TRANSACCIÓN: evita doble uso del pair_code y carreras
 * 2.2) Obtener suscripción más relevante del cliente
 * 2.3) Decidir vigencia: manual_until, ends_on, grace_days
 * 2.4) Control de cupos (max_devices)
 * 2.5) Crear/actualizar device (lock para evitar carreras)
 * 2.6) Marcar pair como usado y vincular device
 * 2.7) Emitir token Sanctum (User dueño del customer)
 * 2.8) Respuesta final (IMPORTANTE: devolver JSON siempre)
 */

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Device;
use App\Models\DevicePairing;
use App\Models\Subscription;
use App\Services\SubscriptionEvaluator;

class ClientPairController extends Controller
{
    public function __invoke(Request $request)
    {
        /**
         * ============================================================
         * 1) VALIDACIÓN INPUT
         * ============================================================
         * Este endpoint se usa antes del token (o si el token aún no existe).
         * Reglas: pair_code + device_uid obligatorios.
         */
        $data = $request->validate([
            'pair_code'    => ['required', 'string', 'max:64'],
            'device_uid'   => ['required', 'string', 'min:10', 'max:128'],
            'rustdesk_id'  => ['nullable', 'string', 'max:64'],
            'alias'        => ['nullable', 'string', 'max:120'], // se mapea a device_name
            'platform'     => ['nullable', 'string', 'max:32'],
            'version'      => ['nullable', 'string', 'max:32'],
            'uuid_machine' => ['nullable', 'string', 'max:64'],
        ]);

        $now = Carbon::now();
        $today = Carbon::today();

        /**
         * ============================================================
         * 2) TRANSACCIÓN: evita doble uso del pair_code y carreras
         * ============================================================
         */
        return DB::transaction(function () use ($data, $now, $today) {

            /**
             * ------------------------------------------------------------
             * 2.1) Buscar pair_code (lockForUpdate => no se usa 2 veces)
             * ------------------------------------------------------------
             */
            $pair = DevicePairing::where('pair_code', $data['pair_code'])
                ->lockForUpdate()
                ->first();

            if (!$pair) {
                return response()->json([
                    'message' => 'Código inválido',
                    'reason'  => 'invalid_pair_code',
                ], 422);
            }

            // Ya usado
            if (!empty($pair->used_at)) {
                return response()->json([
                    'message' => 'Código ya usado',
                    'reason'  => 'pair_code_used',
                ], 409);
            }

            // Expirado
            if (!empty($pair->expires_at) && Carbon::parse($pair->expires_at)->lt($now)) {
                return response()->json([
                    'message' => 'Código expirado',
                    'reason'  => 'pair_code_expired',
                ], 410);
            }

            // Debe tener customer asociado
            if (empty($pair->customer_id)) {
                return response()->json([
                    'message' => 'Pair code sin cliente asociado',
                    'reason'  => 'pair_code_missing_customer',
                ], 422);
            }

            $customerId = (int) $pair->customer_id;

            /**
             * ------------------------------------------------------------
             * 2.2) Obtener suscripción más relevante del cliente
             * ------------------------------------------------------------
             */
            $sub = Subscription::where('customer_id', $customerId)
                ->orderByDesc('ends_on')
                ->lockForUpdate()
                ->first();

            if (!$sub) {
                return response()->json([
                    'message' => 'Cliente sin suscripción',
                    'reason'  => 'no_subscription',
                ], 403);
            }

           /**
             * ------------------------------------------------------------
             * 2.3) Evaluación oficial de suscripción (MODELO CENTRALIZADO)
             * ------------------------------------------------------------
             * El backend calcula estado y vigencia. Pair y Policy deben usar
             * la MISMA lógica para no divergir.
             */
            $eval = SubscriptionEvaluator::evaluate($sub, $now);
                    
            if (!$eval['allow']) {
                return response()->json([
                    'message' => 'Suscripción no permite emparejar',
                    'reason'  => 'subscription_not_allowed',
                    'subscription_state'   => $eval['state'],
                    'expires_at_effective' => $eval['expires_at_effective'],
                    'days_left'            => $eval['days_left'],
                    'status'               => $sub->status,
                    'ends_on'              => $sub->ends_on,
                ], 403);
            }
            
            // Si quieres conservar "inGrace" para usarlo en el response final:
            $inGrace = (bool) $eval['in_grace'];


            /**
             * ------------------------------------------------------------
             * 2.4) Control de cupos (max_devices)
             * ------------------------------------------------------------
             */
            $max = (int) ($sub->max_devices ?? 0);
            if ($max <= 0) {
                return response()->json([
                    'message' => 'Plan sin cupos configurados',
                    'reason'  => 'plan_zero_devices',
                ], 409);
            }

            $activeCount = Device::where('customer_id', $customerId)
                ->where('status', 'active')
                ->count();

            if ($activeCount >= $max) {
                return response()->json([
                    'message'     => 'Límite de máquinas alcanzado',
                    'reason'      => 'device_limit_reached',
                    'max_devices' => $max,
                    'active'      => $activeCount,
                ], 409);
            }

            /**
             * ------------------------------------------------------------
             * 2.5) Crear/actualizar device (lock para evitar carreras)
             * ------------------------------------------------------------
             */
            $device = Device::where('device_uid', $data['device_uid'])
                ->lockForUpdate()
                ->first();

            if (!$device) {
                $device = new Device();
                $device->device_uid = $data['device_uid'];
            }

            // Evitar "robo" de device_uid: si ya pertenece a otro customer => bloquear
            if (!empty($device->customer_id) && (int) $device->customer_id !== $customerId) {
                return response()->json([
                    'message' => 'Esta máquina ya pertenece a otro cliente',
                    'reason'  => 'device_belongs_to_other_customer',
                ], 409);
            }

            $device->customer_id = $customerId;
            $device->status      = 'active';

            if (empty($device->paired_at)) {
                $device->paired_at = $now;
            }

            // metadata opcional
            if (!empty($data['rustdesk_id'])) {
                $device->rustdesk_id = $data['rustdesk_id'];
            }
            if (!empty($data['uuid_machine'])) {
                $device->uuid_machine = $data['uuid_machine'];
            }
            if (!empty($data['platform'])) {
                $device->platform = $data['platform'];
            }
            if (!empty($data['version'])) {
                $device->version = $data['version'];
            }

            // alias -> device_name
            if (!empty($data['alias'])) {
                $device->device_name = $data['alias'];
            }

            $device->save();

            /**
             * ------------------------------------------------------------
             * 2.6) Marcar pair como usado y vincular device
             * ------------------------------------------------------------
             */
            $pair->device_id = $device->id;
            $pair->used_at   = $now;
            $pair->status    = 'used';
            $pair->save();

            /**
             * ------------------------------------------------------------
             * 2.7) Emitir token Sanctum (User dueño del customer)
             * ------------------------------------------------------------
             * Tu /client/policy amarra token->name = "seh-agent:<device_uid>"
             */
            $user = User::where('customer_id', $customerId)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'Cliente sin usuario asociado',
                    'reason'  => 'customer_missing_user',
                ], 409);
            }

            $tokenName = 'seh-agent:' . $device->device_uid;

            // Revocar token previo de ese device (1 token activo por máquina)
            $user->tokens()->where('name', $tokenName)->delete();

            $token = $user->createToken($tokenName)->plainTextToken;

            /**
             * ------------------------------------------------------------
             * 2.8) Respuesta final (IMPORTANTE: devolver JSON siempre)
             * ------------------------------------------------------------
             */
            return response()->json([
                'server_time' => $now->toIso8601String(),

                'message'     => 'PAIRED',
                'token'       => $token,
                'token_type'  => 'Bearer',

                'customer_id' => $customerId,

                'device'      => [
                    'id'          => $device->id,
                    'device_uid'  => $device->device_uid,
                    'status'      => $device->status,
                    'paired_at'   => optional($device->paired_at)->toIso8601String(),
                    'rustdesk_id' => $device->rustdesk_id ?? null,
                    'device_name' => $device->device_name ?? null,
                ],

                // opcional: útil para debug del agente
                'subscription' => [
                'status'       => $sub->status,
                'plan_code'    => $sub->plan_code,
                'max_devices'  => (int) $sub->max_devices,
                'starts_on'    => $sub->starts_on,
                'ends_on'      => $sub->ends_on,
                'grace_days'   => (int) ($sub->grace_days ?? 0),
                'manual_until' => $sub->manual_until,
                            
                // ✅ NUEVO: estado oficial calculado por backend
                'subscription_state'   => $eval['state'],
                'expires_at_effective' => $eval['expires_at_effective'],
                'days_left'            => $eval['days_left'],
                'in_grace'             => (bool) $eval['in_grace'],
            ],
            
            ], 200);
        });
    }
}
