<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MartOrderStatusUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $orderId,
        public readonly string $status,
        public readonly string $customerId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('mart-order.'.$this->orderId),
            new PrivateChannel('customer-mart-orders.'.$this->customerId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mart.order.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'   => $this->orderId,
            'status'     => $this->status,
            'updated_at' => now()->toISOString(),
        ];
    }
}
