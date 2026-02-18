<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

/**
 * Reservation Value Object.
 *
 * Represents a stock reservation with expiry time
 *
 * Business Rules:
 * - Reservations expire after 15 minutes of inactivity
 * - Each reservation is identified by a unique reservationId (typically cart/session ID)
 * - Expired reservations should be automatically released
 */
final readonly class Reservation
{
    private const TIMEOUT_MINUTES = 15;

    private function __construct(
        private string $reservationId,
        private Quantity $quantity,
        private \DateTimeImmutable $reservedAt,
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public static function create(
        string $reservationId,
        Quantity $quantity,
        ?\DateTimeImmutable $reservedAt = null,
    ): self {
        $reservedAt = $reservedAt ?? new \DateTimeImmutable();
        $expiresAt = $reservedAt->modify(sprintf('+%d minutes', self::TIMEOUT_MINUTES));

        return new self($reservationId, $quantity, $reservedAt, $expiresAt);
    }

    public function reservationId(): string
    {
        return $this->reservationId;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function reservedAt(): \DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable();

        return $now >= $this->expiresAt;
    }

    public function equals(self $other): bool
    {
        return $this->reservationId === $other->reservationId;
    }

    /**
     * Extend reservation expiry time (e.g., when user is still active).
     */
    public function extend(?\DateTimeImmutable $now = null): self
    {
        $now = $now ?? new \DateTimeImmutable();
        $newExpiresAt = $now->modify(sprintf('+%d minutes', self::TIMEOUT_MINUTES));

        return new self(
            $this->reservationId,
            $this->quantity,
            $this->reservedAt,
            $newExpiresAt
        );
    }
}
