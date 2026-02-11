<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Model;

/**
 * Invoice Status Value Object (Enum).
 *
 * Business Rules:
 * - State machine transitions:
 *   - DRAFT → ISSUED (issue the invoice)
 *   - ISSUED → PAID (mark as paid)
 *   - ISSUED → CANCELLED (cancel before payment)
 *   - ISSUED → CREDITED (create credit note)
 * - Final states: PAID, CANCELLED, CREDITED (no transitions allowed)
 * - Draft invoices can be edited
 * - Issued invoices are immutable (except status changes)
 */
enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case CREDITED = 'credited';

    /**
     * Valid state transitions.
     */
    private const VALID_TRANSITIONS = [
        'draft' => ['issued'],
        'issued' => ['paid', 'cancelled', 'credited'],
        'paid' => [],
        'cancelled' => [],
        'credited' => [],
    ];

    /**
     * Check if transition to new status is allowed.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus->value, self::VALID_TRANSITIONS[$this->value] ?? [], true);
    }

    /**
     * Check if invoice is in draft state.
     */
    public function isDraft(): bool
    {
        return self::DRAFT === $this;
    }

    /**
     * Check if invoice is issued.
     */
    public function isIssued(): bool
    {
        return self::ISSUED === $this;
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return self::PAID === $this;
    }

    /**
     * Check if invoice is cancelled.
     */
    public function isCancelled(): bool
    {
        return self::CANCELLED === $this;
    }

    /**
     * Check if invoice has been credited.
     */
    public function isCredited(): bool
    {
        return self::CREDITED === $this;
    }

    /**
     * Check if invoice is in a final state (no further transitions allowed).
     */
    public function isFinal(): bool
    {
        return self::PAID === $this
            || self::CANCELLED === $this
            || self::CREDITED === $this;
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ISSUED => 'Issued',
            self::PAID => 'Paid',
            self::CANCELLED => 'Cancelled',
            self::CREDITED => 'Credited',
        };
    }
}
