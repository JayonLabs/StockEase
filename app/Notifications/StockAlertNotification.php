<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class StockAlertNotification extends Notification implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldQueue
{
    private ?string $cachedCreatedAt = null;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly Product $product,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'stock.alert';
    }

    /**
     * Get the data to include in the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        if (! isset($this->cachedCreatedAt)) {
            $this->cachedCreatedAt = now()->toISOString();
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product->id,
            'product_slug' => $this->product->slug,
            'product_name' => $this->product->name,
            'current_stock' => $this->product->stock,
            'alert_level' => $this->product->alert_stock,
            'message' => "Stock produk {$this->product->name} di bawah batas minimum!",
            'created_at' => $this->cachedCreatedAt,
        ];
    }
}
