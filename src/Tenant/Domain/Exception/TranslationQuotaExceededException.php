<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Exception;

use DomainException;

final class TranslationQuotaExceededException extends DomainException
{
    public static function forTenant(string $tenantId, int $quota, int $currentUsage): self
    {
        return new self(
            sprintf(
                'Translation quota exceeded for tenant %s. Quota: %d, Current usage: %d',
                $tenantId,
                $quota,
                $currentUsage
            )
        );
    }
}
