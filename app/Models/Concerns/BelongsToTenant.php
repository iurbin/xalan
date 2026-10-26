<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Tenancy\TenantContext;
use LogicException;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                throw new LogicException('A tenant context is required to create this record.');
            }

            if ($model->tenant_id !== null && (int) $model->tenant_id !== $tenantId) {
                throw new LogicException('The record tenant does not match the current tenant context.');
            }

            $model->tenant_id = $tenantId;
        });
    }
}
