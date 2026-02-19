<?php

declare(strict_types=1);

namespace App\Invoice\Application\EventSubscriber;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Application\Command\GenerateInvoice\GenerateInvoiceCommand;
use App\Invoice\Application\Command\GenerateInvoice\GenerateInvoiceLineDto;
use App\Invoice\Application\Command\IssueInvoice\IssueInvoiceCommand;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Payment\Domain\Event\PaymentCaptured;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Automatically generates and issues invoices when payment is captured.
 *
 * Business Rules:
 * - Invoice is generated only after payment capture (not authorization)
 * - One invoice per order (idempotent - prevents duplicate invoices)
 * - Invoice is immediately issued after generation
 * - Billing address from order is used
 * - Tax information from order is preserved
 * - Failures in invoice generation do not block payment flow
 *
 * Event Flow:
 * 1. PaymentCaptured event received
 * 2. Check if invoice already exists (idempotency)
 * 3. Retrieve order details
 * 4. Generate invoice via GenerateInvoiceCommand
 * 5. Issue invoice via IssueInvoiceCommand
 * 6. Log success/failure
 */
final readonly class PaymentCompletedInvoiceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private InvoiceRepositoryInterface $invoiceRepository,
        private OrderRepositoryInterface $orderRepository,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentCaptured::class => 'onPaymentCaptured',
        ];
    }

    public function onPaymentCaptured(PaymentCaptured $event): void
    {
        // Skip if no order is associated with payment
        if (null === $event->orderId) {
            $this->logger->warning('PaymentCaptured event has no associated order, skipping invoice generation', [
                'paymentId' => $event->paymentId->toString(),
                'tenantId' => $event->tenantId->toString(),
            ]);

            return;
        }

        try {
            $orderId = OrderId::fromString($event->orderId);

            // Idempotency check: skip if invoice already exists
            $existingInvoice = $this->invoiceRepository->findByOrderId($orderId);
            if (null !== $existingInvoice) {
                $this->logger->info('Invoice already exists for order, skipping generation', [
                    'orderId' => $event->orderId,
                    'invoiceId' => $existingInvoice->id()->toString(),
                    'tenantId' => $event->tenantId->toString(),
                ]);

                return;
            }

            // Retrieve order details
            $order = $this->orderRepository->findByIdAndTenant($orderId, $event->tenantId);
            if (null === $order) {
                $this->logger->error('Order not found for invoice generation', [
                    'orderId' => $event->orderId,
                    'tenantId' => $event->tenantId->toString(),
                    'paymentId' => $event->paymentId->toString(),
                ]);

                return;
            }

            // Generate new invoice ID
            $invoiceId = $this->invoiceRepository->nextIdentity();

            // Convert order lines to invoice line DTOs
            $invoiceLines = [];
            foreach ($order->lines() as $orderLine) {
                $invoiceLines[] = new GenerateInvoiceLineDto(
                    description: $orderLine->productName(),
                    quantity: $orderLine->quantity(),
                    unitPriceAmount: $orderLine->unitPrice()->getAmount(),
                    unitPriceCurrency: $orderLine->unitPrice()->getCurrency()->getCurrencyCode(),
                    taxRate: $order->taxRate(),
                    productId: $orderLine->productId()->toString(),
                    sku: null, // SKU not available in OrderLine
                );
            }

            // Extract billing address data
            $billingAddress = $order->billingAddress();
            $billingAddressArray = [
                'name' => $order->customerEmail(), // Use email as name fallback (TODO: get from Customer entity)
                'addressLine1' => $billingAddress->street(),
                'addressLine2' => null, // Address VO doesn't have line2
                'city' => $billingAddress->city(),
                'postalCode' => $billingAddress->postalCode(),
                'country' => $billingAddress->country(),
                'vatNumber' => $order->vatNumber(),
            ];

            // Determine if reverse charge applies (B2B cross-border EU)
            $isReverseCharge = $order->isReverseCharge();

            // Generate invoice
            $generateCommand = new GenerateInvoiceCommand(
                invoiceId: $invoiceId->toString(),
                tenantId: $event->tenantId->toString(),
                orderId: $event->orderId,
                customerId: $this->extractCustomerId($order),
                billingAddress: $billingAddressArray,
                lines: $invoiceLines,
                isReverseCharge: $isReverseCharge,
                notes: $this->buildInvoiceNotes($order),
            );

            $this->commandBus->dispatch($generateCommand);

            // Issue invoice immediately
            $issueCommand = new IssueInvoiceCommand(
                invoiceId: $invoiceId->toString(),
                dueDate: null, // Defaults to +30 days
            );

            $this->commandBus->dispatch($issueCommand);

            $this->logger->info('Invoice generated and issued successfully', [
                'invoiceId' => $invoiceId->toString(),
                'orderId' => $event->orderId,
                'tenantId' => $event->tenantId->toString(),
                'paymentId' => $event->paymentId->toString(),
            ]);
        } catch (\Throwable $exception) {
            // Log error but don't throw - invoice generation failure should not block payment flow
            $this->logger->error('Failed to generate invoice for captured payment', [
                'paymentId' => $event->paymentId->toString(),
                'orderId' => $event->orderId ?? 'N/A',
                'tenantId' => $event->tenantId->toString(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * Extract customer ID from order.
     *
     * TODO: Order model should have customerId field.
     * For now, we generate a placeholder CustomerId from email hash.
     */
    private function extractCustomerId(\App\Order\Domain\Model\Order $order): string
    {
        // TEMPORARY: Generate deterministic UUID from email
        // This should be replaced with actual customerId from Order when available
        $emailHash = md5($order->customerEmail());
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($emailHash, 0, 8),
            substr($emailHash, 8, 4),
            '4'.substr($emailHash, 12, 3), // Version 4 UUID
            '8'.substr($emailHash, 15, 3), // Variant
            substr($emailHash, 18, 12)
        );

        return $uuid;
    }

    /**
     * Build invoice notes from order information.
     */
    private function buildInvoiceNotes(\App\Order\Domain\Model\Order $order): ?string
    {
        $notes = [];

        if ($order->isReverseCharge()) {
            $notes[] = 'Reverse charge, Art. 196 Dir. 2006/112/EC';
            if (null !== $order->vatNumber()) {
                $notes[] = sprintf('Buyer VAT: %s', $order->vatNumber());
            }
        }

        if (null !== $order->couponCode()) {
            $notes[] = sprintf('Coupon code applied: %s', $order->couponCode());
        }

        if (count($order->appliedPromotions()) > 0) {
            $notes[] = sprintf('Promotions applied: %d', count($order->appliedPromotions()));
        }

        if (null !== $order->taxJurisdiction()) {
            $notes[] = sprintf('Tax jurisdiction: %s', $order->taxJurisdiction());
        }

        return count($notes) > 0 ? implode("\n", $notes) : null;
    }
}
