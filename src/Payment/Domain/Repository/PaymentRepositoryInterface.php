<?php

declare(strict_types=1);

namespace App\Payment\Domain\Repository;

use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;

interface PaymentRepositoryInterface
{
    public function save(Payment $payment): void;

    public function findById(PaymentId $id, TenantId $tenantId): ?Payment;

    public function findByOrderId(string $orderId, TenantId $tenantId): array;

    public function findAll(TenantId $tenantId): array;

    public function delete(Payment $payment): void;
}
