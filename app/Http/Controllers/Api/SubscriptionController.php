<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\BillingCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Subscription::query()
                ->with(['customer:id,name,email', 'plan'])
                ->latest('starts_at')
                ->paginate(20)
        );
    }

    public function store(Request $request, int $customer): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer'],
        ]);
        $plan = Plan::query()
            ->whereKey($validated['plan_id'])
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_id' => ['The selected service plan is not available.'],
            ]);
        }

        $subscription = DB::transaction(function () use ($customer, $plan): Subscription {
            $customerRecord = Customer::query()->lockForUpdate()->findOrFail($customer);
            $hasActiveSubscription = Subscription::query()
                ->where('customer_id', $customerRecord->id)
                ->whereIn('status', ['active', 'trialing'])
                ->exists();

            if ($hasActiveSubscription) {
                throw ValidationException::withMessages([
                    'customer' => ['This customer already has an active service subscription.'],
                ]);
            }

            $startsAt = now();

            return Subscription::create([
                'customer_id' => $customerRecord->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $startsAt,
                'next_billing_at' => BillingCycle::nextPeriodEnd(
                    $startsAt,
                    $plan->billing_interval,
                    $plan->interval_count
                ),
            ]);
        });

        return response()->json($subscription->load(['customer', 'plan']), 201);
    }

    public function destroy(int $customer): JsonResponse
    {
        $customerRecord = Customer::query()->findOrFail($customer);
        $subscription = Subscription::query()
            ->where('customer_id', $customerRecord->id)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('starts_at')
            ->first();

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'subscription' => ['This customer does not have an active service subscription.'],
            ]);
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'ends_at' => now(),
        ]);

        return response()->json($subscription->refresh()->load(['customer', 'plan']));
    }
}
