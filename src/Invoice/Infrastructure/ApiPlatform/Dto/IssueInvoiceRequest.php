<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\ApiPlatform\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for issuing a draft invoice.
 *
 * Used by POST /api/invoices/{id}/issue endpoint to issue a draft invoice.
 *
 * Example payload:
 * ```json
 * {
 *   "dueDate": "2025-12-31T23:59:59Z"
 * }
 * ```
 */
final class IssueInvoiceRequest
{
    #[Groups(['invoice:write'])]
    #[Assert\DateTime(message: 'Due date must be a valid date')]
    public ?string $dueDate = null;
}
