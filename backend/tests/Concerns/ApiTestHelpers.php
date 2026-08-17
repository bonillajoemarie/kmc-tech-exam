<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\Client;
use Spatie\Permission\Models\Role;

trait ApiTestHelpers
{
    protected function setUpApiTestHelpers(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->createPassportClient();
    }

    protected function createPassportClient(): void
    {
        Client::factory()->asPersonalAccessTokenClient()->create([
            'name' => 'Test Personal Access Client',
        ]);
    }

    protected function makeCustomer(?string $email = null): User
    {
        $user = User::factory()->create($email ? ['email' => $email] : []);
        $user->assignRole(Role::findOrCreate('customer'));

        return $user;
    }

    protected function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('api-test')->accessToken];
    }
}
