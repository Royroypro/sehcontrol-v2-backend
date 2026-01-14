<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (!Schema::hasColumn('devices', 'last_heartbeat_at')) {
                $table->timestamp('last_heartbeat_at')->nullable()->after('last_seen_at');
            }

            if (!Schema::hasColumn('devices', 'heartbeat_interval_s')) {
                $table->unsignedInteger('heartbeat_interval_s')->nullable()->after('last_heartbeat_at');
            }

            if (!Schema::hasColumn('devices', 'agent_status')) {
                $table->string('agent_status', 30)->nullable()->after('heartbeat_interval_s');
            }

            if (!Schema::hasColumn('devices', 'sehcontrol_running')) {
                $table->boolean('sehcontrol_running')->nullable()->after('agent_status');
            }

            if (!Schema::hasColumn('devices', 'policy_fingerprint_applied')) {
                $table->string('policy_fingerprint_applied', 64)->nullable()->after('sehcontrol_running');
            }

            if (!Schema::hasColumn('devices', 'last_error_code')) {
                $table->string('last_error_code', 64)->nullable()->after('policy_fingerprint_applied');
            }

            if (!Schema::hasColumn('devices', 'last_error_at')) {
                $table->timestamp('last_error_at')->nullable()->after('last_error_code');
            }

            if (!Schema::hasColumn('devices', 'last_hostname')) {
                $table->string('last_hostname', 120)->nullable()->after('last_ip');
            }

            if (!Schema::hasColumn('devices', 'last_heartbeat_payload')) {
                $table->json('last_heartbeat_payload')->nullable()->after('last_hostname');
            }
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            foreach ([
                'last_heartbeat_at',
                'heartbeat_interval_s',
                'agent_status',
                'sehcontrol_running',
                'policy_fingerprint_applied',
                'last_error_code',
                'last_error_at',
                'last_hostname',
                'last_heartbeat_payload',
            ] as $col) {
                if (Schema::hasColumn('devices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
