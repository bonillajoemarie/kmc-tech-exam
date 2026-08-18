<?php

use App\Events\TicketCommentCreated;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    seedTicketLookups();
});

/**
 * Replay production channel registration against the Reverb connection so the
 * /broadcasting/auth endpoint is exercised with the real channels.php rules.
 */
function broadcastAuthConnection(): void
{
    config(['broadcasting.default' => 'reverb']);
    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');
}

describe('comment attachments', function () {
    it('stores uploaded attachments and exposes them on the comment resource', function () {
        Storage::fake('public');

        $user = makeCustomer();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'status_id' => openStatusId()]);

        $response = $this->withHeaders(authHeaders($user))->post("/api/v1/tickets/{$ticket->id}/comments", [
            'content' => 'Please see the attached invoice and screenshot.',
            'attachments' => [
                UploadedFile::fake()->create('invoice.pdf', 300, 'application/pdf'),
                UploadedFile::fake()->image('proof-of-payment.png'),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'content',
                'attachments' => [['name', 'url', 'size']],
                'user' => ['id', 'name'],
                'created_at',
            ])
            ->assertJsonCount(2, 'attachments')
            ->assertJsonPath('attachments.0.name', 'invoice.pdf')
            ->assertJsonPath('attachments.1.name', 'proof-of-payment.png');

        $comment = TicketComment::where('ticket_id', $ticket->id)->firstOrFail();
        expect($comment->attachments)->toHaveCount(2);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        foreach ($comment->attachments as $attachment) {
            $disk->assertExists($attachment['path']);
        }
    });

    it('rejects attachments with a disallowed mime type', function () {
        Storage::fake('public');

        $user = makeCustomer();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'status_id' => openStatusId()]);

        $this->withHeaders(authHeaders($user))->post("/api/v1/tickets/{$ticket->id}/comments", [
            'content' => 'Malicious upload attempt.',
            'attachments' => [UploadedFile::fake()->create('payload.exe', 100, 'application/x-msdownload')],
        ])->assertStatus(422)->assertJsonValidationErrors('attachments.0');
    });

    it('rejects more than five attachments', function () {
        Storage::fake('public');

        $user = makeCustomer();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'status_id' => openStatusId()]);

        $files = collect(range(1, 6))
            ->map(fn (int $index): UploadedFile => UploadedFile::fake()->create("file-{$index}.pdf", 50, 'application/pdf'))
            ->all();

        $this->withHeaders(authHeaders($user))->post("/api/v1/tickets/{$ticket->id}/comments", [
            'content' => 'Too many files.',
            'attachments' => $files,
        ])->assertStatus(422)->assertJsonValidationErrors('attachments');
    });

    it('returns attachments when listing comments on a ticket', function () {
        Storage::fake('public');

        $user = makeCustomer();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'status_id' => openStatusId()]);

        $this->withHeaders(authHeaders($user))->post("/api/v1/tickets/{$ticket->id}/comments", [
            'content' => 'Attachment here.',
            'attachments' => [UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf')],
        ])->assertCreated();

        $this->withHeaders(authHeaders($user))->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonCount(1, 'comments')
            ->assertJsonStructure(['comments' => [['id', 'content', 'attachments' => [['name', 'url', 'size']], 'user', 'created_at']]]);
    });

    it('does not expose another customers comments', function () {
        Storage::fake('public');

        $user = makeCustomer();
        $other = makeCustomer();
        $otherTicket = Ticket::factory()->create(['user_id' => $other->id, 'status_id' => openStatusId()]);

        $this->withHeaders(authHeaders($user))->getJson("/api/v1/tickets/{$otherTicket->id}")->assertForbidden();
    });
});

describe('comment realtime events', function () {
    it('broadcasts TicketCommentCreated to the ticket owner and staff channels', function () {
        $owner = makeCustomer();
        $staff = User::factory()->create()->assignRole(Role::findOrCreate('admin'));
        $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'status_id' => openStatusId()]);

        Event::fake([TicketCommentCreated::class]);

        TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'content' => 'A staff reply on the owners ticket.',
            'is_internal' => false,
        ]);

        Event::assertDispatched(TicketCommentCreated::class, function (TicketCommentCreated $event) use ($owner, $staff, $ticket): bool {
            $channels = collect($event->broadcastOn());
            $payload = $event->broadcastWith();

            return $channels->contains(fn ($channel): bool => $channel->name === "private-user.{$owner->id}")
                && $channels->contains(fn ($channel): bool => $channel->name === 'private-staff')
                && $payload['ticket_number'] === $ticket->ticket_number
                && $payload['author_id'] === $staff->id;
        });
    });

    it('does not broadcast internal comments to customers', function () {
        $owner = makeCustomer();
        $staff = User::factory()->create()->assignRole(Role::findOrCreate('admin'));
        $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'status_id' => openStatusId()]);

        Event::fake([TicketCommentCreated::class]);

        TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'content' => 'Internal note for staff only.',
            'is_internal' => true,
        ]);

        Event::assertNotDispatched(TicketCommentCreated::class);
    });
});

describe('broadcast channel authorization', function () {
    it('authorizes the passport token holder on the private-user channel and denies others', function () {
        broadcastAuthConnection();

        $user = makeCustomer();
        $other = makeCustomer();
        $headers = authHeaders($user);

        $this->withHeaders($headers)->post('/broadcasting/auth', [
            'channel_name' => "private-user.{$user->id}",
            'socket_id' => '123456.7890',
        ])->assertOk()->assertJsonStructure(['auth']);

        app(AuthManager::class)->forgetGuards();

        $this->withHeaders($headers)->post('/broadcasting/auth', [
            'channel_name' => "private-user.{$other->id}",
            'socket_id' => '123456.7890',
        ])->assertForbidden();
    });

    it('authorizes only admin users on the private-staff channel', function () {
        broadcastAuthConnection();

        $customer = makeCustomer();
        $admin = User::factory()->create()->assignRole(Role::findOrCreate('admin'));

        $this->withHeaders(authHeaders($admin))->post('/broadcasting/auth', [
            'channel_name' => 'private-staff',
            'socket_id' => '123456.7890',
        ])->assertOk()->assertJsonStructure(['auth']);

        app(AuthManager::class)->forgetGuards();

        $this->withHeaders(authHeaders($customer))->post('/broadcasting/auth', [
            'channel_name' => 'private-staff',
            'socket_id' => '123456.7890',
        ])->assertForbidden();
    });
});
