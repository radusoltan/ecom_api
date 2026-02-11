<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Repository;

use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Model\NotificationId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Notification Repository Interface (Port).
 */
interface NotificationRepositoryInterface
{
    /**
     * Save a notification to persistence and dispatch domain events.
     */
    public function save(Notification $notification): void;

    /**
     * Find a notification by ID within a tenant.
     */
    public function findById(NotificationId $id, TenantId $tenantId): ?Notification;

    /**
     * Find all notifications for a tenant.
     *
     * @return Notification[]
     */
    public function findByTenant(TenantId $tenantId): array;

    /**
     * Find all pending notifications for a tenant.
     *
     * @return Notification[]
     */
    public function findPending(TenantId $tenantId): array;

    /**
     * Find all failed notifications for a tenant.
     *
     * @return Notification[]
     */
    public function findFailed(TenantId $tenantId): array;
}
