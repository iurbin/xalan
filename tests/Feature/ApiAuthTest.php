<?php

use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

test('registration provisions a tenant, user, and Sanctum token', function () {
    $response = $this->postJson('/api/register', [
        'tenant_name' => 'Pool Care Co',
        'name' => 'Taylor Technician',
        'email' => 'taylor@example.test',
        'password' => 'a-secure-password',
        'password_confirmation' => 'a-secure-password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('tenant.name', 'Pool Care Co')
        ->assertJsonPath('user.email', 'taylor@example.test')
        ->assertJsonStructure(['token', 'user' => ['id', 'tenant_id', 'name', 'email']]);

    $this->withToken($response->json('token'))
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', 'taylor@example.test');
});

test('registration rejects duplicate email addresses', function () {
    $this->postJson('/api/register', [
        'tenant_name' => 'Pool Care Co',
        'name' => 'Taylor Technician',
        'email' => 'taylor@example.test',
        'password' => 'a-secure-password',
        'password_confirmation' => 'a-secure-password',
    ])->assertCreated();

    $this->postJson('/api/register', [
        'tenant_name' => 'Another Company',
        'name' => 'Another User',
        'email' => 'taylor@example.test',
        'password' => 'another-secure-password',
        'password_confirmation' => 'another-secure-password',
    ])->assertUnprocessable();
});

test('login issues a token and logout revokes the current token', function () {
    $this->postJson('/api/register', [
        'tenant_name' => 'Pool Care Co',
        'name' => 'Taylor Technician',
        'email' => 'taylor@example.test',
        'password' => 'a-secure-password',
        'password_confirmation' => 'a-secure-password',
    ])->assertCreated();

    $login = $this->postJson('/api/login', [
        'email' => 'taylor@example.test',
        'password' => 'a-secure-password',
        'device_name' => 'test-device',
    ]);
    $login->assertOk()->assertJsonStructure(['token', 'user']);

    $token = $login->json('token');

    $this->withToken($token)
        ->postJson('/api/logout')
        ->assertOk();

    expect(PersonalAccessToken::findToken($token))->toBeNull();

    Auth::forgetGuards();

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

test('login rejects incorrect credentials', function () {
    $this->postJson('/api/login', [
        'email' => 'missing@example.test',
        'password' => 'incorrect-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});
