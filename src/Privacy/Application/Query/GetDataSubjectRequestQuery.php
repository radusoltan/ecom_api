<?php

declare(strict_types=1);

namespace App\Privacy\Application\Query;

use App\Privacy\Domain\ValueObject\DataSubjectRequestId;

final readonly class GetDataSubjectRequestQuery
{
    public function __construct(
        public DataSubjectRequestId $requestId,
    ) {
    }
}
