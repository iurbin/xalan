<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Plan extends Model
{
    protected $fillable = [
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
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope(includeGlobalRecords: true));

        static::creating(function (Plan $plan): void {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null && $plan->tenant_id !== null) {
                throw new LogicException('A tenant context is required to create a tenant-specific plan.');
            }

            if ($tenantId !== null && $plan->tenant_id !== null && (int) $plan->tenant_id !== $tenantId) {
                throw new LogicException('The plan tenant does not match the current tenant context.');
            }

            if ($tenantId !== null && $plan->tenant_id === null) {
                $plan->tenant_id = $tenantId;
            }
        });

    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'interval_count' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
