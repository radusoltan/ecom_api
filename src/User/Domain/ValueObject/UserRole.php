<?php

declare(strict_types=1);

namespace App\User\Domain\ValueObject;

final readonly class UserRole implements \Stringable
{
    // Admin Panel Roles (for backend admin interface)
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const ROLE_MANAGER = 'ROLE_MANAGER';
    public const ROLE_VIEWER = 'ROLE_VIEWER';

    // Storefront & Business Roles (for customers, tenants, vendors)
    public const ROLE_CUSTOMER = 'ROLE_CUSTOMER';
    public const ROLE_TENANT_ADMIN = 'ROLE_TENANT_ADMIN';
    public const ROLE_TENANT_USER = 'ROLE_TENANT_USER';
    public const ROLE_VENDOR = 'ROLE_VENDOR';

    private const VALID_ROLES = [
        // Admin Panel Roles
        self::ROLE_USER,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
        self::ROLE_MANAGER,
        self::ROLE_VIEWER,
        // Storefront & Business Roles
        self::ROLE_CUSTOMER,
        self::ROLE_TENANT_ADMIN,
        self::ROLE_TENANT_USER,
        self::ROLE_VENDOR,
    ];

    private function __construct(
        private string $value,
    ) {
        if (!in_array($value, self::VALID_ROLES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid user role: "%s". Must be one of: %s', $value, implode(', ', self::VALID_ROLES)));
        }
    }

    // Admin Panel Role Factories
    public static function user(): self
    {
        return new self(self::ROLE_USER);
    }

    public static function admin(): self
    {
        return new self(self::ROLE_ADMIN);
    }

    public static function superAdmin(): self
    {
        return new self(self::ROLE_SUPER_ADMIN);
    }

    public static function manager(): self
    {
        return new self(self::ROLE_MANAGER);
    }

    public static function viewer(): self
    {
        return new self(self::ROLE_VIEWER);
    }

    // Storefront & Business Role Factories
    public static function customer(): self
    {
        return new self(self::ROLE_CUSTOMER);
    }

    public static function tenantAdmin(): self
    {
        return new self(self::ROLE_TENANT_ADMIN);
    }

    public static function tenantUser(): self
    {
        return new self(self::ROLE_TENANT_USER);
    }

    public static function vendor(): self
    {
        return new self(self::ROLE_VENDOR);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    // Admin Panel Role Helpers
    public function isSuperAdmin(): bool
    {
        return self::ROLE_SUPER_ADMIN === $this->value;
    }

    public function isAdmin(): bool
    {
        return in_array($this->value, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function isManager(): bool
    {
        return self::ROLE_MANAGER === $this->value;
    }

    public function isViewer(): bool
    {
        return self::ROLE_VIEWER === $this->value;
    }

    // Storefront & Business Role Helpers
    public function isCustomer(): bool
    {
        return self::ROLE_CUSTOMER === $this->value;
    }

    public function isTenantAdmin(): bool
    {
        return self::ROLE_TENANT_ADMIN === $this->value;
    }

    public function isTenantUser(): bool
    {
        return self::ROLE_TENANT_USER === $this->value;
    }

    public function isVendor(): bool
    {
        return self::ROLE_VENDOR === $this->value;
    }

    /**
     * Check if user has administrative privileges (SUPER_ADMIN, ADMIN, MANAGER, TENANT_ADMIN).
     */
    public function hasAdminPrivileges(): bool
    {
        return in_array($this->value, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_TENANT_ADMIN,
        ], true);
    }

    /**
     * Check if user has read-only privileges (VIEWER).
     */
    public function isReadOnly(): bool
    {
        return self::ROLE_VIEWER === $this->value;
    }
}
