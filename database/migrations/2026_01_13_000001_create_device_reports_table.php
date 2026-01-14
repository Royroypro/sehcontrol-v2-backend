<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_id')
                ->constrained('devices')
                ->cascadeOnDelete();

            // cuándo el agente reportó (hora servidor)
            $table->timestamp('reported_at')->useCurrent();

            // estado del agente (observacional)
            $table->string('agent_status', 16)->nullable(); // running|idle|error
            $table->boolean('sehcontrol_running')->nullable();

            // auditoría de policy aplicada
            $table->char('policy_fingerprint_applied', 64)->nullable();

            // último error reportado por el agente
            $table->string('last_error_code', 64)->nullable();
            $table->timestamp('last_error_at')->nullable();

            // metadata flexible (por si luego agregas más campos sin migrar)
            $table->json('meta')->nullable();

            $table->timestamps();

            // índices útiles
            $table->index(['device_id', 'reported_at']);
            $table->index(['policy_fingerprint_applied']);
            $table->index(['agent_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_reports');
    }
};
