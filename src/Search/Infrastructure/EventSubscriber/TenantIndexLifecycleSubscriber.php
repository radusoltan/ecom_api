<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventSubscriber;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use App\Tenant\Domain\Event\TenantCreated;
use App\Tenant\Domain\Event\TenantDeactivated;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Manages Elasticsearch index lifecycle in response to tenant events.
 *
 * - TenantCreated: Creates product and category indices for all supported locales
 * - TenantDeactivated: Deletes all indices for the deactivated tenant
 */
final readonly class TenantIndexLifecycleSubscriber implements EventSubscriberInterface
{
    /** @var string[] */
    private const SUPPORTED_LOCALES = ['en_US', 'ro_RO', 'de_DE', 'fr_FR', 'es_ES', 'it_IT'];

    public function __construct(
        private IndexManager $indexManager,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TenantCreated::class => 'onTenantCreated',
            TenantDeactivated::class => 'onTenantDeactivated',
        ];
    }

    public function onTenantCreated(TenantCreated $event): void
    {
        foreach (self::SUPPORTED_LOCALES as $localeString) {
            try {
                $locale = Locale::fromString($localeString);
                $this->indexManager->createProductIndex($event->tenantId, $locale);
                $this->indexManager->createCategoryIndex($event->tenantId, $locale);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to create ES index for tenant {tenantId}, locale {locale}: {error}', [
                    'tenantId' => $event->tenantId->toString(),
                    'locale' => $localeString,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Created ES indices for tenant {tenantId}', [
            'tenantId' => $event->tenantId->toString(),
        ]);
    }

    public function onTenantDeactivated(TenantDeactivated $event): void
    {
        foreach (self::SUPPORTED_LOCALES as $localeString) {
            try {
                $locale = Locale::fromString($localeString);
                $productIndex = $this->indexManager->getProductIndexName($event->tenantId, $locale);
                $categoryIndex = $this->indexManager->getCategoryIndexName($event->tenantId, $locale);

                $this->indexManager->deleteIndex($productIndex);
                $this->indexManager->deleteIndex($categoryIndex);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to delete ES index for tenant {tenantId}, locale {locale}: {error}', [
                    'tenantId' => $event->tenantId->toString(),
                    'locale' => $localeString,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Deleted ES indices for deactivated tenant {tenantId}', [
            'tenantId' => $event->tenantId->toString(),
        ]);
    }
}
