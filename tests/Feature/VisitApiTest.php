<?php

use App\Models\Property;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Invoice;
use App\Models\Visit;
use App\Jobs\GenerateVisitReport;
use App\Mail\VisitCompletedReport;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function createVisitTestProperty(Tenant $tenant): Property
{
    $context = app(TenantContext::class);
    $context->set($tenant->id);

    try {
        $customer = $tenant->customers()->create(['name' => 'Visit Customer']);

        return Property::create([
            'customer_id' => $customer->id,
            'address_line_1' => '10 Pool Lane',
            'city' => 'Austin',
        ]);
    } finally {
        $context->clear();
    }
}

function createVisitTestUser(Tenant $tenant, string $email, string $role = 'admin'): array
{
    $user = $tenant->users()->create([
        'name' => 'Visit Technician',
        'email' => $email,
        'password' => 'password',
        'role' => $role,
    ]);

    return [$user, $user->createToken('visit-test')->plainTextToken];
}

function createVisitForTenant(Tenant $tenant, Property $property, ?int $technicianId): Visit
{
    $context = app(TenantContext::class);
    $context->set($tenant->id);

    try {
        return Visit::create([
            'property_id' => $property->id,
            'assigned_to' => $technicianId,
            'scheduled_at' => now()->addDay(),
        ]);
    } finally {
        $context->clear();
    }
}

