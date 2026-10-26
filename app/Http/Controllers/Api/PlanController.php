<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id();

        return response()->json(
            Plan::query()
                ->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)
                        ->orWhere(function ($globalPlans): void {
                            $globalPlans->whereNull('tenant_id')->where('is_active', true);
                        });
                })
                ->orderBy('price')
                ->get([
                    'id',
                    'tenant_id',
                    'name',
                    'code',
                    'description',
                    'price',
                    'currency',
                    'billing_interval',
                    'interval_count',
                    'features',
                    'is_active',
                ])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules($request));
        $plan = Plan::create($validated);

        return response()->json($plan, 201);
    }

    public function update(Request $request, int $plan): JsonResponse
    {
        $record = $this->findManageablePlan($plan);
        $record->update($request->validate($this->rules($request, $record)));

        return response()->json($record->refresh());
    }

    public function destroy(int $plan): JsonResponse
    {
        $record = $this->findManageablePlan($plan);

        if ($record->subscriptions()->exists()) {
            abort(409, 'This plan is assigned to a customer subscription. Deactivate it instead of deleting it.');
        }

        $record->delete();

        return response()->json(status: 204);
    }

    private function findManageablePlan(int $plan): Plan
    {
        return Plan::query()
            ->whereKey($plan)
            ->where('tenant_id', app(TenantContext::class)->id())
            ->firstOrFail();
    }

    private function rules(Request $request, ?Plan $plan = null): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('plans', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($plan?->id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'billing_interval' => ['sometimes', 'required', Rule::in(['day', 'week', 'month', 'year'])],
            'interval_count' => ['sometimes', 'required', 'integer', 'between:1,65535'],
            'features' => ['sometimes', 'nullable', 'array', 'max:50'],
            'features.*' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
