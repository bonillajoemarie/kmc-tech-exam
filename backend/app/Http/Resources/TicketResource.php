<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status ? [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'slug' => $this->status->slug,
                'color' => $this->status->color,
                'is_closed' => (bool) $this->status->is_closed,
            ] : null,
            'priority' => $this->priority ? [
                'id' => $this->priority->id,
                'name' => $this->priority->name,
                'slug' => $this->priority->slug,
                'color' => $this->priority->color,
                'level' => $this->priority->level,
            ] : null,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'color' => $this->category->color,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'comments_count' => (int) $this->comments_count,
            'is_closed' => $this->isClosed(),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
