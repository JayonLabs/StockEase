<?php

namespace App\Http\Middleware;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $userData = null;

        if ($user) {
            $user->loadMissing('roles');
            if ($user->relationLoaded('roles')) {
                $user->roles->loadMissing('permissions');
            }
            $user->loadMissing('permissions');

            $roleNames = $user->getRoleNames();

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'photo_profile' => $user->photo_profile,
                'role' => $roleNames->first(),
                'roles' => $roleNames,
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $userData,
                'subscription' => $user
                    ? fn () => $this->getSubscription($user)
                    : null,
            ],
            'notifications' => fn () => $this->getNotifications($request),
        ];
    }

    /**
     * Get the subscription data for the authenticated user.
     */
    private function getSubscription($user): ?array
    {
        $company = $user->company;

        if (! $company) {
            return null;
        }

        $subscription = $company->activeSubscription();

        if (! $subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'billing_cycle' => $subscription->billing_cycle,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'trial_ends_at' => $subscription->trial_ends_at,
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'slug' => $subscription->plan->slug,
                'max_products' => $subscription->plan->max_products,
                'max_users' => $subscription->plan->max_users,
                'max_warehouses' => $subscription->plan->max_warehouses,
            ],
        ];
    }

    /**
     * Format notifications for frontend consumption.
     * Cached on first page load with `once()` in Inertia to prevent refetching.
     */
    private function getNotifications(Request $request): array
    {
        if (! $request->user()) {
            return [];
        }

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get();

        $productIds = $notifications->map(fn ($n) => $n->data['product_id'] ?? null)->filter()->unique();
        $products = $productIds->isEmpty() ? collect() : Product::whereIn('id', $productIds)->get()->keyBy('id');

        return $notifications->map(function ($notification) use ($products) {
            $data = $notification->data;

            // Ensure slug is present
            if (! isset($data['product_slug']) && isset($data['product_id'])) {
                $product = $products->get($data['product_id']);
                if ($product) {
                    $data['product_slug'] = $product->slug;
                }
            }

            return [
                'id' => $notification->id,
                'slug' => $data['product_slug'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'message' => $data['message'] ?? null,
                'product_name' => $data['product_name'] ?? null,
                'current_stock' => $data['current_stock'] ?? null,
                'alert_level' => $data['alert_level'] ?? null,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ];
        })->toArray();
    }
}
