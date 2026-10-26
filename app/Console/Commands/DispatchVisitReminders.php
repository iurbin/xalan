<?php

namespace App\Console\Commands;

use App\Jobs\SendVisitNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchVisitReminders extends Command
{
    protected $signature = 'visits:send-reminders';

    protected $description = 'Queue reminders for visits scheduled in the next 24 hours';

    public function handle(): int
    {
        $now = now();
        $reminderWindowEnd = $now->copy()->addDay();
        $count = 0;

        DB::table('visits')
            ->join('properties', function ($join): void {
                $join->on('properties.id', '=', 'visits.property_id')
                    ->on('properties.tenant_id', '=', 'visits.tenant_id');
            })
            ->join('customers', function ($join): void {
                $join->on('customers.id', '=', 'properties.customer_id')
                    ->on('customers.tenant_id', '=', 'visits.tenant_id');
            })
            ->where('visits.status', 'scheduled')
            ->whereNull('visits.reminder_sent_at')
            ->whereNotNull('customers.email')
            ->where('visits.scheduled_at', '>', $now)
            ->where('visits.scheduled_at', '<=', $reminderWindowEnd)
            ->select(['visits.id', 'visits.tenant_id'])
            ->orderBy('visits.id')
            ->chunk(100, function ($visits) use (&$count): void {
                foreach ($visits as $visit) {
                    SendVisitNotification::dispatch(
                        (int) $visit->id,
                        (int) $visit->tenant_id,
                        'reminder'
                    );
                    $count++;
                }
            });

        $this->info("Queued reminders for {$count} visit(s).");

        return self::SUCCESS;
    }
}
