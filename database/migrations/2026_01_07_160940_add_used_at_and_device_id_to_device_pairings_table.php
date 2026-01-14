<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('device_pairings', function (Blueprint $table) {
            if (!Schema::hasColumn('device_pairings', 'device_id')) {
                $table->foreignId('device_id')->nullable()->after('customer_id')->constrained('devices')->nullOnDelete();
            }
            if (!Schema::hasColumn('device_pairings', 'used_at')) {
                $table->timestamp('used_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('device_pairings', function (Blueprint $table) {
            if (Schema::hasColumn('device_pairings', 'used_at')) {
                $table->dropColumn('used_at');
            }
            if (Schema::hasColumn('device_pairings', 'device_id')) {
                $table->dropConstrainedForeignId('device_id');
            }
        });
    }
};
