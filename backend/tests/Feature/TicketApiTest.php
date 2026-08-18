<?php

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketPriority;

beforeEach(function () {
    seedTicketLookups();
});

describe('ticket listing', function () {
    it('returns only the authenticated user\'s own tickets', function () {
        $alice = makeCustomer('alice@example.com');
        $bob = makeCustomer('bob@example.com');

        Ticket::factory()->count(3)->create(['user_id' => $alice->id, 'status_id' => openStatusId()]);
        Ticket::factory()->count(2)->create(['user_id' => $bob->id, 'status_id' => openStatusId()]);

        $response = $this->withHeaders(authHeaders($alice))->getJson('/api/v1/tickets');

        $response->assertOk()->assertJsonStructure(['data', 'meta']);
        expect($response->json('data'))->toHaveCount(3);

        foreach ($response->json('data') as $ticket) {
            expect(Ticket::findOrFail($ticket['id'])->user_id)->toBe($alice->id);
        }
    });

    it('filters by status, priority, category, and search', function () {
        $user = makeCustomer();
        $openId = openStatusId();
        $pendingId = pendingStatusId();
        $highId = TicketPriority::where('slug', 'high')->firstOrFail()->id;
        $lowId = TicketPriority::where('slug', 'low')->firstOrFail()->id;
        $billingId = TicketCategory::where('slug', 'billing')->firstOrFail()->id;
        $otherId = TicketCategory::where('slug', 'other')->firstOrFail()->id;

        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $openId, 'priority_id' => $highId, 'category_id' => $billingId, 'subject' => 'Unicorn refund request']);
        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $pendingId, 'priority_id' => $lowId, 'category_id' => $billingId]);
        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $pendingId, 'priority_id' => $lowId, 'category_id' => $otherId]);

        $headers = authHeaders($user);

        $this->withHeaders($headers)->getJson('/api/v1/tickets?status=pending')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withHeaders($headers)->getJson('/api/v1/tickets?priority=high')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Unicorn refund request');

        $this->withHeaders($headers)->getJson('/api/v1/tickets?category=billing')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withHeaders($headers)->getJson('/api/v1/tickets?search=unicorn')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Unicorn refund request');
    });

    it('sorts by created_at in ascending and descending order', function () {
        $user = makeCustomer();
        $openId = openStatusId();

        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $openId, 'created_at' => now()->subDays(3)]);
        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $openId, 'created_at' => now()->subDays(2)]);
        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $openId, 'created_at' => now()->subDays(1)]);

        $headers = authHeaders($user);

        $ascending = $this->withHeaders($headers)->getJson('/api/v1/tickets?sort=created_at&order=asc')->assertOk();
        expect($ascending->json('data.0.created_at'))->toBeLessThan($ascending->json('data.1.created_at'));

        $descending = $this->withHeaders($headers)->getJson('/api/v1/tickets?sort=created_at&order=desc')->assertOk();
        expect($descending->json('data.0.created_at'))->toBeGreaterThan($descending->json('data.1.created_at'));
    });

    it('clamps per_page between ten and fifty', function () {
        $user = makeCustomer();
        $openId = openStatusId();
        Ticket::factory()->count(25)->create(['user_id' => $user->id, 'status_id' => $openId]);

        $headers = authHeaders($user);

        $default = $this->withHeaders($headers)->getJson('/api/v1/tickets')->assertOk();
        expect($default->json('data'))->toHaveCount(15);
        expect($default->json('meta.total'))->toBe(25);

        $page = $this->withHeaders($headers)->getJson('/api/v1/tickets?per_page=10')->assertOk();
        expect($page->json('data'))->toHaveCount(10);
        expect($page->json('meta.last_page'))->toBe(3);

        $this->withHeaders($headers)->getJson('/api/v1/tickets?per_page=5')->assertOk()->assertJsonCount(10, 'data');
        $this->withHeaders($headers)->getJson('/api/v1/tickets?per_page=100')->assertOk()->assertJsonCount(25, 'data');
    });
});

