<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite 3.31+ supports generated columns but uses different syntax.
            // Use a partial unique index instead.
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX IF NOT EXISTS sales_draft_user_id_unique
                ON sales (user_id)
                WHERE status = 'draft'
            SQL);
        } else {
            Schema::table('sales', function (Blueprint $table) {
                $table->unsignedBigInteger('draft_user_id')
                    ->virtualAs("CASE WHEN status = 'draft' THEN user_id END")
                    ->nullable()
                    ->after('status');

                $table->unique('draft_user_id', 'sales_draft_user_id_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS sales_draft_user_id_unique');
        } else {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropUnique('sales_draft_user_id_unique');
                $table->dropColumn('draft_user_id');
            });
        }
    }
};
