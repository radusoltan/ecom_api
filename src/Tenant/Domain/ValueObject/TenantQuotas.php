<?php

declare(strict_types=1);

namespace App\Tenant\Domain\ValueObject;

final readonly class TenantQuotas
{
    public function __construct(
        public int $maxProducts,
        public int $maxOrdersPerMonth,
        public int $maxStorageMb,
        public int $maxTranslations,
        public int $maxWarehouses,
        public int $maxUsers,
    ) {
        if ($maxProducts < 0) {
            throw new \InvalidArgumentException('maxProducts cannot be negative');
        }
        if ($maxOrdersPerMonth < 0) {
            throw new \InvalidArgumentException('maxOrdersPerMonth cannot be negative');
        }
        if ($maxStorageMb < 0) {
            throw new \InvalidArgumentException('maxStorageMb cannot be negative');
        }
        if ($maxTranslations < 0) {
            throw new \InvalidArgumentException('maxTranslations cannot be negative');
        }
        if ($maxWarehouses < 0) {
            throw new \InvalidArgumentException('maxWarehouses cannot be negative');
        }
        if ($maxUsers < 0) {
            throw new \InvalidArgumentException('maxUsers cannot be negative');
        }
    }

    public static function forTier(string $tier): self
    {
        return match ($tier) {
            'starter' => new self(100, 500, 1024, 1000, 1, 3),
            'professional' => new self(5000, 10000, 10240, 10000, 5, 25),
            'enterprise' => new self(PHP_INT_MAX, PHP_INT_MAX, 102400, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX),
            default => throw new \InvalidArgumentException(sprintf('Unknown tier: %s', $tier)),
        };
    }

    public function equals(self $other): bool
    {
        return $this->maxProducts === $other->maxProducts
            && $this->maxOrdersPerMonth === $other->maxOrdersPerMonth
            && $this->maxStorageMb === $other->maxStorageMb
            && $this->maxTranslations === $other->maxTranslations
            && $this->maxWarehouses === $other->maxWarehouses
            && $this->maxUsers === $other->maxUsers;
    }
}
