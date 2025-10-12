<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Repository;

use App\Inventory\Domain\Model\StockReservation;
use App\Shared\Domain\ValueObject\TenantId;

interface StockReservationRepositoryInterface
{
    public function save(StockReservation $reservation): void;

    public function findByReservationId(string $reservationId): ?StockReservation;

    /**
     * Find all expired reservations that haven't been released yet
     *
     * @return StockReservation[]
     */
    public function findExpiredReservations(?\DateTimeImmutable $now = null): array;

    /**
     * Find all active (non-released, non-expired) reservations for a tenant
     *
     * @return StockReservation[]
     */
    public function findActiveByTenant(TenantId $tenantId): array;

    public function delete(StockReservation $reservation): void;
}
