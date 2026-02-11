<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetDataExportStatus;

/**
 * Get Data Export Status Query.
 *
 * Retrieves the status of a data export request.
 * Customers can only view their own export requests.
 */
final readonly class GetDataExportStatusQuery
{
    public function __construct(
        public string $requestId,
        public string $customerId,
        public string $tenantId
    ) {
    }
}
