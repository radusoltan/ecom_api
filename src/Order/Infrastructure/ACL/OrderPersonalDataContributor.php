<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ACL;

use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Privacy\Domain\Port\PersonalDataContributorInterface;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Order Personal Data Contributor.
 *
 * Provides order data to the Privacy context's PersonalDataInventory
 * without requiring Privacy to directly import Order repositories.
 */
final readonly class OrderPersonalDataContributor implements PersonalDataContributorInterface
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function contextName(): string
    {
        return 'orders';
    }

    public function collectPersonalData(array $subjectContext): array
    {
        $email = $subjectContext['email'] ?? null;
        $tenantId = $subjectContext['tenantId'] ?? null;

        if (null === $email || null === $tenantId) {
            return [];
        }

        $orders = $this->orderRepository->findByCustomerEmail(
            $email,
            TenantId::fromString($tenantId),
        );

        return array_map(function ($order) {
            return [
                'id' => $order->id()->toString(),
                'status' => $order->status()->value(),
                'customerEmail' => $order->customerEmail(),
                'shippingAddress' => [
                    'street' => $order->shippingAddress()->street(),
                    'city' => $order->shippingAddress()->city(),
                    'state' => $order->shippingAddress()->state(),
                    'postalCode' => $order->shippingAddress()->postalCode(),
                    'country' => $order->shippingAddress()->country(),
                ],
                'billingAddress' => [
                    'street' => $order->billingAddress()->street(),
                    'city' => $order->billingAddress()->city(),
                    'state' => $order->billingAddress()->state(),
                    'postalCode' => $order->billingAddress()->postalCode(),
                    'country' => $order->billingAddress()->country(),
                ],
                'total' => (string) $order->total(),
                'createdAt' => $order->createdAt()->format('c'),
                'updatedAt' => $order->updatedAt()->format('c'),
            ];
        }, $orders);
    }

    public function canDeleteData(array $subjectContext): bool
    {
        $email = $subjectContext['email'] ?? null;
        $tenantId = $subjectContext['tenantId'] ?? null;

        if (null === $email || null === $tenantId) {
            return true;
        }

        $orders = $this->orderRepository->findByCustomerEmail(
            $email,
            TenantId::fromString($tenantId),
        );

        foreach ($orders as $order) {
            if (!$order->status()->isFinal()) {
                return false;
            }

            // Check 7-year legal retention period
            $retentionDate = $order->createdAt()->modify('+7 years');
            if ($retentionDate > new \DateTimeImmutable()) {
                return false;
            }
        }

        return true;
    }
}
