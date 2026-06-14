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
        Schema::create('platform_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->integer('total_companies')->default(0);
            $table->integer('active_companies')->default(0);
            $table->integer('total_users')->default(0);
            $table->integer('active_subscriptions')->default(0);
            $table->float('mrr')->default(0);
            $table->json('subscription_breakdown')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_daily_snapshots');
    }
};
