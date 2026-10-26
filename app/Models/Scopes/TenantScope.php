<?php

namespace App\Models\Scopes;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function __construct(private readonly bool $includeGlobalRecords = false)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($this->includeGlobalRecords) {
            $builder->where(function (Builder $query) use ($model, $tenantId): void {
                $query->whereNull($model->qualifyColumn('tenant_id'));

                if ($tenantId !== null) {
                    $query->orWhere($model->qualifyColumn('tenant_id'), $tenantId);
                }
            });

            return;
        }

        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
