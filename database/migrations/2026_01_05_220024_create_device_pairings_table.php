<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->string('pair_code', 64)->unique();
            $table->string('status', 32)->default('pending'); // pending|used
            $table->timestamp('used_at')->nullable();

            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_pairings');
    }
};
