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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status')->default('pending'); // active,canceled,expired,trialing,pending
            $table->string('billing_cycle')->default('monthly'); // monthly, annual
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('payment_method')->nullable();
            $table->string('payment_token')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('ends_at');
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
