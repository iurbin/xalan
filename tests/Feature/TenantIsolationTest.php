<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/tenant-test/customers', fn () => response()->json(Customer::query()->pluck('name')))
        ->middleware(['auth', 'tenant']);
});

test('authenticated requests only see customers belonging to their active tenant', function () {
    $firstTenant = Tenant::create(['name' => 'First', 'slug' => 'first']);
    $secondTenant = Tenant::create(['name' => 'Second', 'slug' => 'second']);

    app(TenantContext::class)->set($firstTenant->id);
    Customer::create(['name' => 'First customer']);

    app(TenantContext::class)->set($secondTenant->id);
    Customer::create(['name' => 'Second customer']);
    app(TenantContext::class)->clear();

    $user = $firstTenant->users()->create([
        'name' => 'Tenant user',
        'email' => 'user@example.test',
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->getJson('/tenant-test/customers')
        ->assertOk()
        ->assertExactJson(['First customer']);

    expect(app(TenantContext::class)->id())->toBeNull();
});

test('requests are denied when the user has no active tenant', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/tenant-test/customers')
        ->assertForbidden();
});

test('requests are denied for users belonging to an inactive tenant', function () {
    $tenant = Tenant::create([
        'name' => 'Inactive',
        'slug' => 'inactive',
        'is_active' => false,
    ]);
    $user = $tenant->users()->create([
        'name' => 'Tenant user',
        'email' => 'inactive@example.test',
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->getJson('/tenant-test/customers')
        ->assertForbidden();
});