describe('creating tickets', function () {
    it('creates a ticket with an auto-generated number and the default open status', function () {
        $user = makeCustomer();
        $billingId = TicketCategory::where('slug', 'billing')->firstOrFail()->id;
        $highId = TicketPriority::where('slug', 'high')->firstOrFail()->id;

        $response = $this->withHeaders(authHeaders($user))->postJson('/api/v1/tickets', [
            'subject' => 'Cannot login to my account',
            'description' => 'I have been unable to login for three days now, please help.',
            'category_id' => $billingId,
            'priority_id' => $highId,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'ticket_number', 'subject', 'description', 'status', 'priority', 'category', 'created_at', 'updated_at', 'comments_count', 'is_closed'])
            ->assertJsonPath('subject', 'Cannot login to my account')
            ->assertJsonPath('status.slug', 'open')
            ->assertJsonPath('status.is_closed', false)
            ->assertJsonPath('priority.slug', 'high')
            ->assertJsonPath('category.slug', 'billing')
            ->assertJsonPath('comments_count', 0)
            ->assertJsonPath('is_closed', false);

        $ticket = Ticket::where('subject', 'Cannot login to my account')->firstOrFail();
        expect($ticket->user_id)->toBe($user->id);
        expect($ticket->status->slug)->toBe('open');
        expect($ticket->ticket_number)->toStartWith('TK-');
    });

    it('creates a ticket with null optional lookups when omitted', function () {
        $user = makeCustomer();

        $this->withHeaders(authHeaders($user))->postJson('/api/v1/tickets', [
            'subject' => 'Just a note',
            'description' => 'Nothing urgent, just documenting something.',
        ])->assertCreated()
            ->assertJsonPath('priority', null)
            ->assertJsonPath('category', null);
    });

    it('rejects invalid ticket payloads with 422 validation errors', function () {
        $user = makeCustomer();
        $headers = authHeaders($user);
        $inactiveCategoryId = TicketCategory::where('slug', 'other')->firstOrFail()->id;

        $this->withHeaders($headers)->postJson('/api/v1/tickets', ['description' => 'A description long enough to pass.'])
            ->assertStatus(422)->assertJsonValidationErrors('subject');

        $this->withHeaders($headers)->postJson('/api/v1/tickets', [
            'subject' => 'Short description',
            'description' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('description');

        $this->withHeaders($headers)->postJson('/api/v1/tickets', [
            'subject' => 'Bad priority',
            'description' => 'A description long enough to pass.',
            'priority_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors('priority_id');

        $this->withHeaders($headers)->postJson('/api/v1/tickets', [
            'subject' => 'Inactive category',
            'description' => 'A description long enough to pass.',
            'category_id' => $inactiveCategoryId,
        ])->assertStatus(422)->assertJsonValidationErrors('category_id');
    });
});

describe('ticket details', function () {
    it('includes only non-internal comments on a ticket', function () {
        $user = makeCustomer();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'status_id' => openStatusId()]);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'content' => 'public note', 'is_internal' => false]);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'content' => 'internal note', 'is_internal' => true]);

        $response = $this->withHeaders(authHeaders($user))->getJson("/api/v1/tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonPath('id', $ticket->id)
            ->assertJsonPath('comments_count', 1)
            ->assertJsonCount(1, 'comments')
            ->assertJsonPath('comments.0.content', 'public note')
            ->assertJsonPath('comments.0.user.id', $user->id)
            ->assertJsonPath('comments.0.user.name', $user->name)
            ->assertJsonStructure(['comments' => [['id', 'content', 'user', 'created_at']]]);

        expect($response->json('comments.*.content'))->not->toContain('internal note');
    });

    it('returns 403 for another user\'s ticket', function () {
        $user = makeCustomer();
        $other = makeCustomer();
        $ticket = Ticket::factory()->create(['user_id' => $other->id, 'status_id' => openStatusId()]);

        $this->withHeaders(authHeaders($user))->getJson("/api/v1/tickets/{$ticket->id}")->assertForbidden();
    });

    it('returns 404 for a nonexistent ticket', function () {
        $user = makeCustomer();

        $this->withHeaders(authHeaders($user))->getJson('/api/v1/tickets/999999')->assertNotFound();
    });
});

describe('ticket comments', function () {
    it('adds a comment and forces is_internal to false', function () {
        $user = makeCustomer();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'status_id' => openStatusId()]);

        $response = $this->withHeaders(authHeaders($user))->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'content' => 'Please check my account again.',
            'is_internal' => true,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'content', 'user' => ['id', 'name'], 'created_at'])
            ->assertJsonPath('content', 'Please check my account again.')
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'content' => 'Please check my account again.',
            'is_internal' => 0,
        ]);
    });

    it('validates comment content and enforces ticket ownership', function () {
        $user = makeCustomer();
        $other = makeCustomer();
        $ownTicket = Ticket::factory()->create(['user_id' => $user->id, 'status_id' => openStatusId()]);
        $otherTicket = Ticket::factory()->create(['user_id' => $other->id, 'status_id' => openStatusId()]);
        $headers = authHeaders($user);

        $this->withHeaders($headers)->postJson("/api/v1/tickets/{$ownTicket->id}/comments", ['content' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('content');

        $this->withHeaders($headers)->postJson("/api/v1/tickets/{$otherTicket->id}/comments", ['content' => 'A valid comment.'])
            ->assertForbidden();
    });
});

describe('ticket metadata', function () {
    it('returns only active categories, priorities, and statuses', function () {
        $user = makeCustomer();
        $headers = authHeaders($user);

        $categories = $this->withHeaders($headers)->getJson('/api/v1/meta/categories')->assertOk();
        expect($categories->json('*.slug'))->toContain('billing')->not->toContain('other');
        $categories->assertJsonStructure([['id', 'name', 'slug', 'color']]);

        $priorities = $this->withHeaders($headers)->getJson('/api/v1/meta/priorities')->assertOk();
        expect($priorities->json('*.slug'))->toContain('low')->not->toContain('ghost');

        $statuses = $this->withHeaders($headers)->getJson('/api/v1/meta/statuses')->assertOk();
        expect($statuses->json('*.slug'))->toContain('open')->not->toContain('archived');
        $statuses->assertJsonStructure([['id', 'name', 'slug', 'color']]);
    });
});
