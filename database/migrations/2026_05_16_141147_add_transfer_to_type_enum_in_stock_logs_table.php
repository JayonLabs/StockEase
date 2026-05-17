<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_logs MODIFY COLUMN type ENUM('in', 'out', 'adjust', 'transfer') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE stock_logs MODIFY COLUMN type ENUM('in', 'out', 'adjust') NOT NULL");
    }
};
