<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class SubmitDataSubjectRequestCommand
{
    public function __construct(
        public TenantId $tenantId,
        public CustomerId $customerId,
        public RequestType $requestType,
        public ?string $reason = null,
    ) {
    }
}
