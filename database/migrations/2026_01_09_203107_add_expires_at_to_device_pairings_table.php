<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('device_pairings', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('pair_code');
            $table->index(['customer_id', 'status', 'expires_at'], 'pairings_customer_status_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::table('device_pairings', function (Blueprint $table) {
            $table->dropIndex('pairings_customer_status_expires_idx');
            $table->dropColumn('expires_at');
        });
    }
};

