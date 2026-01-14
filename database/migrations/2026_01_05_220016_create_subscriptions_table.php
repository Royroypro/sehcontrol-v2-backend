<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    private function foreignKeyExists(string $table, string $fkName): bool
    {
        $db = DB::getDatabaseName();

        return (bool) DB::selectOne(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME = ?
             LIMIT 1",
            [$db, $table, $fkName]
        );
    }

    public function up(): void
    {
        // Solo lo que realmente falta (si aplica):
        if (
            Schema::hasColumn('subscriptions', 'user_id') &&
            !$this->foreignKeyExists('subscriptions', 'subscriptions_user_id_foreign')
        ) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->foreign('user_id')
                    ->references('id')->on('users')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // intentionally blank
    }
};
