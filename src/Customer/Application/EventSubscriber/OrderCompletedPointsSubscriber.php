<?php

declare(strict_types=1);

namespace App\Customer\Application\EventSubscriber;

use App\Customer\Domain\Event\LoyaltyPointsAwarded;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Order\Domain\Event\OrderPaid;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event Subscriber for awarding loyalty points when orders are paid.
 *
 * Listens to OrderPaid events and awards points based on:
 * - Order total amount
 * - Loyalty program earning rate
 * - Loyalty program minimum order value
 *
 * Business Rules:
 * - Points are only awarded when payment is captured (OrderPaid event)
 * - Order total must meet minimum order value threshold
 * - Loyalty program must be active
 * - Points are rounded down to nearest whole number
 */
final readonly class OrderCompletedPointsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private OrderRepositoryInterface $orderRepository,
        private LoyaltyProgramRepositoryInterface $loyaltyProgramRepository,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPaid::class => 'onOrderPaid',
        ];
    }

    /**
     * Handle order paid event - award loyalty points to customer.
     */
    public function onOrderPaid(OrderPaid $event): void
    {
        try {
            $tenantId = TenantId::fromString($event->tenantId->toString());

            // 1. Get loyalty program
            $program = $this->loyaltyProgramRepository->findByTenantId($tenantId);
            if (null === $program) {
                $this->logger->debug('No loyalty program found for tenant', [
                    'tenant_id' => $tenantId->toString(),
                    'order_id' => $event->orderId,
                ]);

                return;
            }

            if (!$program->isActive()) {
                $this->logger->debug('Loyalty program is not active, skipping points award', [
                    'tenant_id' => $tenantId->toString(),
                    'order_id' => $event->orderId,
                    'program_id' => $program->id()->toString(),
                ]);

                return;
            }

            // 2. Get order details
            $orderId = \App\Order\Domain\Model\OrderId::fromString($event->orderId);
            $order = $this->orderRepository->findByIdAndTenant($orderId, $tenantId);
            if (null === $order) {
                $this->logger->warning('Order not found for points award', [
                    'order_id' => $event->orderId,
                    'tenant_id' => $tenantId->toString(),
                ]);

                return;
            }

            // 3. Calculate points to award
            $pointsToAward = $program->calculatePointsForOrder($order->total());

            if ($pointsToAward <= 0) {
                $this->logger->debug('No points to award for order (below minimum or zero)', [
                    'order_id' => $event->orderId,
                    'order_total' => $order->total()->amount(),
                    'min_order_value' => $program->minOrderValue()->amount(),
                ]);

                return;
            }

            // 4. Find customer and award points
            $email = Email::fromString($order->customerEmail());
            $customer = $this->customerRepository->findByEmail($email, $tenantId);

            if (null === $customer) {
                $this->logger->warning('Customer not found for points award', [
                    'email' => $order->customerEmail(),
                    'tenant_id' => $tenantId->toString(),
                    'order_id' => $event->orderId,
                ]);

                return;
            }

            // Award points
            $customer->awardLoyaltyPoints(
                $pointsToAward,
                sprintf('Points earned from order %s', $event->orderId)
            );

            // Save customer (this will dispatch LoyaltyPointsAwarded event)
            $this->customerRepository->save($customer);

            $this->logger->info('Loyalty points awarded for order payment', [
                'customer_id' => $customer->id()->toString(),
                'order_id' => $event->orderId,
                'points_awarded' => $pointsToAward,
                'new_balance' => $customer->loyaltyPoints(),
                'order_total' => $order->total()->amount(),
                'earning_rate' => $program->earningRate()->toFloat(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the order payment
            $this->logger->error('Failed to award loyalty points for order payment', [
                'error' => $e->getMessage(),
                'order_id' => $event->orderId,
                'tenant_id' => $event->tenantId->toString(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
