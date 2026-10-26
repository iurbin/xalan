<?php

namespace App\Mail;

use App\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VisitScheduleNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Visit $visit,
        public readonly string $notificationType,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->notificationType) {
            'scheduled' => "Your service visit is scheduled",
            'rescheduled' => "Your service visit has been updated",
            'reminder' => "Reminder: upcoming service visit",
            default => "Service visit update",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.visits.scheduled',
            with: [
                'visit' => $this->visit,
                'notificationType' => $this->notificationType,
                'scheduledAt' => $this->visit->scheduled_at
                    ->timezone($this->visit->tenant->timezone)
                    ->format('F j, Y \a\t g:i A T'),
            ],
        );
    }
}
