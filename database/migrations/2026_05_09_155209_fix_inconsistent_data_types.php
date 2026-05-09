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
        DB::statement('ALTER TABLE purchase_items MODIFY COLUMN remaining_qty INT UNSIGNED DEFAULT 0 NOT NULL');

        DB::statement('ALTER TABLE sale_items MODIFY COLUMN cost_price DECIMAL(19, 4) DEFAULT 0.0000 NOT NULL');

        DB::statement('ALTER TABLE sales MODIFY COLUMN total_cost DECIMAL(19, 4) DEFAULT 0.0000 NOT NULL');

        DB::statement('ALTER TABLE stock_logs MODIFY COLUMN reference_id BIGINT UNSIGNED NULL');

        DB::statement('ALTER TABLE stock_logs MODIFY COLUMN qty INT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE stock_adjustments MODIFY COLUMN old_stock INT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE stock_adjustments MODIFY COLUMN new_stock INT UNSIGNED NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE purchase_items MODIFY COLUMN remaining_qty INT DEFAULT 0 NOT NULL');

        DB::statement('ALTER TABLE sale_items MODIFY COLUMN cost_price DECIMAL(15, 4) DEFAULT 0.0000 NOT NULL');

        DB::statement('ALTER TABLE sales MODIFY COLUMN total_cost DECIMAL(15, 4) DEFAULT 0.0000 NOT NULL');

        DB::statement('ALTER TABLE stock_logs MODIFY COLUMN reference_id BIGINT NULL');

        DB::statement('ALTER TABLE stock_logs MODIFY COLUMN qty INT NOT NULL');

        DB::statement('ALTER TABLE stock_adjustments MODIFY COLUMN old_stock INT NOT NULL');

        DB::statement('ALTER TABLE stock_adjustments MODIFY COLUMN new_stock INT NOT NULL');
    }
};
