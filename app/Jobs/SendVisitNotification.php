<?php

namespace App\Jobs;

use App\Mail\VisitScheduleNotification;
use App\Models\Visit;
use App\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class SendVisitNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $visitId,
        public readonly int $tenantId,
        public readonly string $notificationType,
    ) {
        if (! in_array($notificationType, ['scheduled', 'rescheduled', 'reminder'], true)) {
            throw new \InvalidArgumentException('Unsupported visit notification type.');
        }
    }

    public function handle(TenantContext $tenantContext): void
    {
        $tenantContext->set($this->tenantId);

        try {
            $visit = Visit::query()
                ->with(['tenant', 'property.customer', 'technician'])
                ->findOrFail($this->visitId);

            if ($visit->tenant_id !== $this->tenantId || $visit->status !== 'scheduled') {
                return;
            }

            $sentAtColumn = $this->notificationType === 'reminder'
                ? 'reminder_sent_at'
                : 'schedule_notification_sent_at';

            if ($visit->{$sentAtColumn} !== null) {
                return;
            }

            $email = $visit->property->customer->email;

            if ($email === null) {
                Log::warning('Visit notification was not sent because the customer has no email address.', [
                    'tenant_id' => $this->tenantId,
                    'visit_id' => $visit->id,
                    'notification_type' => $this->notificationType,
                ]);

                return;
            }

            Mail::to($email)->send(new VisitScheduleNotification($visit, $this->notificationType));
            $visit->forceFill([$sentAtColumn => now()])->save();
        } finally {
            $tenantContext->clear();
        }
    }
}
