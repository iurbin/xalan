<?php

namespace App\Jobs;

use App\Mail\VisitCompletedReport;
use App\Models\Visit;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GenerateVisitReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $visitId,
        public readonly int $tenantId,
    ) {
    }

    public function handle(TenantContext $tenantContext): void
    {
        $tenantContext->set($this->tenantId);

        try {
            $visit = Visit::query()
                ->with(['tenant', 'property.customer', 'technician'])
                ->findOrFail($this->visitId);

            if ($visit->tenant_id !== $this->tenantId || $visit->status !== 'completed') {
                throw new RuntimeException('The visit is not available for report generation.');
            }

            $pdfContents = Pdf::loadView('reports.visits.completed', [
                'visit' => $visit,
            ])->output();

            $reportPath = "tenants/{$this->tenantId}/visits/{$visit->id}/report.pdf";

            if (! Storage::disk('local')->put($reportPath, $pdfContents)) {
                throw new RuntimeException('The visit report could not be stored.');
            }

            $visit->forceFill(['report_path' => $reportPath])->save();

            $email = $visit->property->customer->email;

            if ($email === null) {
                Log::warning('Visit report generated but not emailed because the customer has no email address.', [
                    'tenant_id' => $this->tenantId,
                    'visit_id' => $visit->id,
                ]);

                return;
            }

            Mail::to($email)->send(new VisitCompletedReport($visit, $pdfContents));

            $visit->forceFill(['report_sent_at' => now()])->save();
        } finally {
            $tenantContext->clear();
        }
    }
}
