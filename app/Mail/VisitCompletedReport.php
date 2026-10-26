<?php

namespace App\Mail;

use App\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VisitCompletedReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Visit $visit,
        private readonly string $pdfContents,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Service report for visit #{$this->visit->id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.visits.completed',
            with: [
                'visit' => $this->visit,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => $this->pdfContents,
                "visit-{$this->visit->id}-report.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
