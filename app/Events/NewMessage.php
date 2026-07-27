<?php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        $ids = [$this->message->sender_id, $this->message->receiver_id];
        sort($ids);
        return [new PrivateChannel('chat.' . $ids[0] . '.' . $ids[1])];
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->message->id,
            'sender_id'   => $this->message->sender_id,
            'receiver_id' => $this->message->receiver_id,
            'content'     => $this->message->content,
            'time'        => $this->message->created_at->format('H:i'),
        ];
    }

    public function broadcastAs(): string { return 'message.sent'; }
}