<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ApiTestHelpers;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use ApiTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApiTestHelpers();
    }

    public function test_register_creates_user_with_customer_role_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New Customer',
            'email' => 'newcustomer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'roles'], 'token'])
            ->assertJsonPath('user.name', 'New Customer')
            ->assertJsonPath('user.email', 'newcustomer@example.com')
            ->assertJsonPath('user.roles', ['customer']);

        $this->assertDatabaseHas('users', ['email' => 'newcustomer@example.com']);
        $this->assertTrue(User::where('email', 'newcustomer@example.com')->first()->hasRole('customer'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_register_with_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Duplicate',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_register_with_unconfirmed_password_returns_422(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'No Confirm',
            'email' => 'noconfirm@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_login_success_returns_user_and_token(): void
    {
        $this->makeCustomer('login@example.com');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'roles'], 'token'])
            ->assertJsonPath('user.email', 'login@example.com')
            ->assertJsonPath('user.roles', ['customer']);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        $this->makeCustomer('login@example.com');

        $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_logout_revokes_token_and_subsequent_request_is_unauthorized(): void
    {
        $user = $this->makeCustomer();
        $headers = $this->authHeaders($user);

        $this->withHeaders($headers)->postJson('/api/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeaders($headers)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_user_endpoint_returns_current_user_with_roles(): void
    {
        $user = $this->makeCustomer('me@example.com');

        $this->withHeaders($this->authHeaders($user))->getJson('/api/user')
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'roles'])
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', 'me@example.com')
            ->assertJsonPath('roles', ['customer']);
    }

    public function test_protected_endpoints_return_401_without_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/tickets')->assertUnauthorized();
        $this->postJson('/api/tickets', ['subject' => 'x', 'description' => 'y'])->assertUnauthorized();
    }
}
