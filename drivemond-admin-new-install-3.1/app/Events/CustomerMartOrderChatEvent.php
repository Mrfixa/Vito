<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\ChattingManagement\Entities\ChannelConversation;
use Modules\ChattingManagement\Transformers\ChannelConversationResource;
use Modules\TripManagement\Entities\MartOrder;

class CustomerMartOrderChatEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected $order;
    protected $channelConversation;

    public function __construct(MartOrder $order, ChannelConversation $channelConversation)
    {
        $this->order = $order;
        $this->channelConversation = $channelConversation;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("customer-mart-chat.{$this->order->id}"),
        ];
    }

    public function broadcastAs()
    {
        return "customer-mart-chat.{$this->order->id}";
    }

    public function broadcastWith()
    {
        return [
            'channel_conversation' => ChannelConversationResource::make($this->channelConversation),
            'order_id' => $this->order->id,
        ];
    }
}
