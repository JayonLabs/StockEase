<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move promotion_id and discount_amount before created_at
     * so that timestamps and soft deletes are the last columns.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `sale_items` MODIFY COLUMN `promotion_id` BIGINT UNSIGNED NULL AFTER `cost_price`');
        DB::statement('ALTER TABLE `sale_items` MODIFY COLUMN `discount_amount` DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER `promotion_id`');
    }

    /**
     * Restore columns to their original position (after updated_at).
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `sale_items` MODIFY COLUMN `promotion_id` BIGINT UNSIGNED NULL AFTER `updated_at`');
        DB::statement('ALTER TABLE `sale_items` MODIFY COLUMN `discount_amount` DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER `promotion_id`');
    }
};
