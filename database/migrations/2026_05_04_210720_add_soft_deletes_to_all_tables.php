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
        $tables = [
            'categories',
            'payment_transactions',
            'price_histories',
            'products',
            'promotions',
            'purchases',
            'purchase_items',
            'sales',
            'sale_items',
            'sale_returns',
            'sale_return_items',
            'shifts',
            'stock_adjustments',
            'stock_logs',
            'suppliers',
            'units',
            'users',
        ];

        // 1. Add softDeletes to all tables first
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 2. Handle slug uniqueness now that deleted_at column exists
        $this->handleSlugUniqueness('up');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Restore original slug uniqueness first
        $this->handleSlugUniqueness('down');

        // 2. Remove softDeletes from all tables
        $tables = [
            'categories',
            'payment_transactions',
            'price_histories',
            'products',
            'promotions',
            'purchases',
            'purchase_items',
            'sales',
            'sale_items',
            'sale_returns',
            'sale_return_items',
            'shifts',
            'stock_adjustments',
            'stock_logs',
            'suppliers',
            'units',
            'users',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    /**
     * Handle slug uniqueness indexes for tables with soft-deletable slug columns.
     * Drops the original unique index and creates a compound unique on (column, deleted_at).
     */
    private function handleSlugUniqueness(string $direction): void
    {
        $slugTables = [
            'categories' => ['slug'],
            'products' => ['slug'],
            'suppliers' => ['slug'],
            'units' => ['slug', 'name', 'short_name'],
        ];

        foreach ($slugTables as $table => $columns) {
            foreach ($columns as $column) {
                $oldIndex = "{$table}_{$column}_unique";
                $newIndex = "{$table}_{$column}_deleted_at_unique";

                if ($direction === 'up') {
                    $this->dropUniqueSafely($table, $oldIndex);
                    DB::statement(
                        "ALTER TABLE `{$table}` ADD UNIQUE `{$newIndex}` (`{$column}`, `deleted_at`)"
                    );
                } else {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$newIndex}`");
                    DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$oldIndex}` (`{$column}`)");
                }
            }
        }
    }

    /**
     * Drop a unique index, silently ignoring if it doesn't exist.
     */
    private function dropUniqueSafely(string $table, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropUnique($indexName);
            });
        } catch (Exception) {
        }
    }
};
