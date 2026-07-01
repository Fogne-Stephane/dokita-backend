<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
    }

    // Canal privé entre deux utilisateurs
    public function broadcastOn(): array
    {
        // On crée un canal unique pour chaque paire d'utilisateurs
        $ids = [$this->message->sender_id, $this->message->receiver_id];
        sort($ids);
        $channelName = 'chat.' . implode('.', $ids);

        return [new PrivateChannel($channelName)];
    }

    // Données envoyées avec l'event
    public function broadcastWith(): array
    {
        return [
            'id'          => $this->message->id,
            'sender_id'   => $this->message->sender_id,
            'receiver_id' => $this->message->receiver_id,
            'content'     => $this->message->content,
            'time'        => $this->message->created_at->format('H:i'),
            'is_read'     => false,
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}