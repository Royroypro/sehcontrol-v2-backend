<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('uuid_machine', 64)->nullable()->after('device_uid');
            $table->string('unlock_pin', 255)->nullable()->after('uuid_machine');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['uuid_machine', 'unlock_pin']);
        });
    }
};
