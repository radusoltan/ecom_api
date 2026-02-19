<?php

declare(strict_types=1);

namespace App\Payment\Application\Service;

use App\Order\Application\Query\GetOrderByIdQuery;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Resolves customer email for payment notification emails.
 *
 * Cross-context communication: Payment -> Order (via query bus).
 * Looks up the order associated with a payment and extracts the customer email.
 */
final readonly class PaymentCustomerEmailResolver
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private PaymentRepositoryInterface $paymentRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Resolve customer email from an order ID.
     */
    public function resolveByOrderId(string $orderId, string $tenantId): ?string
    {
        try {
            $envelope = $this->queryBus->dispatch(
                new GetOrderByIdQuery($orderId, $tenantId)
            );

            $handledStamp = $envelope->last(HandledStamp::class);

            if (!$handledStamp instanceof HandledStamp) {
                $this->logger->warning('No handler result for GetOrderByIdQuery', [
                    'order_id' => $orderId,
                ]);

                return null;
            }

            $orderDTO = $handledStamp->getResult();

            if (null === $orderDTO) {
                $this->logger->warning('Order not found for email resolution', [
                    'order_id' => $orderId,
                ]);

                return null;
            }

            return $orderDTO->customerEmail;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to resolve customer email from order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve customer email from a payment ID (looks up payment -> orderId -> order).
     */
    public function resolveByPaymentId(PaymentId $paymentId, TenantId $tenantId): ?string
    {
        try {
            $payment = $this->paymentRepository->findById($paymentId);

            if (null === $payment) {
                $this->logger->warning('Payment not found for email resolution', [
                    'payment_id' => $paymentId->toString(),
                ]);

                return null;
            }

            return $this->resolveByOrderId($payment->orderId(), $tenantId->toString());
        } catch (\Throwable $e) {
            $this->logger->error('Failed to resolve customer email from payment', [
                'payment_id' => $paymentId->toString(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
