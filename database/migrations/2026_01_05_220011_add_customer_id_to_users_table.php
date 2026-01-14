<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
   public function up(): void
{
    if (!Schema::hasTable('users')) return;

    Schema::table('users', function (Blueprint $table) {
        if (!Schema::hasColumn('users', 'customer_id')) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('id');
        }
    });
}

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
