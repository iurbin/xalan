<?php

use App\Models\Tenant;

test('the API user route requires Sanctum authentication and an active tenant', function () {
    $this->getJson('/api/user')->assertUnauthorized();

    $tenant = Tenant::create(['name' => 'API tenant', 'slug' => 'api-tenant']);
    $user = $tenant->users()->create([
        'name' => 'API user',
        'email' => 'api-user@example.test',
        'password' => 'password',
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});
