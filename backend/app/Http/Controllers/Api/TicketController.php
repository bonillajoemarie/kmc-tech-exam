<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Requests\Api\StoreTicketRequest;
use App\Http\Resources\TicketCommentResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Ticket::query()
            ->forUser($request->user()->id)
            ->with(['status', 'priority', 'category'])
            ->withCount(['comments' => fn ($q) => $q->where('is_internal', false)]);

        $query->when($request->get('status'), fn ($q, $slug) => $q->whereHas('status', fn ($s) => $s->where('slug', $slug)));
        $query->when($request->get('priority'), fn ($q, $slug) => $q->whereHas('priority', fn ($s) => $s->where('slug', $slug)));
        $query->when($request->get('category'), fn ($q, $slug) => $q->whereHas('category', fn ($s) => $s->where('slug', $slug)));
        $query->when($request->get('search'), function ($q, $search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        });

        $sort = in_array($request->get('sort'), ['created_at', 'updated_at']) ? $request->get('sort') : 'created_at';
        $order = strtolower((string) $request->get('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(50, max(10, (int) $request->get('per_page', 15)));

        $tickets = $query->orderBy($sort, $order)->paginate($perPage)->withQueryString();

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $ticket = Ticket::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status_id' => TicketStatus::default()->firstOrFail()->id,
        ]);

        $ticket->load(['status', 'priority', 'category'])
            ->loadCount(['comments' => fn ($q) => $q->where('is_internal', false)]);

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    public function show(Request $request, Ticket $ticket): TicketResource
    {
        $this->authorize('view', $ticket);

        $ticket->load(['status', 'priority', 'category'])
            ->loadCount(['comments' => fn ($q) => $q->where('is_internal', false)])
            ->load(['comments' => fn ($q) => $q->where('is_internal', false)->with('user:id,name')]);

        return new TicketResource($ticket);
    }

    public function addComment(StoreCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $comment = $ticket->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
            'is_internal' => false,
        ]);

        return (new TicketCommentResource($comment->load('user:id,name')))->response()->setStatusCode(201);
    }
}
