<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

function createTeamApiUser(Tenant $tenant, string $email, string $role): array
{
    $user = $tenant->users()->create([
        'name' => ucfirst($role).' user',
        'email' => $email,
        'password' => 'password',
        'role' => $role,
    ]);

    return [$user, $user->createToken('team-test')->plainTextToken];
}

test('tenant admins can create team members and list only their workspace users', function () {
    $tenant = Tenant::create(['name' => 'Team Alpha', 'slug' => 'team-alpha']);
    [, $adminToken] = createTeamApiUser($tenant, 'admin-alpha@example.test', 'admin');
    [, $technicianToken] = createTeamApiUser($tenant, 'tech-alpha@example.test', 'technician');

    $created = $this->withToken($adminToken)->postJson('/api/team', [
        'name' => 'New Technician',
        'email' => 'new-tech@example.test',
        'password' => 'temporary-password',
        'password_confirmation' => 'temporary-password',
        'role' => 'technician',
    ]);

    $created->assertCreated()
        ->assertJsonPath('role', 'technician')
        ->assertJsonMissingPath('password');

    Auth::forgetGuards();
    $this->withToken($technicianToken)->getJson('/api/team')->assertForbidden();

    $otherTenant = Tenant::create(['name' => 'Team Beta', 'slug' => 'team-beta']);
    [, $otherToken] = createTeamApiUser($otherTenant, 'admin-beta@example.test', 'admin');
    Auth::forgetGuards();

    $response = $this->withToken($otherToken)->getJson('/api/team');
    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonMissing(['email' => 'admin-alpha@example.test'])
        ->assertJsonMissing(['email' => 'new-tech@example.test']);
});

test('technicians cannot manage workspace members', function () {
    $tenant = Tenant::create(['name' => 'Team Security', 'slug' => 'team-security']);
    [, $technicianToken] = createTeamApiUser($tenant, 'security-tech@example.test', 'technician');

    $this->withToken($technicianToken)->postJson('/api/team', [
        'name' => 'Unauthorized Admin',
        'email' => 'unauthorized-admin@example.test',
        'password' => 'temporary-password',
        'password_confirmation' => 'temporary-password',
        'role' => 'admin',
    ])->assertForbidden();
    $this->withToken($technicianToken)->getJson('/api/team')->assertForbidden();
    Auth::forgetGuards();
    $this->withToken($technicianToken)->getJson('/api/customers')->assertForbidden();
    Auth::forgetGuards();
    $this->withToken($technicianToken)->getJson('/api/plans')->assertForbidden();
    Auth::forgetGuards();
    $this->withToken($technicianToken)->postJson('/api/visits', [])->assertForbidden();
});
