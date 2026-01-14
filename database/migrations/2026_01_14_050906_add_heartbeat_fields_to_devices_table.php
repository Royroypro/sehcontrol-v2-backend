<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('updated_at');
            $table->string('last_agent_version', 50)->nullable()->after('last_heartbeat_at');
            $table->string('last_agent_status', 30)->nullable()->after('last_agent_version');
            $table->boolean('last_sehcontrol_running')->nullable()->after('last_agent_status');
            $table->string('last_policy_fp_applied', 64)->nullable()->after('last_sehcontrol_running');
            $table->string('last_hostname', 120)->nullable()->after('last_policy_fp_applied');
            $table->unsignedInteger('last_heartbeat_interval_s')->nullable()->after('last_hostname'); // opcional pero útil
            $table->json('last_heartbeat_payload')->nullable()->after('last_heartbeat_interval_s');    // opcional
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'last_heartbeat_at',
                'last_agent_version',
                'last_agent_status',
                'last_sehcontrol_running',
                'last_policy_fp_applied',
                'last_hostname',
                'last_heartbeat_interval_s',
                'last_heartbeat_payload',
            ]);
        });
    }
};
