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
        Schema::table('sales', function (Blueprint $table) {
            $table->index('date');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index('date');
            $table->index('created_at');
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('type');
        });

        Schema::table('price_histories', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->index('date');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['type']);
        });

        Schema::table('price_histories', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['stock']);
        });
    }
};
