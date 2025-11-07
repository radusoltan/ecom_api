<?php

declare(strict_types=1);

namespace App\Customer\Application\EventSubscriber;

use App\Customer\Application\Command\ChangeSegmentCommand;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Service\CustomerSegmentationService;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Event Subscriber that re-evaluates customer segment after order completion.
 *
 * Listens to OrderPlaced events and triggers segment evaluation based on:
 * - Total amount spent by customer
 * - Current loyalty points
 */
final readonly class OrderCompletedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private OrderRepositoryInterface $orderRepository,
        private CustomerSegmentationService $segmentationService,
        private MessageBusInterface $commandBus,
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlaced::class => 'onOrderPlaced',
        ];
    }

    /**
     * Handle order placed event - re-evaluate customer segment.
     */
    public function onOrderPlaced(OrderPlaced $event): void
    {
        try {
            // Find customer by email and tenant
            $email = Email::fromString($event->customerEmail);
            $customer = $this->customerRepository->findByEmail($email, $event->tenantId);

            if (null === $customer) {
                $this->logger->warning('Customer not found for order', [
                    'email' => $event->customerEmail,
                    'tenant_id' => $event->tenantId->toString(),
                    'order_id' => $event->orderId->toString(),
                ]);

                return;
            }

            // Calculate total spent by this customer (sum of all completed orders)
            $totalSpent = $this->calculateTotalSpent($customer->email(), $event->tenantId);

            // Check if segment should change
            if (!$this->segmentationService->shouldChangeSegment($customer, $totalSpent)) {
                $this->logger->debug('Customer segment does not need to change', [
                    'customer_id' => $customer->id()->toString(),
                    'current_segment' => $customer->segment()->value(),
                    'total_spent' => $totalSpent->getAmount(),
                    'loyalty_points' => $customer->loyaltyPoints(),
                ]);

                return;
            }

            // Get recommended segment
            $recommendedSegment = $this->segmentationService->getRecommendedSegment($customer, $totalSpent);

            $this->logger->info('Changing customer segment based on order activity', [
                'customer_id' => $customer->id()->toString(),
                'old_segment' => $customer->segment()->value(),
                'new_segment' => $recommendedSegment->value(),
                'total_spent' => $totalSpent->getAmount(),
                'loyalty_points' => $customer->loyaltyPoints(),
                'trigger_order_id' => $event->orderId->toString(),
            ]);

            // Dispatch change segment command
            $command = new ChangeSegmentCommand(
                customerId: $customer->id()->toString(),
                tenantId: $event->tenantId->toString(),
                newSegment: $recommendedSegment->value()
            );

            $this->commandBus->dispatch($command);
        } catch (\Exception $e) {
            // Log error but don't fail the order placement
            $this->logger->error('Failed to re-evaluate customer segment after order', [
                'error' => $e->getMessage(),
                'order_id' => $event->orderId->toString(),
                'customer_email' => $event->customerEmail,
            ]);
        }
    }

    /**
     * Calculate total spent by customer across all completed orders.
     */
    private function calculateTotalSpent(Email $customerEmail, \App\Shared\Domain\ValueObject\TenantId $tenantId): Money
    {
        $orders = $this->orderRepository->findByCustomerEmail($customerEmail->value(), $tenantId);

        $totalCents = 0;
        $currency = 'EUR'; // Default currency

        foreach ($orders as $order) {
            // Only count completed/delivered orders
            if (in_array($order->status()->value(), ['completed', 'delivered'], true)) {
                $totalCents += $order->total()->getAmount();
                $currency = $order->total()->getCurrency();
            }
        }

        return Money::fromScalars($totalCents, $currency);
    }
}
