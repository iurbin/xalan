<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Visit;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;

function createCustomerServiceInvoiceFixture(string $name, string $email): array
{
    $tenant = Tenant::create(['name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name))]);
    $admin = $tenant->users()->create([
        'name' => "{$name} admin",
        'email' => $email,
        'password' => 'password',
    ]);

    $context = app(TenantContext::class);
    $context->set($tenant->id);

    try {
        $customer = Customer::create([
            'name' => "{$name} customer",
            'email' => "customer.{$email}",
        ]);
        $property = Property::create([
            'customer_id' => $customer->id,
            'address_line_1' => '10 Service Road',
            'city' => 'Austin',
        ]);
        $visit = Visit::create([
            'property_id' => $property->id,
            'status' => 'completed',
            'scheduled_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    } finally {
        $context->clear();
    }

    return [$tenant, $customer, $visit, $admin->createToken('service-invoice-test')->plainTextToken];
}

test('invoice history and PDFs identify the billed customer and service visit', function () {
    [$tenant, $customer, $visit, $token] = createCustomerServiceInvoiceFixture(
        'Service Invoice Tenant',
        'service-invoice-admin@example.test'
    );

    $invoice = $this->withToken($token)->postJson("/api/visits/{$visit->id}/invoice", [
        'amount' => '125.50',
        'currency' => 'USD',
        'description' => 'Filter cleaning and chemical balance',
    ])->assertCreated()
        ->assertJsonPath('customer_id', $customer->id)
        ->assertJsonPath('visit_id', $visit->id)
        ->assertJsonPath('total', '125.50')
        ->json();

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/invoices')
        ->assertOk()
        ->assertJsonPath('data.0.customer.id', $customer->id)
        ->assertJsonPath('data.0.visit.id', $visit->id);

    Auth::forgetGuards();
    $pdf = $this->withToken($token)->get("/api/invoices/{$invoice['id']}/download");
    $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF');

    [$otherTenant, , , $otherToken] = createCustomerServiceInvoiceFixture(
        'Other Service Invoice Tenant',
        'other-service-invoice@example.test'
    );
    expect($tenant->id)->not->toBe($otherTenant->id);

    Auth::forgetGuards();
    $this->withToken($otherToken)->getJson("/api/invoices/{$invoice['id']}/download")->assertNotFound();

    $context = app(TenantContext::class);
    $context->set($tenant->id);
    expect(Invoice::query()->where('customer_id', $customer->id)->count())->toBe(1);
    $context->clear();
});
