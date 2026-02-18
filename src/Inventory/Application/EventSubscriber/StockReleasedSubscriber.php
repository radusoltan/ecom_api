<?php

declare(strict_types=1);

namespace App\Inventory\Application\EventSubscriber;

use App\Inventory\Domain\Event\StockReleased;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles StockReleased domain events for audit logging.
 *
 * Business Rules:
 * - Log all stock releases for audit trail
 * - Include reference ID (order/reservation), quantity, and reason
 * - Track why stock was released (cancellation, timeout, etc.)
 */
final readonly class StockReleasedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StockReleased::class => 'onStockReleased',
        ];
    }

    public function onStockReleased(StockReleased $event): void
    {
        $this->logger->info('Stock released', [
            'stockItemId' => $event->stockItemId->toString(),
            'quantity' => $event->quantity->value(),
            'referenceId' => $event->referenceId,
            'reason' => $event->reason,
            'occurredOn' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
