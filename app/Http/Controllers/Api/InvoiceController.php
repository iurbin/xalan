<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Visit;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->with(['customer', 'subscription.plan', 'visit'])
            ->orderByDesc('issued_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return response()->json($query->paginate(20));
    }

    public function show(int $invoice): JsonResponse
    {
        return response()->json(
            Invoice::query()->with(['customer', 'subscription.plan', 'visit'])->findOrFail($invoice)
        );
    }

    public function storeForVisit(Request $request, int $visit): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $tenantId = app(TenantContext::class)->id();

        $invoice = DB::transaction(function () use ($validated, $tenantId, $visit): Invoice {
            $record = Visit::query()
                ->with('property.customer')
                ->lockForUpdate()
                ->findOrFail($visit);

            if ($record->status !== 'completed') {
                throw ValidationException::withMessages([
                    'visit' => ['Invoices can only be issued for completed visits.'],
                ]);
            }

            if (Invoice::withTrashed()->where('visit_id', $record->id)->exists()) {
                throw ValidationException::withMessages([
                    'visit' => ['An invoice has already been created for this visit.'],
                ]);
            }

            $customer = $record->property->customer;
            $subscription = Subscription::query()
                ->with('plan')
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['active', 'trialing'])
                ->latest('starts_at')
                ->first();

            if ($subscription === null) {
                $errors = [];
                if (! isset($validated['amount'])) {
                    $errors['amount'] = ['An amount is required because this customer has no active service subscription.'];
                }
                if (empty($validated['currency'])) {
                    $errors['currency'] = ['A currency is required because this customer has no active service subscription.'];
                }
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }
            }

            $issuedAt = now();
            $description = $validated['description'] ?? null;
            $description = $description
                ?: sprintf(
                    'Service visit #%d - %s',
                    $record->id,
                    $customer->name
                );
            $amount = $validated['amount'] ?? 0;
            $currency = $subscription?->plan->currency ?? $validated['currency'];

            return Invoice::create([
                'customer_id' => $customer->id,
                'subscription_id' => $subscription?->id,
                'visit_id' => $record->id,
                'invoice_number' => sprintf(
                    'VIS-%d-%d-%s',
                    $tenantId,
                    $record->id,
                    $issuedAt->format('YmdHis')
                ),
                'status' => $amount > 0 ? 'due' : 'paid',
                'description' => $description,
                'subtotal' => $amount,
                'total' => $amount,
                'currency' => $currency,
                'issued_at' => $issuedAt,
                'due_at' => $amount > 0 ? $issuedAt->copy()->addDays(14) : null,
                'paid_at' => $amount > 0 ? null : $issuedAt,
            ]);
        });

        return response()->json($invoice->load(['customer', 'subscription.plan', 'visit']), 201);
    }

    public function download(int $invoice): Response
    {
        $record = Invoice::query()
            ->with(['tenant', 'customer', 'subscription.plan', 'visit.property.customer', 'visit.technician'])
            ->findOrFail($invoice);

        $pdf = Pdf::loadView('reports.invoices.invoice', [
            'invoice' => $record,
        ]);

        return $pdf->download("invoice-{$record->invoice_number}.pdf");
    }
}
