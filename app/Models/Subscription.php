<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Subscription extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            if (! Plan::query()->whereKey($subscription->plan_id)->exists()) {
                throw new LogicException('The selected plan is not available to the current tenant.');
            }
                if (
                    $subscription->customer_id === null
                    || ! Customer::query()->whereKey($subscription->customer_id)->exists()
                ) {
                    throw new LogicException('The selected customer is not available to the current tenant.');
                }
        });
    }

    protected $fillable = [
        'customer_id',
        'plan_id',
        'status',
        'starts_at',
        'trial_ends_at',
        'ends_at',
        'next_billing_at',
        'canceled_at',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'next_billing_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
