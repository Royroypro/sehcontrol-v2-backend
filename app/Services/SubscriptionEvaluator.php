<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SubscriptionEvaluator
{
    public static function evaluate(Subscription $sub, ?Carbon $now = null): array
    {
        $now   = $now ?: Carbon::now();
        $today = $now->copy()->startOfDay();

        // 1) Carga de fechas y parámetros (compatibles con columnas opcionales)
        $hasManual = Schema::hasColumn('subscriptions', 'manual_until');
        $hasGrace  = Schema::hasColumn('subscriptions', 'grace_days');

        $manualUntil = $hasManual
            ? ($sub->manual_until ? Carbon::parse($sub->manual_until)->endOfDay() : null)
            : null;

        $graceDays = $hasGrace ? (int)($sub->grace_days ?? 0) : 0;

        $endsOn = $sub->ends_on
            ? Carbon::parse($sub->ends_on)->endOfDay()
            : null;

        // 2) Cálculos lógicos de fechas
        $manualOk = $manualUntil ? $manualUntil->gte($today) : false;
        $endsOk   = $endsOn ? $endsOn->gte($now) : false;

        $inGrace = false;
        if (!$manualOk && !$endsOk && $endsOn && $graceDays > 0) {
            $inGrace = $endsOn->copy()->addDays($graceDays)->gte($now);
        }

        // 3) Expiración efectiva + days_left (calcular ANTES de usarlo en el estado)
        $expiresEffective = null;

        // Si existe ends_on, manda ends_on (+grace). Si no, usa manual_until.
        if ($endsOn) {
            $expiresEffective = $endsOn->copy();
            if ($graceDays > 0) {
                $expiresEffective->addDays($graceDays);
            }
        } elseif ($manualUntil) {
            $expiresEffective = $manualUntil->copy();
        }

        $daysLeft = null;
        if ($expiresEffective) {
            // Días restantes desde hoy (según $now) hasta la expiración efectiva.
            $daysLeft = $now->copy()->startOfDay()->diffInDays($expiresEffective->copy()->startOfDay(), false);
        }

        // 4) ESTADO ADMINISTRATIVO (status DB)
        $rawStatus = (string)($sub->status ?? 'active');

        // PRIORIDAD: si status es de bloqueo, ignorar fechas
        if (in_array($rawStatus, ['expired', 'banned', 'suspended'], true)) {
            $state = $rawStatus;
            $allow = false;
        } else {
            if ($manualOk || $endsOk) {
                // Advertencia proactiva (cuando hay expiración calculable)
                if ($daysLeft !== null && $daysLeft <= 5 && $daysLeft > 0) {
                    $state = 'warning';
                } else {
                    $state = 'active';
                }
                $allow = true;
            } elseif ($inGrace) {
                $state = 'grace';
                $allow = true;
            } else {
                $state = 'expired';
                $allow = false;
            }
        }

        return [
            'state' => $state,
            'allow' => $allow,
            'manual_ok' => $manualOk,
            'ends_ok' => $endsOk,
            'in_grace' => $inGrace,
            'expires_at_effective' => $expiresEffective?->toIso8601String(),
            'days_left' => $daysLeft,
            'grace_days' => $graceDays,
        ];
    }
}
