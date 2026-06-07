<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            'units',
            'suppliers',
            'products',
            'warehouses',
            'warehouse_product',
            'sales',
            'sale_items',
            'sale_returns',
            'sale_return_items',
            'sale_emails',
            'purchases',
            'purchase_items',
            'stock_logs',
            'stock_adjustments',
            'stock_transfers',
            'shifts',
            'promotions',
            'payment_transactions',
            'price_histories',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
                $table->index('company_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'price_histories',
            'payment_transactions',
            'promotions',
            'shifts',
            'stock_transfers',
            'stock_adjustments',
            'stock_logs',
            'purchase_items',
            'purchases',
            'sale_emails',
            'sale_return_items',
            'sale_returns',
            'sale_items',
            'sales',
            'warehouse_product',
            'warehouses',
            'products',
            'suppliers',
            'units',
            'categories',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropIndex(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
