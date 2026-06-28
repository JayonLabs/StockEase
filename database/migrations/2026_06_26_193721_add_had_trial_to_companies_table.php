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
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('had_trial')->default(false)->after('is_active');
        });

        // Backfill: mark companies that already used a trial
        DB::table('companies')
            ->whereIn('id', DB::table('subscriptions')
                ->whereNotNull('trial_ends_at')
                ->distinct()
                ->pluck('company_id'))
            ->update(['had_trial' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('had_trial');
        });
    }
};
