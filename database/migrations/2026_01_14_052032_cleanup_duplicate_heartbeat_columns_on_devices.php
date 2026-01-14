<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Copiar datos de columnas "last_*" -> columnas estándar, solo si estándar está NULL
        // (Esto evita perder data si alguna parte antigua estaba escribiendo en last_*)

        // last_agent_status -> agent_status
        if (Schema::hasColumn('devices', 'last_agent_status') && Schema::hasColumn('devices', 'agent_status')) {
            DB::statement("
                UPDATE devices
                SET agent_status = COALESCE(agent_status, last_agent_status)
            ");
        }

        // last_sehcontrol_running -> sehcontrol_running
        if (Schema::hasColumn('devices', 'last_sehcontrol_running') && Schema::hasColumn('devices', 'sehcontrol_running')) {
            DB::statement("
                UPDATE devices
                SET sehcontrol_running = COALESCE(sehcontrol_running, last_sehcontrol_running)
            ");
        }

        // last_policy_fp_applied -> policy_fingerprint_applied
        if (Schema::hasColumn('devices', 'last_policy_fp_applied') && Schema::hasColumn('devices', 'policy_fingerprint_applied')) {
            DB::statement("
                UPDATE devices
                SET policy_fingerprint_applied = COALESCE(policy_fingerprint_applied, last_policy_fp_applied)
            ");
        }

        // last_heartbeat_interval_s -> heartbeat_interval_s
        if (Schema::hasColumn('devices', 'last_heartbeat_interval_s') && Schema::hasColumn('devices', 'heartbeat_interval_s')) {
            DB::statement("
                UPDATE devices
                SET heartbeat_interval_s = COALESCE(heartbeat_interval_s, last_heartbeat_interval_s)
            ");
        }

        // last_agent_version -> version (solo si quieres unificar versión del agente)
        if (Schema::hasColumn('devices', 'last_agent_version') && Schema::hasColumn('devices', 'version')) {
            DB::statement("
                UPDATE devices
                SET version = COALESCE(version, last_agent_version)
            ");
        }

        // 2) Eliminar duplicados (B). OJO: esto debe hacerse SOLO si ya confirmaste que no los usas.
        Schema::table('devices', function (Blueprint $table) {
            $drop = [];

            foreach ([
                'last_agent_version',
                'last_agent_status',
                'last_sehcontrol_running',
                'last_policy_fp_applied',
                'last_heartbeat_interval_s',
            ] as $col) {
                if (Schema::hasColumn('devices', $col)) {
                    $drop[] = $col;
                }
            }

            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        // No recreamos columnas duplicadas en rollback (porque ya no las queremos).
        // Si de verdad necesitas rollback, habría que recrearlas, pero no es recomendable.
    }
};
