<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_logs', function ($table) {
            //
        });

        DB::statement('ALTER TABLE stock_logs MODIFY COLUMN qty INT NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE stock_logs MODIFY COLUMN qty INT UNSIGNED NOT NULL');
    }
};
