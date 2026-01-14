<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('device_uid', 64)->unique();
            $table->string('rustdesk_id', 64)->nullable()->index();

            $table->string('device_name', 190)->nullable(); // alias
            $table->string('platform', 50)->nullable();
            $table->string('version', 50)->nullable();

            $table->string('status', 32)->default('active'); // active|blocked

            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 64)->nullable();

            $table->string('ui_title', 190)->nullable();
            $table->text('ui_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
