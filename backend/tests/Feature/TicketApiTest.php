<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApiTestHelpers();
        $this->seedLookups();
    }

    private function seedLookups(): void
    {
        TicketStatus::factory()->create(['slug' => 'open', 'name' => 'Open', 'is_default' => true]);
        TicketStatus::factory()->create(['slug' => 'pending', 'name' => 'Pending']);
        TicketStatus::factory()->create(['slug' => 'resolved', 'name' => 'Resolved', 'is_closed' => true]);
        TicketStatus::factory()->create(['slug' => 'archived', 'name' => 'Archived', 'is_active' => false]);

        TicketPriority::factory()->create(['slug' => 'low', 'name' => 'Low', 'level' => 1]);
        TicketPriority::factory()->create(['slug' => 'high', 'name' => 'High', 'level' => 3]);
        TicketPriority::factory()->create(['slug' => 'ghost', 'name' => 'Ghost', 'is_active' => false]);

        TicketCategory::factory()->create(['slug' => 'billing', 'name' => 'Billing']);
        TicketCategory::factory()->create(['slug' => 'other', 'name' => 'Other', 'is_active' => false]);
    }

    private function openStatusId(): int
    {
        return TicketStatus::where('slug', 'open')->firstOrFail()->id;
    }

    private function pendingStatusId(): int
    {
        return TicketStatus::where('slug', 'pending')->firstOrFail()->id;
    }

    public function test_ticket_list_returns_only_own_tickets(): void
    {
        $alice = $this->makeCustomer('alice@example.com');
        $bob = $this->makeCustomer('bob@example.com');

        Ticket::factory()->count(3)->create(['user_id' => $alice->id, 'status_id' => $this->openStatusId()]);
        Ticket::factory()->count(2)->create(['user_id' => $bob->id, 'status_id' => $this->openStatusId()]);

        $response = $this->withHeaders($this->authHeaders($alice))->getJson('/api/tickets');

        $response->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->assertCount(3, $response->json('data'));

        foreach ($response->json('data') as $ticket) {
            $this->assertEquals($alice->id, Ticket::findOrFail($ticket['id'])->user_id);
        }
    }

    public function test_ticket_list_filters_by_status_priority_category_and_search(): void
    {
        $user = $this->makeCustomer();
        $openId = $this->openStatusId();
        $pendingId = $this->pendingStatusId();
        $highId = TicketPriority::where('slug', 'high')->firstOrFail()->id;
        $lowId = TicketPriority::where('slug', 'low')->firstOrFail()->id;
        $billingId = TicketCategory::where('slug', 'billing')->firstOrFail()->id;
        $otherId = TicketCategory::where('slug', 'other')->firstOrFail()->id;

        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $openId, 'priority_id' => $highId, 'category_id' => $billingId, 'subject' => 'Unicorn refund request']);
        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $pendingId, 'priority_id' => $lowId, 'category_id' => $billingId]);
        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $pendingId, 'priority_id' => $lowId, 'category_id' => $otherId]);

        $headers = $this->authHeaders($user);

        $this->withHeaders($headers)->getJson('/api/tickets?status=pending')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withHeaders($headers)->getJson('/api/tickets?priority=high')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Unicorn refund request');

        $this->withHeaders($headers)->getJson('/api/tickets?category=billing')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withHeaders($headers)->getJson('/api/tickets?search=unicorn')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Unicorn refund request');
    }

    public function test_ticket_list_sorts_and_paginates(): void
    {
        $user = $this->makeCustomer();
        $openId = $this->openStatusId();

        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $openId, 'created_at' => now()->subDays(3)]);
        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $openId, 'created_at' => now()->subDays(2)]);
        Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $openId, 'created_at' => now()->subDays(1)]);

        $headers = $this->authHeaders($user);

        $ascending = $this->withHeaders($headers)->getJson('/api/tickets?sort=created_at&order=asc')->assertOk();
        $this->assertTrue($ascending->json('data.0.created_at') < $ascending->json('data.1.created_at'));

        $descending = $this->withHeaders($headers)->getJson('/api/tickets?sort=created_at&order=desc')->assertOk();
        $this->assertTrue($descending->json('data.0.created_at') > $descending->json('data.1.created_at'));
    }

    public function test_ticket_list_per_page_is_clamped_between_10_and_50(): void
    {
        $user = $this->makeCustomer();
        $openId = $this->openStatusId();
        Ticket::factory()->count(25)->create(['user_id' => $user->id, 'status_id' => $openId]);

        $headers = $this->authHeaders($user);

        $default = $this->withHeaders($headers)->getJson('/api/tickets')->assertOk();
        $this->assertCount(15, $default->json('data'));
        $this->assertEquals(25, $default->json('meta.total'));

        $page = $this->withHeaders($headers)->getJson('/api/tickets?per_page=10')->assertOk();
        $this->assertCount(10, $page->json('data'));
        $this->assertEquals(3, $page->json('meta.last_page'));

        $this->withHeaders($headers)->getJson('/api/tickets?per_page=5')->assertOk()->assertJsonCount(10, 'data');
        $this->withHeaders($headers)->getJson('/api/tickets?per_page=100')->assertOk()->assertJsonCount(25, 'data');
    }

    public function test_ticket_store_creates_ticket_with_default_open_status(): void
    {
        $user = $this->makeCustomer();
        $billingId = TicketCategory::where('slug', 'billing')->firstOrFail()->id;
        $highId = TicketPriority::where('slug', 'high')->firstOrFail()->id;

        $response = $this->withHeaders($this->authHeaders($user))->postJson('/api/tickets', [
            'subject' => 'Cannot login to my account',
            'description' => 'I have been unable to login for three days now, please help.',
            'category_id' => $billingId,
            'priority_id' => $highId,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'ticket_number', 'subject', 'description', 'status', 'priority', 'category', 'created_at', 'updated_at', 'comments_count', 'is_closed'])
            ->assertJsonPath('subject', 'Cannot login to my account')
            ->assertJsonPath('status.slug', 'open')
            ->assertJsonPath('status.is_closed', false)
            ->assertJsonPath('priority.slug', 'high')
            ->assertJsonPath('category.slug', 'billing')
            ->assertJsonPath('comments_count', 0)
            ->assertJsonPath('is_closed', false);

        $ticket = Ticket::where('subject', 'Cannot login to my account')->firstOrFail();
        $this->assertEquals($user->id, $ticket->user_id);
        $this->assertEquals('open', $ticket->status->slug);
        $this->assertStringStartsWith('TK-', $ticket->ticket_number);
    }

    public function test_ticket_store_without_optional_lookups_sets_them_null(): void
    {
        $user = $this->makeCustomer();

        $this->withHeaders($this->authHeaders($user))->postJson('/api/tickets', [
            'subject' => 'Just a note',
            'description' => 'Nothing urgent, just documenting something.',
        ])->assertStatus(201)
            ->assertJsonPath('priority', null)
            ->assertJsonPath('category', null);
    }

    public function test_ticket_store_validation_failures_return_422(): void
    {
        $user = $this->makeCustomer();
        $headers = $this->authHeaders($user);
        $inactiveCategoryId = TicketCategory::where('slug', 'other')->firstOrFail()->id;

        $this->withHeaders($headers)->postJson('/api/tickets', ['description' => 'A description long enough to pass.'])
            ->assertStatus(422)->assertJsonValidationErrors('subject');

        $this->withHeaders($headers)->postJson('/api/tickets', [
            'subject' => 'Short description',
            'description' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('description');

        $this->withHeaders($headers)->postJson('/api/tickets', [
            'subject' => 'Bad priority',
            'description' => 'A description long enough to pass.',
            'priority_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors('priority_id');

        $this->withHeaders($headers)->postJson('/api/tickets', [
            'subject' => 'Inactive category',
            'description' => 'A description long enough to pass.',
            'category_id' => $inactiveCategoryId,
        ])->assertStatus(422)->assertJsonValidationErrors('category_id');
    }

    public function test_ticket_show_includes_only_non_internal_comments(): void
    {
        $user = $this->makeCustomer();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $this->openStatusId()]);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'content' => 'public note', 'is_internal' => false]);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'content' => 'internal note', 'is_internal' => true]);

        $response = $this->withHeaders($this->authHeaders($user))->getJson("/api/tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonPath('id', $ticket->id)
            ->assertJsonPath('comments_count', 1)
            ->assertJsonCount(1, 'comments')
            ->assertJsonPath('comments.0.content', 'public note')
            ->assertJsonPath('comments.0.user.id', $user->id)
            ->assertJsonPath('comments.0.user.name', $user->name)
            ->assertJsonStructure(['comments' => [['id', 'content', 'user', 'created_at']]]);

        $contents = collect($response->json('comments'))->pluck('content');
        $this->assertNotContains('internal note', $contents);
    }

    public function test_ticket_show_returns_403_for_another_users_ticket(): void
    {
        $user = $this->makeCustomer();
        $other = $this->makeCustomer();
        $ticket = Ticket::factory()->create(['user_id' => $other->id, 'status_id' => $this->openStatusId()]);

        $this->withHeaders($this->authHeaders($user))->getJson("/api/tickets/{$ticket->id}")->assertForbidden();
    }

    public function test_ticket_show_returns_404_for_nonexistent_ticket(): void
    {
        $user = $this->makeCustomer();

        $this->withHeaders($this->authHeaders($user))->getJson('/api/tickets/999999')->assertNotFound();
    }

    public function test_add_comment_succeeds_and_forces_is_internal_false(): void
    {
        $user = $this->makeCustomer();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $this->openStatusId()]);

        $response = $this->withHeaders($this->authHeaders($user))->postJson("/api/tickets/{$ticket->id}/comments", [
            'content' => 'Please check my account again.',
            'is_internal' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'content', 'user' => ['id', 'name'], 'created_at'])
            ->assertJsonPath('content', 'Please check my account again.')
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'content' => 'Please check my account again.',
            'is_internal' => 0,
        ]);
    }

    public function test_add_comment_validation_and_ownership(): void
    {
        $user = $this->makeCustomer();
        $other = $this->makeCustomer();
        $ownTicket = Ticket::factory()->create(['user_id' => $user->id, 'status_id' => $this->openStatusId()]);
        $otherTicket = Ticket::factory()->create(['user_id' => $other->id, 'status_id' => $this->openStatusId()]);
        $headers = $this->authHeaders($user);

        $this->withHeaders($headers)->postJson("/api/tickets/{$ownTicket->id}/comments", ['content' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('content');

        $this->withHeaders($headers)->postJson("/api/tickets/{$otherTicket->id}/comments", ['content' => 'A valid comment.'])
            ->assertForbidden();
    }

    public function test_meta_endpoints_return_only_active_lookups(): void
    {
        $user = $this->makeCustomer();
        $headers = $this->authHeaders($user);

        $categories = $this->withHeaders($headers)->getJson('/api/meta/categories')->assertOk();
        $this->assertContains('billing', $categories->json('*.slug'));
        $this->assertNotContains('other', $categories->json('*.slug'));
        $categories->assertJsonStructure([['id', 'name', 'slug', 'color']]);

        $priorities = $this->withHeaders($headers)->getJson('/api/meta/priorities')->assertOk();
        $this->assertContains('low', $priorities->json('*.slug'));
        $this->assertNotContains('ghost', $priorities->json('*.slug'));

        $statuses = $this->withHeaders($headers)->getJson('/api/meta/statuses')->assertOk();
        $this->assertContains('open', $statuses->json('*.slug'));
        $this->assertNotContains('archived', $statuses->json('*.slug'));
        $statuses->assertJsonStructure([['id', 'name', 'slug', 'color']]);
    }
}
