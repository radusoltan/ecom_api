<?php

declare(strict_types=1);

namespace App\Cart\Application\Service;

use App\Cart\Domain\Event\CartAbandoned;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service to identify and process abandoned carts.
 *
 * Business Rules:
 * - Cart is considered abandoned after 24 hours of inactivity
 * - Only send one abandonment email per cart
 * - Only process carts with customer email (authenticated users)
 * - Skip carts that already received abandonment email
 * - Process in batches to avoid memory issues
 */
final readonly class CartAbandonmentService
{
    private const ABANDONMENT_HOURS = 24;
    private const BATCH_SIZE = 100;

    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private CustomerRepositoryInterface $customerRepository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Process abandoned carts and dispatch events for email notification.
     *
     * @return array{processed: int, sent: int, skipped: int, errors: int}
     */
    public function processAbandonedCarts(): array
    {
        $stats = [
            'processed' => 0,
            'sent' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $abandonedBefore = new \DateTimeImmutable(sprintf('-%d hours', self::ABANDONMENT_HOURS));

        $this->logger->info('Starting abandoned cart processing', [
            'abandonedBefore' => $abandonedBefore->format('Y-m-d H:i:s'),
            'batchSize' => self::BATCH_SIZE,
        ]);

        try {
            // Find cart IDs that need abandonment emails
            $cartIds = $this->cartRepository->findAbandonedCartsForEmail(
                $abandonedBefore,
                self::BATCH_SIZE
            );

            $stats['processed'] = count($cartIds);

            if (0 === $stats['processed']) {
                $this->logger->info('No abandoned carts found');

                return $stats;
            }

            $this->logger->info('Found abandoned carts', ['count' => $stats['processed']]);

            // Process each cart
            foreach ($cartIds as $cartId) {
                try {
                    if ($this->processAbandonedCart($cartId)) {
                        ++$stats['sent'];
                    } else {
                        ++$stats['skipped'];
                    }
                } catch (\Throwable $exception) {
                    ++$stats['errors'];
                    $this->logger->error('Error processing abandoned cart', [
                        'cartId' => $cartId->toString(),
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $this->logger->info('Abandoned cart processing completed', $stats);
        } catch (\Throwable $exception) {
            $this->logger->error('Fatal error in abandoned cart processing', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }

        return $stats;
    }

    /**
     * Process a single abandoned cart.
     *
     * @return bool True if event was dispatched, false if cart was skipped
     */
    private function processAbandonedCart(CartId $cartId): bool
    {
        // Load cart
        $cart = $this->cartRepository->findById($cartId);

        if (null === $cart) {
            $this->logger->warning('Cart not found', ['cartId' => $cartId->toString()]);

            return false;
        }

        // Skip if cart has no customer (guest cart without email)
        if (null === $cart->customerId()) {
            $this->logger->debug('Skipping cart without customer', ['cartId' => $cartId->toString()]);

            return false;
        }

        // Load customer to get email
        $customer = $this->customerRepository->findById($cart->customerId(), $cart->tenantId());

        if (null === $customer) {
            $this->logger->warning('Customer not found for cart', [
                'cartId' => $cartId->toString(),
                'customerId' => $cart->customerId()->toString(),
            ]);

            return false;
        }

        // Skip if cart is empty (shouldn't happen due to query, but defensive check)
        if ($cart->isEmpty()) {
            $this->logger->debug('Skipping empty cart', ['cartId' => $cartId->toString()]);

            return false;
        }

        // Dispatch CartAbandoned event
        $event = new CartAbandoned(
            cartId: $cart->id(),
            tenantId: $cart->tenantId(),
            customerId: $cart->customerId(),
            customerEmail: $customer->email()->toString(),
            cartTotal: $cart->calculateTotal(),
            itemCount: $cart->getItemCount(),
            abandonedAt: $cart->updatedAt()
        );

        $this->eventDispatcher->dispatch($event);

        // Mark cart as having received abandonment email
        $this->cartRepository->markAbandonmentEmailSent($cartId);

        $this->logger->info('Dispatched CartAbandoned event', [
            'cartId' => $cartId->toString(),
            'customerEmail' => $customer->email()->toString(),
            'itemCount' => $cart->getItemCount(),
        ]);

        return true;
    }
}
