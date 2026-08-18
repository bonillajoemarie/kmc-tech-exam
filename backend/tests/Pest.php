<?php

use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\Client;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature')->beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    createPassportClient();
});

/**
 * Provisions a Passport personal access client so that API tokens can be issued.
 */
function createPassportClient(): void
{
    Client::factory()->asPersonalAccessTokenClient()->create([
        'name' => 'Test Personal Access Client',
    ]);
}

/**
 * Creates a user assigned to the customer role.
 */
function makeCustomer(?string $email = null): User
{
    $user = User::factory()->create($email ? ['email' => $email] : []);
    $user->assignRole(Role::findOrCreate('customer'));

    return $user;
}

/**
 * Builds the bearer Authorization header for the given user.
 *
 * @return array<string, string>
 */
function authHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('api-test')->accessToken];
}

/**
 * Seeds the ticket status/priority/category lookup tables used by the ticket tests.
 */
function seedTicketLookups(): void
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

/**
 * Returns the id of the seeded "open" status.
 */
function openStatusId(): int
{
    return TicketStatus::where('slug', 'open')->firstOrFail()->id;
}

/**
 * Returns the id of the seeded "pending" status.
 */
function pendingStatusId(): int
{
    return TicketStatus::where('slug', 'pending')->firstOrFail()->id;
}
