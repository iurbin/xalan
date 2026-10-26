<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->tenant_id === null) {
            abort(403, 'An active tenant is required.');
        }

        $tenantIsActive = Tenant::query()
            ->whereKey($user->tenant_id)
            ->where('is_active', true)
            ->exists();

        if (! $tenantIsActive) {
            abort(403, 'An active tenant is required.');
        }

        $context = app(TenantContext::class);
        $context->set((int) $user->tenant_id);

        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