test('visits can be scheduled and cannot reference another tenant property or user', function () {
    $tenant = Tenant::create(['name' => 'Visit Co', 'slug' => 'visit-co']);
    [$technician] = createVisitTestUser($tenant, 'visit-tech@example.test');
    $property = createVisitTestProperty($tenant);
    $otherTenant = Tenant::create(['name' => 'Other Visit Co', 'slug' => 'other-visit-co']);
    $otherProperty = createVisitTestProperty($otherTenant);
    [$otherTechnician] = createVisitTestUser($otherTenant, 'other-visit-tech@example.test');
    [, $token] = createVisitTestUser($tenant, 'scheduler@example.test');

    $this->withToken($token)->postJson('/api/visits', [
        'property_id' => $otherProperty->id,
        'assigned_to' => $technician->id,
        'scheduled_at' => now()->addDay()->toISOString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('property_id');

    $this->withToken($token)->postJson('/api/visits', [
        'property_id' => $property->id,
        'assigned_to' => $otherTechnician->id,
        'scheduled_at' => now()->addDay()->toISOString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');

    $response = $this->withToken($token)->postJson('/api/visits', [
        'property_id' => $property->id,
        'assigned_to' => $technician->id,
        'scheduled_at' => now()->addDay()->toISOString(),
        'requested_services' => ['Clean the filter', 'Check the pump'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'scheduled')
        ->assertJsonPath('technician.id', $technician->id)
        ->assertJsonPath('requested_services.0', 'Clean the filter')
        ->assertJsonPath('requested_services.1', 'Check the pump');
});

test('only the assigned technician can start and complete a visit', function () {
    Storage::fake('local');

    $tenant = Tenant::create(['name' => 'Field Co', 'slug' => 'field-co']);
    $property = createVisitTestProperty($tenant);
    [$technician, $technicianToken] = createVisitTestUser($tenant, 'field-tech@example.test');
    [, $otherToken] = createVisitTestUser($tenant, 'field-other@example.test', 'technician');
    $visit = createVisitForTenant($tenant, $property, $technician->id);

    $this->withToken($otherToken)
        ->postJson("/api/visits/{$visit->id}/start")
        ->assertForbidden();

    Auth::forgetGuards();
    $this->withToken($technicianToken)
        ->postJson("/api/visits/{$visit->id}/start")
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');

    Auth::forgetGuards();
    $completed = $this->withToken($technicianToken)->post(
        "/api/visits/{$visit->id}/complete",
        [
            'chlorine_reading' => '2.50',
            'ph_reading' => '7.40',
            'services_performed' => ['Skimmed pool', 'Cleaned filter'],
            'technician_notes' => 'Water is clear.',
            'photos' => [UploadedFile::fake()->image('pool.jpg')],
        ],
        ['Accept' => 'application/json']
    );

    $completed->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('chlorine_reading', '2.50')
        ->assertJsonPath('ph_reading', '7.40')
        ->assertJsonPath('services_performed.0', 'Skimmed pool')
        ->assertJsonPath('technician_notes', 'Water is clear.');

    $path = $completed->json('photos.0');
    expect($path)->toStartWith("tenants/{$tenant->id}/visits/{$visit->id}/");
    Storage::disk('local')->assertExists($path);

    Auth::forgetGuards();
    $photoResponse = $this->withToken($technicianToken)->get("/api/visits/{$visit->id}/photos/0");
    $photoResponse->assertOk()->assertHeader('content-type', 'image/jpeg');

    [, $otherTechnicianToken] = createVisitTestUser($tenant, 'another-photo-tech@example.test', 'technician');
    Auth::forgetGuards();
    $this->withToken($otherTechnicianToken)
        ->get("/api/visits/{$visit->id}/photos/0")
        ->assertNotFound();
});

test('workplace admins can start and complete visits assigned to another technician or nobody', function () {
    Queue::fake();
    Storage::fake('local');

    $tenant = Tenant::create(['name' => 'Admin Visit Co', 'slug' => 'admin-visit-co']);
    $property = createVisitTestProperty($tenant);
    [$technician] = createVisitTestUser($tenant, 'admin-visit-tech@example.test', 'technician');
    [, $adminToken] = createVisitTestUser($tenant, 'admin-visit-admin@example.test', 'admin');
    $assignedVisit = createVisitForTenant($tenant, $property, $technician->id);
    $unassignedVisit = createVisitForTenant($tenant, $property, null);

    $this->withToken($adminToken)
        ->postJson("/api/visits/{$assignedVisit->id}/start")
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');

    Auth::forgetGuards();
    $this->withToken($adminToken)
        ->postJson("/api/visits/{$assignedVisit->id}/complete", [
            'technician_notes' => 'Completed by workspace admin.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('technician_notes', 'Completed by workspace admin.');

    Auth::forgetGuards();
    $this->withToken($adminToken)
        ->postJson("/api/visits/{$unassignedVisit->id}/start")
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');

    Auth::forgetGuards();
    $this->withToken($adminToken)
        ->postJson("/api/visits/{$unassignedVisit->id}/complete")
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    Queue::assertPushed(GenerateVisitReport::class, 2);
});

test('completed visits cannot be completed twice and invalid readings are rejected', function () {
    $tenant = Tenant::create(['name' => 'Readings Co', 'slug' => 'readings-co']);
    $property = createVisitTestProperty($tenant);
    [$technician, $token] = createVisitTestUser($tenant, 'readings-tech@example.test');
    $visit = createVisitForTenant($tenant, $property, $technician->id);

    $this->withToken($token)
        ->postJson("/api/visits/{$visit->id}/complete", ['ph_reading' => 7])
        ->assertConflict();

    $visit->update(['status' => 'in_progress', 'started_at' => now()]);
    Auth::forgetGuards();

    $this->withToken($token)
        ->postJson("/api/visits/{$visit->id}/complete", [
            'chlorine_reading' => 50,
            'ph_reading' => 7,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('chlorine_reading');
});

test('completing a visit queues its report and the job stores a PDF and emails the customer', function () {
    Storage::fake('local');
    Mail::fake();

    $tenant = Tenant::create(['name' => 'Reports Co', 'slug' => 'reports-co']);
    $property = createVisitTestProperty($tenant);
    $context = app(TenantContext::class);
    $context->set($tenant->id);
    $property->customer->update(['email' => 'customer@example.test']);
    $context->clear();
    [$technician, $token] = createVisitTestUser($tenant, 'reports-tech@example.test');
    $visit = createVisitForTenant($tenant, $property, $technician->id);
    $visit->update(['status' => 'in_progress', 'started_at' => now()]);

    Queue::fake();

    $this->withToken($token)->postJson("/api/visits/{$visit->id}/complete", [
        'chlorine_reading' => 2.1,
        'ph_reading' => 7.3,
    ])->assertOk();

    Queue::assertPushed(GenerateVisitReport::class, function (GenerateVisitReport $job) use ($tenant, $visit): bool {
        return $job->visitId === $visit->id && $job->tenantId === $tenant->id;
    });

    (new GenerateVisitReport($visit->id, $tenant->id))->handle(app(TenantContext::class));

    $visit->refresh();
    expect($visit->report_path)->toBe("tenants/{$tenant->id}/visits/{$visit->id}/report.pdf")
        ->and($visit->report_sent_at)->not->toBeNull();

    Storage::disk('local')->assertExists($visit->report_path);
    expect(Storage::disk('local')->get($visit->report_path))->toStartWith('%PDF');

    Mail::assertSent(VisitCompletedReport::class, function (VisitCompletedReport $mail): bool {
        return $mail->hasTo('customer@example.test')
            && count($mail->attachments()) === 1;
    });

    Auth::forgetGuards();
    $download = $this->withToken($token)->get("/api/visits/{$visit->id}/report");
    $download->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition');
    expect($download->streamedContent())->toStartWith('%PDF');
});

test('visit report download is tenant-scoped and returns 404 before report generation', function () {
    Storage::fake('local');

    $tenant = Tenant::create(['name' => 'Report Access Co', 'slug' => 'report-access-co']);
    $property = createVisitTestProperty($tenant);
    $visit = createVisitForTenant($tenant, $property, null);
    [, $token] = createVisitTestUser($tenant, 'report-access@example.test');
    [, $otherToken] = createVisitTestUser(
        Tenant::create(['name' => 'Other Report Co', 'slug' => 'other-report-co']),
        'other-report-access@example.test'
    );

    $this->withToken($token)
        ->get("/api/visits/{$visit->id}/report")
        ->assertNotFound();

    $visit->update(['report_path' => 'tenants/'.$tenant->id.'/visits/'.$visit->id.'/report.pdf']);
    Storage::disk('local')->put($visit->report_path, '%PDF test report');
    Auth::forgetGuards();

    $this->withToken($otherToken)
        ->get("/api/visits/{$visit->id}/report")
        ->assertNotFound();
});

test('technicians only see visits assigned to them', function () {
    $tenant = Tenant::create(['name' => 'Route Scope Co', 'slug' => 'route-scope-co']);
    $property = createVisitTestProperty($tenant);
    [$technician, $token] = createVisitTestUser($tenant, 'route-tech@example.test', 'technician');
    [$otherTechnician] = createVisitTestUser($tenant, 'route-other-tech@example.test', 'technician');
    $ownVisit = createVisitForTenant($tenant, $property, $technician->id);
    $otherVisit = createVisitForTenant($tenant, $property, $otherTechnician->id);

    $this->withToken($token)->getJson('/api/visits')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownVisit->id);

    Auth::forgetGuards();
    $this->withToken($token)->getJson("/api/visits/{$otherVisit->id}")->assertNotFound();
});

test('admins can reschedule a scheduled visit but cannot change it after it starts', function () {
    $tenant = Tenant::create(['name' => 'Reschedule Co', 'slug' => 'reschedule-co']);
    $property = createVisitTestProperty($tenant);
    [$technician] = createVisitTestUser($tenant, 'reschedule-tech@example.test', 'technician');
    [, $adminToken] = createVisitTestUser($tenant, 'reschedule-admin@example.test', 'admin');
    $visit = createVisitForTenant($tenant, $property, $technician->id);
    $newScheduledAt = now()->addDays(3)->startOfMinute();

    $this->withToken($adminToken)->patchJson("/api/visits/{$visit->id}", [
        'scheduled_at' => $newScheduledAt->toISOString(),
        'assigned_to' => null,
        'requested_services' => ['Replace the pump basket'],
    ])->assertOk()
        ->assertJsonPath('assigned_to', null)
        ->assertJsonPath('requested_services.0', 'Replace the pump basket');

    $visit->refresh();
    expect($visit->scheduled_at->equalTo($newScheduledAt))->toBeTrue();

    $visit->update(['status' => 'in_progress', 'started_at' => now()]);
    Auth::forgetGuards();

    $this->withToken($adminToken)->patchJson("/api/visits/{$visit->id}", [
        'scheduled_at' => now()->addDays(4)->toISOString(),
    ])->assertConflict();

    Auth::forgetGuards();
    $this->withToken($adminToken)->deleteJson("/api/visits/{$visit->id}")->assertConflict();
});

test('workplace admins can issue one invoice for a completed visit only', function () {
    $tenant = Tenant::create(['name' => 'Visit Invoice Co', 'slug' => 'visit-invoice-co']);
    $property = createVisitTestProperty($tenant);
    $visit = createVisitForTenant($tenant, $property, null);
    $visit->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    [, $adminToken] = createVisitTestUser($tenant, 'visit-invoice-admin@example.test', 'admin');
    [, $technicianToken] = createVisitTestUser($tenant, 'visit-invoice-tech@example.test', 'technician');

    $issued = $this->withToken($adminToken)->postJson("/api/visits/{$visit->id}/invoice", [
        'amount' => '125.50',
        'currency' => 'USD',
        'description' => 'Pool maintenance service',
    ])->assertCreated()
        ->assertJsonPath('visit_id', $visit->id)
        ->assertJsonPath('subscription_id', null)
        ->assertJsonPath('total', '125.50')
        ->assertJsonPath('currency', 'USD');

    Auth::forgetGuards();
    $this->withToken($adminToken)->get("/api/invoices/{$issued->json('id')}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    Auth::forgetGuards();
    $this->withToken($adminToken)->postJson("/api/visits/{$visit->id}/invoice", [
        'amount' => '125.50',
        'currency' => 'USD',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('visit');

    Auth::forgetGuards();
    $this->withToken($technicianToken)->postJson("/api/visits/{$visit->id}/invoice", [
        'amount' => '125.50',
        'currency' => 'USD',
    ])->assertForbidden();

    $context = app(TenantContext::class);
    $context->set($tenant->id);
    expect(Invoice::query()->where('visit_id', $visit->id)->count())->toBe(1);
    $context->clear();

    $uncompletedVisit = createVisitForTenant($tenant, $property, null);
    Auth::forgetGuards();
    $this->withToken($adminToken)->postJson("/api/visits/{$uncompletedVisit->id}/invoice", [
        'amount' => '10.00',
        'currency' => 'USD',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('visit');

    $completedVisit = createVisitForTenant($tenant, $property, null);
    $completedVisit->update(['status' => 'completed', 'completed_at' => now()]);
    Auth::forgetGuards();
    $this->withToken($adminToken)->postJson("/api/visits/{$completedVisit->id}/invoice")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'currency']);
});

test('service subscription covers the visit while the admin may add extra charges', function () {
    $tenant = Tenant::create(['name' => 'Included Service Co', 'slug' => 'included-service-co']);
    $property = createVisitTestProperty($tenant);
    [, $adminToken] = createVisitTestUser($tenant, 'included-service-admin@example.test', 'admin');
    $visit = createVisitForTenant($tenant, $property, null);
    $visit->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $context = app(TenantContext::class);
    $context->set($tenant->id);
    $customer = $property->customer;
    $plan = Plan::create([
        'name' => 'Monthly Field Care',
        'code' => 'included-service-plan',
        'price' => '99.00',
        'currency' => 'USD',
        'billing_interval' => 'month',
        'interval_count' => 1,
        'is_active' => true,
    ]);
    $subscription = Subscription::create([
        'customer_id' => $customer->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'next_billing_at' => now()->addMonth(),
    ]);
    $context->clear();

    $zeroInvoice = $this->withToken($adminToken)->postJson("/api/visits/{$visit->id}/invoice", [
        'description' => 'Included monthly service',
    ])->assertCreated()
        ->assertJsonPath('customer_id', $customer->id)
        ->assertJsonPath('subscription_id', $subscription->id)
        ->assertJsonPath('subtotal', '0.00')
        ->assertJsonPath('total', '0.00')
        ->assertJsonPath('currency', 'USD')
        ->assertJsonPath('status', 'paid');

    $extraChargeVisit = createVisitForTenant($tenant, $property, null);
    $extraChargeVisit->update(['status' => 'completed', 'completed_at' => now()]);
    Auth::forgetGuards();
    $extraInvoice = $this->withToken($adminToken)->postJson("/api/visits/{$extraChargeVisit->id}/invoice", [
        'amount' => '35.75',
        'currency' => 'EUR',
        'description' => 'Replacement filter cartridge',
    ])->assertCreated()
        ->assertJsonPath('customer_id', $customer->id)
        ->assertJsonPath('subscription_id', $subscription->id)
        ->assertJsonPath('subtotal', '35.75')
        ->assertJsonPath('total', '35.75')
        ->assertJsonPath('currency', 'USD')
        ->assertJsonPath('status', 'due');

    expect($extraInvoice->json('due_at'))->not->toBeNull()
        ->and($zeroInvoice->json('due_at'))->toBeNull();
});
