<?php

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;

function createCustomerSubscriptionApiTenant(string $name, string $email): array
{
    $tenant = Tenant::create(['name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name))]);
    $user = $tenant->users()->create([
        'name' => $name.' admin',
        'email' => $email,
        'password' => 'password',
    ]);

    return [$tenant, $user->createToken('customer-subscription-test')->plainTextToken];
}

function createCustomerSubscriptionPlan(array $attributes = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'Pool Care',
        'code' => 'pool-care-'.fake()->unique()->numerify('####'),
        'price' => 49.99,
        'currency' => 'USD',
        'billing_interval' => 'month',
        'interval_count' => 1,
        'is_active' => true,
    ], $attributes));
}

function createCustomerForSubscription(Tenant $tenant, string $name): Customer
{
    $context = app(TenantContext::class);
    $context->set($tenant->id);

    try {
        return Customer::create(['name' => $name]);
    } finally {
        $context->clear();
    }
}

test('admins can assign and cancel service subscriptions for their customers', function () {
    [$tenant, $token] = createCustomerSubscriptionApiTenant('Subscription Tenant', 'subscription@example.test');
    $customer = createCustomerForSubscription($tenant, 'Pool Customer');
    $plan = createCustomerSubscriptionPlan();

    $created = $this->withToken($token)->postJson("/api/customers/{$customer->id}/subscription", [
        'plan_id' => $plan->id,
    ]);
    $created->assertCreated()
        ->assertJsonPath('customer_id', $customer->id)
        ->assertJsonPath('customer.id', $customer->id)
        ->assertJsonPath('plan.id', $plan->id)
        ->assertJsonPath('status', 'active');

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/subscriptions')
        ->assertOk()
        ->assertJsonPath('data.0.customer.id', $customer->id)
        ->assertJsonPath('data.0.plan.id', $plan->id);

    Auth::forgetGuards();
    $this->withToken($token)->postJson("/api/customers/{$customer->id}/subscription", [
        'plan_id' => $plan->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('customer');

    Auth::forgetGuards();
    $this->withToken($token)->deleteJson("/api/customers/{$customer->id}/subscription")
        ->assertOk()
        ->assertJsonPath('status', 'canceled');
});

test('customer subscriptions cannot use another tenant customer or private plan', function () {
    [$ownerTenant, $ownerToken] = createCustomerSubscriptionApiTenant('Owner Tenant', 'owner-sub@example.test');
    $ownerCustomer = createCustomerForSubscription($ownerTenant, 'Owner customer');
    $context = app(TenantContext::class);
    $context->set($ownerTenant->id);
    $privatePlan = createCustomerSubscriptionPlan([
        'name' => 'Private plan',
        'tenant_id' => $ownerTenant->id,
    ]);
    $context->clear();

    [$otherTenant, $otherToken] = createCustomerSubscriptionApiTenant('Other Tenant', 'other-sub@example.test');
    $otherCustomer = createCustomerForSubscription($otherTenant, 'Other customer');

    $this->withToken($otherToken)->postJson("/api/customers/{$otherCustomer->id}/subscription", [
        'plan_id' => $privatePlan->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('plan_id');

    Auth::forgetGuards();
    $this->withToken($ownerToken)->postJson("/api/customers/{$otherCustomer->id}/subscription", [
        'plan_id' => $privatePlan->id,
    ])->assertNotFound();

    Auth::forgetGuards();
    $this->withToken($ownerToken)->postJson("/api/customers/{$ownerCustomer->id}/subscription", [
        'plan_id' => $privatePlan->id,
    ])->assertCreated();
});
