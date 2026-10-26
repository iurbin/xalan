<?php

use App\Jobs\SendVisitNotification;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Visit;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function createVisitNotificationFixture(string $name, string $email, $scheduledAt = null): array
{
    $tenant = Tenant::create([
        'name' => $name,
        'slug' => strtolower(str_replace(' ', '-', $name)),
        'timezone' => 'America/Los_Angeles',
    ]);
    $user = $tenant->users()->create([
        'name' => "{$name} Admin",
        'email' => "{$name}.admin@example.test",
        'password' => 'password',
    ]);

    $context = app(TenantContext::class);
    $context->set($tenant->id);

    try {
        $customer = $tenant->customers()->create([
            'name' => "{$name} Customer",
            'email' => $email,
        ]);
        $property = Property::create([
            'customer_id' => $customer->id,
            'address_line_1' => '100 Service Way',
            'city' => 'Los Angeles',
        ]);
        $visit = Visit::create([
            'property_id' => $property->id,
            'scheduled_at' => $scheduledAt ?? now()->addHours(12),
            'status' => 'scheduled',
        ]);
    } finally {
        $context->clear();
    }

    return [$tenant, $user->createToken('notification-test')->plainTextToken, $visit];
}

test('creating and rescheduling a visit queue customer notifications', function () {
    Queue::fake();
    [$tenant, $token, $visit] = createVisitNotificationFixture(
        'Notice Tenant',
        'notice-customer@example.test'
    );

    $scheduled = $this->withToken($token)->postJson('/api/visits', [
        'property_id' => $visit->property_id,
        'scheduled_at' => now()->addHours(8)->toISOString(),
    ]);
    $scheduled->assertCreated();
    $createdId = $scheduled->json('id');

    Queue::assertPushed(SendVisitNotification::class, function (SendVisitNotification $job) use ($tenant, $createdId): bool {
        return $job->visitId === $createdId
            && $job->tenantId === $tenant->id
            && $job->notificationType === 'scheduled';
    });

    Auth::forgetGuards();
    $this->withToken($token)->patchJson("/api/visits/{$createdId}", [
        'scheduled_at' => now()->addHours(10)->toISOString(),
    ])->assertOk();

    Queue::assertPushed(SendVisitNotification::class, 2);
});

test('visit notification job emails in tenant timezone and does not send the same notification twice', function () {
    Mail::fake();
    [$tenant, , $visit] = createVisitNotificationFixture(
        'Timezone Tenant',
        'timezone-customer@example.test',
        now()->addHours(12)
    );

    $job = new SendVisitNotification($visit->id, $tenant->id, 'scheduled');
    $job->handle(app(TenantContext::class));
    $job->handle(app(TenantContext::class));

    Mail::assertSent(\App\Mail\VisitScheduleNotification::class, function ($mail): bool {
        return $mail->hasTo('timezone-customer@example.test')
            && $mail->notificationType === 'scheduled'
            && str_contains($mail->content()->with['scheduledAt'], 'PDT');
    });
    Mail::assertSent(\App\Mail\VisitScheduleNotification::class, 1);

    $visit->refresh();
    expect($visit->schedule_notification_sent_at)->not->toBeNull();
});

test('reminder command queues only upcoming scheduled visits with customer email addresses', function () {
    Queue::fake();
    [$tenant, , $dueVisit] = createVisitNotificationFixture(
        'Reminder Tenant',
        'reminder-customer@example.test',
        now()->addHours(12)
    );
    [, , $laterVisit] = createVisitNotificationFixture(
        'Later Tenant',
        'later-customer@example.test',
        now()->addHours(30)
    );
    [, , $pastVisit] = createVisitNotificationFixture(
        'Past Tenant',
        'past-customer@example.test',
        now()->subHour()
    );

    Artisan::call('visits:send-reminders');

    Queue::assertPushed(SendVisitNotification::class, 1);
    Queue::assertPushed(SendVisitNotification::class, function (SendVisitNotification $job) use ($tenant, $dueVisit): bool {
        return $job->visitId === $dueVisit->id
            && $job->tenantId === $tenant->id
            && $job->notificationType === 'reminder';
    });
    Queue::assertNotPushed(SendVisitNotification::class, function (SendVisitNotification $job) use ($laterVisit, $pastVisit): bool {
        return in_array($job->visitId, [$laterVisit->id, $pastVisit->id], true);
    });
});
