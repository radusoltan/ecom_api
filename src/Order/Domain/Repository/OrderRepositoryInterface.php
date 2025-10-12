<?php

declare(strict_types=1);

namespace App\Order\Domain\Repository;

use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;

interface OrderRepositoryInterface
{
    public function save(Order $order): void;

    public function findById(OrderId $id): ?Order;

    public function findByIdAndTenant(OrderId $id, TenantId $tenantId): ?Order;

    public function findByCustomerEmail(string $email, TenantId $tenantId): array;

    public function findAllByTenant(TenantId $tenantId): array;
}
