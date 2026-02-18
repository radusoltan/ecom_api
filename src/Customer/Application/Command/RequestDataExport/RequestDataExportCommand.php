<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RequestDataExport;

/**
 * Request Data Export Command.
 *
 * Creates a new GDPR data export request for a customer.
 *
 * Business Rules:
 * - Rate limit: 1 export request per 24 hours per customer
 * - Customer must exist and belong to the tenant
 * - Export will be processed asynchronously
 */
final readonly class RequestDataExportCommand
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
    ) {
    }
}
