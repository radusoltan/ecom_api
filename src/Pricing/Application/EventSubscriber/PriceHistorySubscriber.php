<?php

declare(strict_types=1);

namespace App\Pricing\Application\EventSubscriber;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Event\FlashSaleActivated;
use App\Pricing\Domain\Event\FlashSaleEnded;
use App\Pricing\Domain\Event\PricingRuleAdded;
use App\Pricing\Domain\Event\PricingRuleRemoved;
use App\Pricing\Domain\Event\PromotionActivated;
use App\Pricing\Domain\Event\PromotionDeactivated;
use App\Pricing\Domain\Repository\PriceHistoryRepositoryInterface;
use App\Pricing\Domain\ValueObject\PriceChange;
use App\Pricing\Domain\ValueObject\PriceChangeSource;
use App\Shared\Domain\ValueObject\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber that records price history when pricing events occur.
 *
 * This subscriber listens to pricing domain events and creates audit trail
 * records for compliance and analytics.
 *
 * Business Rules:
 * - All price changes must be recorded
 * - Records are immutable (no updates/deletes)
 * - Must not fail main business operation (catch exceptions)
 * - Log errors but don't throw
 */
final readonly class PriceHistorySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PriceHistoryRepositoryInterface $priceHistoryRepository,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PricingRuleAdded::class => 'onPricingRuleAdded',
            PricingRuleRemoved::class => 'onPricingRuleRemoved',
            PromotionActivated::class => 'onPromotionActivated',
            PromotionDeactivated::class => 'onPromotionDeactivated',
            FlashSaleActivated::class => 'onFlashSaleActivated',
            FlashSaleEnded::class => 'onFlashSaleEnded',
        ];
    }

    /**
     * Handle pricing rule added event.
     * Records the new price in history.
     */
    public function onPricingRuleAdded(PricingRuleAdded $event): void
    {
        try {
            $ruleData = $event->ruleData();

            // Extract product ID and new price from rule data
            if (!isset($ruleData['product_id'], $ruleData['price'])) {
                $this->logger->warning('PricingRuleAdded event missing required data', [
                    'event' => $event->toArray(),
                ]);

                return;
            }

            $productId = ProductId::fromString($ruleData['product_id']);
            $newPrice = $this->extractMoneyFromRuleData($ruleData['price']);
            $oldPrice = isset($ruleData['old_price']) ? $this->extractMoneyFromRuleData($ruleData['old_price']) : null;

            $priceChange = PriceChange::create(
                $productId,
                $oldPrice,
                $newPrice,
                $ruleData['reason'] ?? 'Price list rule applied',
                PriceChangeSource::MANUAL,
                $event->occurredOn()
            );

            // Extract tenant ID from price list ID (assuming format)
            $tenantId = $this->extractTenantIdFromPriceListId($event->priceListId());

            $this->priceHistoryRepository->save($priceChange, $tenantId);

            $this->logger->info('Price history recorded for pricing rule added', [
                'product_id' => $productId->toString(),
                'price_list_id' => $event->priceListId(),
                'new_price' => $newPrice->toArray(),
            ]);
        } catch (\Throwable $e) {
            // Don't fail the main operation, just log the error
            $this->logger->error('Failed to record price history for pricing rule added', [
                'error' => $e->getMessage(),
                'event' => $event->toArray(),
            ]);
        }
    }

    /**
     * Handle pricing rule removed event.
     * Records the price removal in history.
     */
    public function onPricingRuleRemoved(PricingRuleRemoved $event): void
    {
        try {
            $ruleData = $event->ruleData();

            // Extract product ID and old price from rule data
            if (!isset($ruleData['product_id'])) {
                $this->logger->warning('PricingRuleRemoved event missing product_id', [
                    'event' => $event->toArray(),
                ]);

                return;
            }

            $productId = ProductId::fromString($ruleData['product_id']);
            $oldPrice = isset($ruleData['price']) ? $this->extractMoneyFromRuleData($ruleData['price']) : null;

            $priceChange = PriceChange::create(
                $productId,
                $oldPrice,
                null, // Price removed, no new price
                $ruleData['reason'] ?? 'Price list rule removed',
                PriceChangeSource::MANUAL,
                $event->occurredOn()
            );

            // Extract tenant ID from price list ID
            $tenantId = $this->extractTenantIdFromPriceListId($event->priceListId());

            $this->priceHistoryRepository->save($priceChange, $tenantId);

            $this->logger->info('Price history recorded for pricing rule removed', [
                'product_id' => $productId->toString(),
                'price_list_id' => $event->priceListId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record price history for pricing rule removed', [
                'error' => $e->getMessage(),
                'event' => $event->toArray(),
            ]);
        }
    }

    /**
     * Handle promotion activated event.
     * Records the promotional price change.
     */
    public function onPromotionActivated(PromotionActivated $event): void
    {
        try {
            // Note: Promotion might apply to multiple products
            // This is a simplified implementation - you may need to fetch
            // the promotion entity to get all affected products and their prices

            $this->logger->info('Promotion activated, price history tracking needed', [
                'promotion_id' => $event->promotionId()->toString(),
                'tenant_id' => $event->tenantId()->toString(),
            ]);

            // TODO: Implement full promotion price tracking
            // This requires fetching the promotion entity and all affected products
            // For now, this is a placeholder that logs the event
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record price history for promotion activated', [
                'error' => $e->getMessage(),
                'promotion_id' => $event->promotionId()->toString(),
            ]);
        }
    }

    /**
     * Handle promotion deactivated event.
     * Records the price restoration after promotion ends.
     */
    public function onPromotionDeactivated(PromotionDeactivated $event): void
    {
        try {
            $this->logger->info('Promotion deactivated, price history tracking needed', [
                'promotion_id' => $event->promotionId()->toString(),
                'tenant_id' => $event->tenantId()->toString(),
            ]);

            // TODO: Implement full promotion price tracking
            // This requires fetching the promotion entity and restoring original prices
            // For now, this is a placeholder that logs the event
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record price history for promotion deactivated', [
                'error' => $e->getMessage(),
                'promotion_id' => $event->promotionId()->toString(),
            ]);
        }
    }

    /**
     * Extract Money value object from rule data.
     *
     * @param array<string, mixed>|float|string $priceData
     */
    private function extractMoneyFromRuleData(array|float|string $priceData): Money
    {
        if (is_array($priceData)) {
            // Assume format: ['amount' => 99.99, 'currency' => 'USD']
            return Money::of(
                (string) $priceData['amount'],
                $priceData['currency']
            );
        }

        // Assume USD if only amount provided (fallback)
        return Money::of((string) $priceData, 'USD');
    }

    /**
     * Handle flash sale activated event.
     * Records the flash sale price change for all affected products.
     */
    public function onFlashSaleActivated(FlashSaleActivated $event): void
    {
        try {
            foreach ($event->productIds() as $productId) {
                // TODO: Fetch current product price and flash sale price
                // For now, log that flash sale was activated
                $this->logger->info('Flash sale activated, price history needed', [
                    'flash_sale_id' => $event->flashSaleId()->toString(),
                    'product_id' => $productId->toString(),
                    'flash_sale_name' => $event->name(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record price history for flash sale activated', [
                'error' => $e->getMessage(),
                'flash_sale_id' => $event->flashSaleId()->toString(),
            ]);
        }
    }

    /**
     * Handle flash sale ended event.
     * Records the price restoration after flash sale ends.
     */
    public function onFlashSaleEnded(FlashSaleEnded $event): void
    {
        try {
            $this->logger->info('Flash sale ended, price history tracking needed', [
                'flash_sale_id' => $event->flashSaleId()->toString(),
                'tenant_id' => $event->tenantId()->toString(),
            ]);

            // TODO: Implement full flash sale price tracking
            // This requires fetching the flash sale entity and restoring original prices
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record price history for flash sale ended', [
                'error' => $e->getMessage(),
                'flash_sale_id' => $event->flashSaleId()->toString(),
            ]);
        }
    }

    /**
     * Extract tenant ID from price list ID.
     * This is a temporary implementation - you may need to fetch from repository.
     */
    private function extractTenantIdFromPriceListId(string $priceListId): \App\Shared\Domain\ValueObject\TenantId
    {
        // TODO: Implement proper tenant ID extraction
        // For now, this is a placeholder - you should fetch the price list
        // from repository to get its tenant ID

        // Temporary: assume we can extract from context or fetch from DB
        throw new \LogicException('Tenant ID extraction not yet implemented - need to fetch from PriceList repository');
    }
}
