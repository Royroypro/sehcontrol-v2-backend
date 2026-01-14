<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('plan_code', 32)->nullable()->after('status');
            $table->unsignedTinyInteger('grace_days')->default(7)->after('ends_on');
            $table->date('manual_until')->nullable()->after('grace_days');
            $table->timestamp('suspended_at')->nullable()->after('manual_until');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['plan_code', 'grace_days', 'manual_until', 'suspended_at']);
        });
    }
};
