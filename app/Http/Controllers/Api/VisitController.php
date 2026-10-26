<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendVisitNotification;
use App\Jobs\GenerateVisitReport;
use App\Models\Visit;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VisitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Visit::query()
            ->with(['property.customer.activeSubscription.plan', 'technician', 'invoice'])
            ->orderBy('scheduled_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        if ($request->user()->role !== 'admin') {
            $query->where('assigned_to', $request->user()->id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => [
                'required',
                Rule::exists('properties', 'id')
                    ->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'assigned_to' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'scheduled_at' => ['required', 'date'],
            'requested_services' => ['nullable', 'array', 'max:50'],
            'requested_services.*' => ['required', 'string', 'max:255'],
        ]);

        $visit = Visit::create([
            ...$validated,
            'status' => 'scheduled',
        ]);

        SendVisitNotification::dispatch($visit->id, (int) $visit->tenant_id, 'scheduled')->afterCommit();

        return response()->json(
            $visit->load(['property.customer', 'technician']),
            201
        );
    }

    public function show(Request $request, int $visit): JsonResponse
    {
        $record = Visit::query()->with(['property.customer', 'technician'])->findOrFail($visit);
        $this->ensureVisitAccess($request, $record);

        return response()->json($record);
    }

    public function downloadReport(Request $request, int $visit)
    {
        $record = Visit::query()->findOrFail($visit);
        $this->ensureVisitAccess($request, $record);

        if ($record->report_path === null || ! Storage::disk('local')->exists($record->report_path)) {
            abort(404, 'The visit report is not available.');
        }

        return Storage::disk('local')->download(
            $record->report_path,
            "visit-{$record->id}-report.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function showPhoto(Request $request, int $visit, int $photo): BinaryFileResponse
    {
        $record = Visit::query()->findOrFail($visit);
        $this->ensureVisitAccess($request, $record);

        $path = $record->photos[$photo] ?? null;
        $expectedDirectory = "tenants/{$record->tenant_id}/visits/{$record->id}/";

        if (! is_string($path) || ! str_starts_with($path, $expectedDirectory)) {
            abort(404, 'The visit photo is not available.');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            abort(404, 'The visit photo is not available.');
        }

        $mimeType = $disk->mimeType($path);

        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            abort(404, 'The visit photo is not available.');
        }

        return response()->file($disk->path($path), [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(Request $request, int $visit): JsonResponse
    {
        $record = Visit::query()->findOrFail($visit);
        $this->ensureVisitAccess($request, $record);

        if ($record->status !== 'scheduled') {
            abort(409, 'Only scheduled visits can be changed.');
        }

        $validated = $request->validate([
            'property_id' => [
                'sometimes',
                'required',
                Rule::exists('properties', 'id')
                    ->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'assigned_to' => [
                'sometimes',
                'nullable',
                Rule::exists('users', 'id')
                    ->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'requested_services' => ['sometimes', 'nullable', 'array', 'max:50'],
            'requested_services.*' => ['required', 'string', 'max:255'],
        ]);

        $scheduleChanged = $record->fill($validated)->isDirty(['property_id', 'assigned_to', 'scheduled_at']);

        if ($scheduleChanged) {
            $record->schedule_notification_sent_at = null;
        }

        $record->save();

        if ($scheduleChanged) {
            SendVisitNotification::dispatch($record->id, (int) $record->tenant_id, 'rescheduled')->afterCommit();
        }

        return response()->json($record->refresh()->load(['property.customer', 'technician']));
    }

    public function destroy(int $visit): JsonResponse
    {
        $record = Visit::query()->findOrFail($visit);

        if ($record->status !== 'scheduled') {
            abort(409, 'Only scheduled visits can be deleted.');
        }

        $record->delete();

        return response()->json(status: 204);
    }

    public function start(Request $request, int $visit): JsonResponse
    {
        $record = Visit::query()->findOrFail($visit);

        $this->ensureVisitUpdateAccess($request, $record);

        if ($record->status !== 'scheduled') {
            abort(409, 'Only scheduled visits can be started.');
        }

        $record->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json($record->refresh()->load(['property.customer']));
    }

    public function complete(Request $request, int $visit): JsonResponse
    {
        $record = Visit::query()->findOrFail($visit);

        $this->ensureVisitUpdateAccess($request, $record);

        if ($record->status !== 'in_progress') {
            abort(409, 'Only in-progress visits can be completed.');
        }

        $validated = $request->validate([
            'chlorine_reading' => ['nullable', 'numeric', 'between:0,20'],
            'ph_reading' => ['nullable', 'numeric', 'between:0,14'],
            'services_performed' => ['nullable', 'array', 'max:50'],
            'services_performed.*' => ['required', 'string', 'max:255'],
            'technician_notes' => ['nullable', 'string', 'max:10000'],
            'photos' => ['sometimes', 'array', 'max:5'],
            'photos.*' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $storedPaths = [];

        try {
            foreach ($request->file('photos', []) as $photo) {
                $path = $photo->store(
                    "tenants/{$record->tenant_id}/visits/{$record->id}",
                    'local'
                );

                if ($path === false) {
                    throw new RuntimeException('A visit photo could not be stored.');
                }

                $storedPaths[] = $path;
            }

            $record->update([
                'status' => 'completed',
                'completed_at' => now(),
                'chlorine_reading' => $validated['chlorine_reading'] ?? null,
                'ph_reading' => $validated['ph_reading'] ?? null,
                'services_performed' => $validated['services_performed'] ?? [],
                'technician_notes' => $validated['technician_notes'] ?? null,
                'photos' => $storedPaths,
            ]);

            GenerateVisitReport::dispatch($record->id, (int) $record->tenant_id)->afterCommit();
        } catch (\Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }

        return response()->json($record->refresh()->load(['property.customer', 'technician']));
    }

    private function ensureVisitUpdateAccess(Request $request, Visit $visit): void
    {
        if (
            $request->user()->role !== 'admin'
            && (int) $visit->assigned_to !== (int) $request->user()->id
        ) {
            abort(403, 'Only the assigned technician can update this visit.');
        }
    }

    private function ensureVisitAccess(Request $request, Visit $visit): void
    {
        if ($request->user()->role !== 'admin' && (int) $visit->assigned_to !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
