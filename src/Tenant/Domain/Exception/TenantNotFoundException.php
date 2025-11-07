<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Exception;

use App\Shared\Domain\ValueObject\TenantId;

final class TenantNotFoundException extends \DomainException
{
    public static function withId(TenantId $id): self
    {
        return new self(
            sprintf('Tenant with ID "%s" was not found', $id->toString())
        );
    }

    public static function withEmail(string $email): self
    {
        return new self(
            sprintf('Tenant with owner email "%s" was not found', $email)
        );
    }
}
