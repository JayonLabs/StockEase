<?php

namespace App\Console\Commands\Subscription;

use App\Services\Subscription\SubscriptionService;
use Illuminate\Console\Command;

class DowngradeExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscription:downgrade-expired';

    /**
     * The console command description.
     */
    protected $description = 'Downgrade expired subscriptions ke Pemula';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $service): int
    {
        $count = $service->downgradeExpiredSubscriptions();
        $this->info("$count subscription expired telah didowngrade.");

        return Command::SUCCESS;
    }
}
