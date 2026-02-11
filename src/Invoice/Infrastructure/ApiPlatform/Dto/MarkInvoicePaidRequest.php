<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\ApiPlatform\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for marking an invoice as paid.
 *
 * Used by POST /api/invoices/{id}/paid endpoint to mark an invoice as paid.
 *
 * Example payload:
 * ```json
 * {
 *   "paidDate": "2025-12-01T14:30:00Z"
 * }
 * ```
 */
final class MarkInvoicePaidRequest
{
    #[Groups(['invoice:write'])]
    #[Assert\NotBlank(message: 'Paid date is required')]
    #[Assert\DateTime(message: 'Paid date must be a valid ISO 8601 date')]
    public string $paidDate;
}
