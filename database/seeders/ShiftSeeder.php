<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\ShiftStatus;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            // Create 2–4 shifts per user spread over last 30 days
            $shiftCount = rand(2, 4);

            for ($s = 0; $s < $shiftCount; $s++) {
                $openedAt = Carbon::now()->subDays(rand(0, 30))->setHour(rand(6, 10))->setMinute(0);
                $closedAt = (clone $openedAt)->addHours(rand(6, 12));
                $isFuture = $closedAt->isAfter(now());
                $isOpen = $isFuture || rand(0, 1) === 0;

                $shift = Shift::create([
                    'user_id' => $user->id,
                    'opened_at' => $openedAt,
                    'starting_cash' => fake()->numberBetween(100000, 500000),
                    'status' => $isOpen ? ShiftStatus::Open->value : ShiftStatus::Closed->value,
                    'closed_at' => $isOpen ? null : $closedAt,
                    'created_at' => $openedAt,
                    'updated_at' => $isOpen ? $openedAt : $closedAt,
                ]);

                if (! $isOpen) {
                    $this->closeShiftWithSales($shift, $openedAt, $closedAt);
                }
            }
        }
    }

    /**
     * Link existing sales to the shift, then calculate and close it.
     */
    private function closeShiftWithSales(Shift $shift, Carbon $openedAt, Carbon $closedAt): void
    {
        // Find completed sales by this user within shift's time range
        $sales = Sale::where('user_id', $shift->user_id)
            ->where('status', SaleStatus::Completed->value)
            ->whereNull('shift_id')
            ->whereBetween('created_at', [$openedAt, $closedAt])
            ->take(rand(3, 15))
            ->get();

        // Link sales to this shift
        if ($sales->isNotEmpty()) {
            Sale::whereIn('id', $sales->pluck('id'))->update(['shift_id' => $shift->id]);
        }

        // Calculate expected cash = starting_cash + sum of completed cash sales
        $cashSalesTotal = Sale::where('shift_id', $shift->id)
            ->where('status', SaleStatus::Completed->value)
            ->where('payment_method', PaymentMethod::Cash->value)
            ->sum('total');

        $expectedCash = (float) $shift->starting_cash + (float) $cashSalesTotal;

        // Introduce a small random difference to simulate real-world variance (±5%)
        $variance = $expectedCash * (fake()->randomFloat(2, -0.05, 0.05));
        $actualCash = round($expectedCash + $variance, 2);
        $cashDifference = round($actualCash - $expectedCash, 2);

        $shift->update([
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'cash_difference' => $cashDifference,
            'notes' => fake()->optional(0.6)->sentence(),
        ]);
    }
}
