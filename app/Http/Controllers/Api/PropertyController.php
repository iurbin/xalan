<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Property::query()->with('customer:id,name')->orderBy('address_line_1')->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $property = Property::create($this->validatedData($request));

        return response()->json($property->load('customer:id,name'), 201);
    }

    public function show(int $property): JsonResponse
    {
        return response()->json(
            Property::query()->with('customer:id,name')->findOrFail($property)
        );
    }

    public function update(Request $request, int $property): JsonResponse
    {
        $record = Property::query()->findOrFail($property);
        $record->update($this->validatedData($request));

        return response()->json($record->refresh()->load('customer:id,name'));
    }

    public function destroy(int $property): JsonResponse
    {
        Property::query()->findOrFail($property)->delete();

        return response()->json(status: 204);
    }

    private function validatedData(Request $request): array
    {
        $tenantId = app(TenantContext::class)->id();

        return $request->validate([
            'customer_id' => [
                'sometimes',
                'required',
                Rule::exists('customers', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_1' => ['sometimes', 'required', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'required', 'string', 'size:2'],
            'pool_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'access_code' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
    }
}
