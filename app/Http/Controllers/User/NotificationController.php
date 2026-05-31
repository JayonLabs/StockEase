<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->paginate(10);

        // Eager-load product slugs in a single query to avoid N+1
        $productIds = $notifications->getCollection()
            ->pluck('data.product_id')
            ->filter()
            ->unique();

        $products = $productIds->isEmpty()
            ? collect()
            : Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Transform results to ensure slug is present even for older notifications
        $notifications->getCollection()->transform(function ($notification) use ($products) {
            if (! isset($notification->data['product_slug']) && isset($notification->data['product_id'])) {
                $product = $products->get($notification->data['product_id']);
                if ($product) {
                    $data = $notification->data;
                    $data['product_slug'] = $product->slug;
                    // We assign it back to the notification object's data attribute
                    $notification->data = $data;
                }
            }

            return $notification;
        });

        return response()->json($notifications);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Auth::user()->notifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        Auth::user()->notifications()->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
