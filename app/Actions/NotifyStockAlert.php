<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Illuminate\Support\Facades\DB;

class NotifyStockAlert
{
    /**
     * Execute the action.
     */
    public function execute(Product $product): void
    {
        $users = User::permission('view_stock_alerts')->get();

        if ($users->isEmpty()) {
            return;
        }

        $alreadyNotifiedUserIds = DB::table('notifications')
            ->where('type', StockAlertNotification::class)
            ->whereNull('read_at')
            ->where('data->product_id', $product->id)
            ->whereIn('notifiable_id', $users->pluck('id'))
            ->pluck('notifiable_id')
            ->toArray();

        foreach ($users as $user) {
            if (! in_array($user->id, $alreadyNotifiedUserIds)) {
                $user->notify(new StockAlertNotification($product));
            }
        }
    }
}
