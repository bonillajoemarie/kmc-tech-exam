<?php

namespace App\Events;

use App\Models\TicketComment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class TicketCommentCreated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public TicketComment $comment) {}

    /**
     * Broadcast on the ticket owner's private channel (so customers hear staff
     * replies in real time) and the staff channel.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->comment->ticket->user_id),
            new PrivateChannel('staff'),
        ];
    }

    /**
     * Keep the payload small and free of internal models.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'comment_id' => $this->comment->id,
            'ticket_id' => $this->comment->ticket_id,
            'ticket_number' => $this->comment->ticket?->ticket_number,
            'author_id' => $this->comment->user_id,
            'user_name' => $this->comment->user?->name,
            'content' => $this->comment->content,
            'created_at' => $this->comment->created_at?->toISOString(),
        ];
    }
}
