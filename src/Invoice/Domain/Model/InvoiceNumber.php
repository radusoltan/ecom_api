<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Model;

/**
 * Invoice Number Value Object.
 *
 * Business Rules:
 * - Format: INV-{YEAR}-{SEQUENCE} (e.g., INV-2025-000001)
 * - Year: 4 digits (YYYY)
 * - Sequence: 6 digits, zero-padded (000001-999999)
 * - Human-readable identifier for invoices
 * - Unique within tenant/year combination
 */
final readonly class InvoiceNumber
{
    private const PATTERN = '/^INV-(\d{4})-(\d{6})$/';
    private const FORMAT = 'INV-%04d-%06d';
    private const MIN_SEQUENCE = 1;
    private const MAX_SEQUENCE = 999999;

    private function __construct(
        private string $value,
        private int $year,
        private int $sequence,
    ) {
        if (!preg_match(self::PATTERN, $value, $matches)) {
            throw new \InvalidArgumentException(sprintf('Invalid InvoiceNumber format: "%s". Expected format: INV-YYYY-NNNNNN (e.g., INV-2025-000001)', $value));
        }

        if ($year < 2000 || $year > 2099) {
            throw new \InvalidArgumentException(sprintf('Invalid year: %d. Year must be between 2000 and 2099', $year));
        }

        if ($sequence < self::MIN_SEQUENCE || $sequence > self::MAX_SEQUENCE) {
            throw new \InvalidArgumentException(sprintf('Invalid sequence: %d. Sequence must be between %d and %d', $sequence, self::MIN_SEQUENCE, self::MAX_SEQUENCE));
        }

        // Verify extracted values match
        if ((int) $matches[1] !== $year || (int) $matches[2] !== $sequence) {
            throw new \InvalidArgumentException('InvoiceNumber components do not match the formatted value');
        }
    }

    public static function create(int $year, int $sequence): self
    {
        $value = sprintf(self::FORMAT, $year, $sequence);

        return new self($value, $year, $sequence);
    }

    public static function fromString(string $value): self
    {
        if (!preg_match(self::PATTERN, $value, $matches)) {
            throw new \InvalidArgumentException(sprintf('Invalid InvoiceNumber format: "%s". Expected format: INV-YYYY-NNNNNN (e.g., INV-2025-000001)', $value));
        }

        $year = (int) $matches[1];
        $sequence = (int) $matches[2];

        return new self($value, $year, $sequence);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
