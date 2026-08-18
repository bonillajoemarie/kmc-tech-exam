<?php

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;

describe('auth endpoints', function () {
    it('registers a new customer with the customer role and returns a token', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Customer',
            'email' => 'newcustomer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'roles'], 'token'])
            ->assertJsonPath('user.name', 'New Customer')
            ->assertJsonPath('user.email', 'newcustomer@example.com')
            ->assertJsonPath('user.roles', ['customer']);

        $this->assertDatabaseHas('users', ['email' => 'newcustomer@example.com']);
        expect(User::where('email', 'newcustomer@example.com')->first()->hasRole('customer'))->toBeTrue();
        expect($response->json('token'))->not->toBeEmpty();
    });

    it('rejects a duplicate email with a 422 validation error', function () {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    });

    it('rejects an unconfirmed password with a 422 validation error', function () {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'No Confirm',
            'email' => 'noconfirm@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    });

    it('logs a customer in and returns the user with a token', function () {
        makeCustomer('login@example.com');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'roles'], 'token'])
            ->assertJsonPath('user.email', 'login@example.com')
            ->assertJsonPath('user.roles', ['customer']);

        expect($response->json('token'))->not->toBeEmpty();
    });

    it('rejects a wrong password with a 422 validation error', function () {
        makeCustomer('login@example.com');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    });

    it('throttles login attempts after ten per minute', function () {
        makeCustomer('login@example.com');

        $this->withMiddleware(ThrottleRequests::class);

        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'login@example.com',
                'password' => 'password',
            ])->assertOk();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ])->assertStatus(429);
    });

    it('revokes the token on logout and rejects the token afterwards', function () {
        $user = makeCustomer();
        $headers = authHeaders($user);

        $this->withHeaders($headers)->postJson('/api/v1/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeaders($headers)->getJson('/api/v1/user')->assertUnauthorized();
    });

    it('returns the current user with roles from the user endpoint', function () {
        $user = makeCustomer('me@example.com');

        $this->withHeaders(authHeaders($user))->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'roles'])
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', 'me@example.com')
            ->assertJsonPath('roles', ['customer']);
    });

    it('returns 401 from protected endpoints without a token', function () {
        $this->getJson('/api/v1/user')->assertUnauthorized();
        $this->getJson('/api/v1/tickets')->assertUnauthorized();
        $this->postJson('/api/v1/tickets', ['subject' => 'x', 'description' => 'y'])->assertUnauthorized();
    });
});
