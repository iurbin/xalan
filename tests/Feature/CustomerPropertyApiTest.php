<?php

use App\Models\Customer;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Visit;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function apiTokenForTenant(string $name, string $email): array
{
    $tenant = Tenant::create(['name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name))]);
    $user = $tenant->users()->create([
        'name' => $name.' user',
        'email' => $email,
        'password' => 'password',
    ]);

    return [$tenant, $user->createToken('test')->plainTextToken];
}

function createTenantCustomer(Tenant $tenant, array $attributes): Customer
{
    $context = app(TenantContext::class);
    $context->set($tenant->id);

    try {
        return Customer::create($attributes);
    } finally {
        $context->clear();
    }
}

test('customer endpoints create and update records within the authenticated tenant', function () {
    [$tenant, $token] = apiTokenForTenant('Customer Co', 'owner@example.test');

    $created = $this->withToken($token)->postJson('/api/customers', [
        'name' => 'Blue Pool',
        'email' => 'contact@example.test',
        'phone' => '555-0100',
    ]);
    $created->assertCreated()->assertJsonPath('name', 'Blue Pool');

    $customerId = $created->json('id');

    $this->withToken($token)->getJson('/api/customers')
        ->assertOk()
        ->assertJsonPath('data.0.id', $customerId);

    $this->withToken($token)->patchJson('/api/customers/'.$customerId, [
        'name' => 'Blue Pool Updated',
    ])->assertOk()->assertJsonPath('name', 'Blue Pool Updated');

    expect(Customer::withoutGlobalScopes()->find($customerId)->tenant_id)->toBe($tenant->id);
});

test('property endpoints enforce tenant customer ownership and never return the access code', function () {
    [$tenant, $token] = apiTokenForTenant('Property Co', 'property-owner@example.test');
    $customer = createTenantCustomer($tenant, ['name' => 'Local Customer']);
    [$otherTenant, $otherToken] = apiTokenForTenant('Other Co', 'other-owner@example.test');
    $otherCustomer = createTenantCustomer($otherTenant, ['name' => 'Other Customer']);

    $this->withToken($token)->postJson('/api/properties', [
        'customer_id' => $otherCustomer->id,
        'address_line_1' => '1 Main St',
        'city' => 'Austin',
        'access_code' => 'private-entry-code',
    ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');

    Auth::forgetGuards();

    $created = $this->withToken($token)->postJson('/api/properties', [
        'customer_id' => $customer->id,
        'address_line_1' => '1 Main St',
        'city' => 'Austin',
        'access_code' => 'private-entry-code',
    ]);
    $created->assertCreated()->assertJsonMissing(['access_code' => 'private-entry-code']);

    $propertyId = $created->json('id');
    Auth::forgetGuards();
    $this->withToken($otherToken)->getJson('/api/properties/'.$propertyId)->assertNotFound();

    expect(Property::withoutGlobalScopes()->find($propertyId)->access_code)->toBe('private-entry-code');
});

test('customer endpoints do not reveal records from another tenant', function () {
    [, $token] = apiTokenForTenant('First Co', 'first@example.test');
    [$otherTenant] = apiTokenForTenant('Second Co', 'second@example.test');
    $otherCustomer = createTenantCustomer($otherTenant, ['name' => 'Hidden Customer']);

    $this->withToken($token)->getJson('/api/customers/'.$otherCustomer->id)->assertNotFound();
});

test('deleting a customer soft deletes its properties and visits', function () {
    [$tenant, $token] = apiTokenForTenant('Archive Co', 'archive@example.test');
    $context = app(TenantContext::class);
    $context->set($tenant->id);
    $customer = Customer::create(['name' => 'Archived Customer']);
    $property = Property::create([
        'customer_id' => $customer->id,
        'address_line_1' => '10 Archive Lane',
        'city' => 'Austin',
    ]);
    $visit = Visit::create([
        'property_id' => $property->id,
        'scheduled_at' => now()->addDay(),
    ]);
    $context->clear();

    $this->withToken($token)->deleteJson("/api/customers/{$customer->id}")->assertNoContent();

    expect(DB::table('customers')->where('id', $customer->id)->value('deleted_at'))->not->toBeNull()
        ->and(DB::table('properties')->where('id', $property->id)->value('deleted_at'))->not->toBeNull()
        ->and(DB::table('visits')->where('id', $visit->id)->value('deleted_at'))->not->toBeNull();

    $this->withToken($token)->getJson("/api/customers/{$customer->id}")->assertNotFound();
    $this->withToken($token)->getJson("/api/properties/{$property->id}")->assertNotFound();
    $this->withToken($token)->getJson("/api/visits/{$visit->id}")->assertNotFound();
});

test('operational tables support soft deletes', function () {
    foreach (['customers', 'properties', 'visits', 'invoices'] as $table) {
        expect(Schema::hasColumn($table, 'deleted_at'))->toBeTrue();
    }
});
