<?php

namespace App\Tenancy;

use InvalidArgumentException;

class TenantContext
{
    private ?int $tenantId = null;

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function set(int $tenantId): void
    {
        if ($tenantId < 1) {
            throw new InvalidArgumentException('The tenant ID must be a positive integer.');
        }

        $this->tenantId = $tenantId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }
}
