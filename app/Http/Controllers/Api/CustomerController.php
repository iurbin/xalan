<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Customer::query()->withCount('properties')->orderBy('name')->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $customer = Customer::create($this->validatedData($request));

        return response()->json($customer, 201);
    }

    public function show(int $customer): JsonResponse
    {
        return response()->json(
            Customer::query()->with('properties')->findOrFail($customer)
        );
    }

    public function update(Request $request, int $customer): JsonResponse
    {
        $record = Customer::query()->findOrFail($customer);
        $record->update($this->validatedData($request, $record));

        return response()->json($record->refresh());
    }

    public function destroy(int $customer): JsonResponse
    {
        Customer::query()->findOrFail($customer)->delete();

        return response()->json(status: 204);
    }

    private function validatedData(Request $request, ?Customer $customer = null): array
    {
        $tenantId = app(TenantContext::class)->id();

        return $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where('tenant_id', $tenantId)
                    ->ignore($customer?->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
        ]);
    }
}
