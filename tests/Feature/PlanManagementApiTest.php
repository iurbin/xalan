<?php

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;

function createPlanManagementTenant(string $name, string $email, string $role = 'admin'): array
{
    $tenant = Tenant::create([
        'name' => $name,
        'slug' => strtolower(str_replace(' ', '-', $name)),
    ]);
    $user = $tenant->users()->create([
        'name' => "{$name} user",
        'email' => $email,
        'password' => 'password',
        'role' => $role,
    ]);

    return [$tenant, $user->createToken('plan-management-test')->plainTextToken];
}

function planManagementPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Weekly Pool Care',
        'code' => 'weekly-pool-care',
        'description' => 'Weekly cleaning and chemical balancing',
        'price' => '89.50',
        'currency' => 'USD',
        'billing_interval' => 'week',
        'interval_count' => 1,
        'features' => ['Pool cleaning', 'Chemical balancing'],
        'is_active' => true,
    ], $overrides);
}

test('admins can create, update, and remove their own plans', function () {
    [$tenant, $token] = createPlanManagementTenant('Plan Admin Tenant', 'plan-admin@example.test');

    $created = $this->withToken($token)->postJson('/api/plans', planManagementPayload());
    $created->assertCreated()
        ->assertJsonPath('name', 'Weekly Pool Care')
        ->assertJsonPath('tenant_id', $tenant->id)
        ->assertJsonPath('features.1', 'Chemical balancing');
    $planId = $created->json('id');

    Auth::forgetGuards();
    $this->withToken($token)->patchJson("/api/plans/{$planId}", planManagementPayload([
        'name' => 'Biweekly Pool Care',
        'billing_interval' => 'month',
        'interval_count' => 2,
        'is_active' => false,
    ]))->assertOk()
        ->assertJsonPath('name', 'Biweekly Pool Care')
        ->assertJsonPath('interval_count', 2)
        ->assertJsonPath('is_active', false);

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/plans')
        ->assertOk()
        ->assertJsonFragment(['id' => $planId, 'is_active' => false]);

    Auth::forgetGuards();
    $this->withToken($token)->deleteJson("/api/plans/{$planId}")->assertNoContent();

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/plans')
        ->assertOk()
        ->assertJsonMissing(['id' => $planId]);
});

test('plan management is tenant-scoped, admin-only, and protects assigned plans', function () {
    [$tenant, $ownerToken] = createPlanManagementTenant('Plan Owner Tenant', 'plan-owner@example.test');
    $context = app(TenantContext::class);
    $context->set($tenant->id);
    $plan = Plan::create(planManagementPayload());
    $customer = Customer::create(['name' => 'Subscribed Customer']);
    Subscription::create([
        'customer_id' => $customer->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'next_billing_at' => now()->addWeek(),
    ]);
    $context->clear();

    $this->withToken($ownerToken)->deleteJson("/api/plans/{$plan->id}")
        ->assertConflict();

    [$otherTenant, $otherToken] = createPlanManagementTenant('Other Plan Tenant', 'other-plan@example.test');
    $context->set($otherTenant->id);
    $otherPlan = Plan::create(planManagementPayload([
        'code' => 'other-tenant-plan',
    ]));
    $context->clear();

    Auth::forgetGuards();
    $this->withToken($ownerToken)->patchJson("/api/plans/{$otherPlan->id}", [
        'name' => 'Unauthorized update',
    ])->assertNotFound();

    [, $technicianToken] = createPlanManagementTenant('Technician Plan Tenant', 'technician-plan@example.test', 'technician');
    Auth::forgetGuards();
    $this->withToken($technicianToken)->postJson('/api/plans', planManagementPayload())
        ->assertForbidden();
});
