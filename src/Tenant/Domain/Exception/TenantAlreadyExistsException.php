<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Exception;

use App\Shared\Domain\ValueObject\TenantId;

final class TenantAlreadyExistsException extends \DomainException
{
    public static function withId(TenantId $id): self
    {
        return new self(
            sprintf('Tenant with ID "%s" already exists', $id->toString())
        );
    }

    public static function withEmail(string $email): self
    {
        return new self(
            sprintf('Tenant with owner email "%s" already exists', $email)
        );
    }
}
