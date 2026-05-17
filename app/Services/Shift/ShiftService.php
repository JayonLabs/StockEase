<?php

namespace App\Services\Shift;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\SaleStatus;
use App\Enums\ShiftStatus;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShiftService
{
    /**
     * Open a new shift for the given user.
     */
    public function openShift(User $user, float $startingCash): Shift
    {
        $existing = Shift::where('user_id', $user->id)
            ->where('status', ShiftStatus::Open->value)
            ->exists();

        if ($existing) {
            throw new \Exception('Anda masih memiliki shift yang terbuka. Tutup shift terlebih dahulu.');
        }

        return Shift::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'starting_cash' => $startingCash,
            'status' => ShiftStatus::Open->value,
        ]);
    }

    /**
     * Close the given shift.
     */
    public function closeShift(Shift $shift, float $actualCash, ?string $notes = null): Shift
    {
        if ($shift->status !== ShiftStatus::Open->value) {
            throw new \Exception('Shift ini sudah ditutup sebelumnya.');
        }

        $expectedCash = $this->calculateExpectedCash($shift);

        $shift->update([
            'closed_at' => now(),
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'cash_difference' => $actualCash - $expectedCash,
            'notes' => $notes,
            'status' => ShiftStatus::Closed->value,
        ]);

        return $shift->fresh();
    }

    /**
     * Get paginated shifts with search and date filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedShifts(?User $user, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $startDate = $filters['start'] ?? null;
        $endDate = $filters['end'] ?? null;

        return Shift::with('user')
            ->when($user && $user->hasRole(Role::Cashier->value), function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('opened_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get shift details with loaded relationships.
     */
    public function getShiftDetails(Shift $shift): Shift
    {
        return $shift->load([
            'user',
            'sales' => function ($query) {
                $query->where('status', SaleStatus::Completed->value)
                    ->orderBy('created_at', 'desc');
            },
            'sales.saleItems.product',
        ]);
    }

    /**
     * Check if the given user has an active (open) shift.
     */
    public function hasActiveShift(User $user): bool
    {
        return Shift::where('user_id', $user->id)
            ->where('status', ShiftStatus::Open->value)
            ->exists();
    }

    /**
     * Get the active shift for the given user.
     */
    public function getActiveShift(User $user): ?Shift
    {
        return Shift::where('user_id', $user->id)
            ->where('status', ShiftStatus::Open->value)
            ->latest()
            ->first();
    }

    /**
     * Calculate the expected cash for a shift.
     * Expected cash = starting_cash + total of completed cash sales during this shift.
     */
    private function calculateExpectedCash(Shift $shift): float
    {
        $cashSalesTotal = Sale::where('shift_id', $shift->id)
            ->where('status', SaleStatus::Completed->value)
            ->where('payment_method', PaymentMethod::Cash->value)
            ->sum('total');

        return (float) $shift->starting_cash + (float) $cashSalesTotal;
    }
}
