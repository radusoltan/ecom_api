<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * DeleteTranslation Command Handler
 *
 * Deletes a translation entry from the system.
 */
#[AsMessageHandler]
final readonly class DeleteTranslationHandler
{
    public function __construct(
        private TranslationEntryRepositoryInterface $repository,
        private TranslationCacheService $cacheService,
    ) {}

    public function __invoke(DeleteTranslation $command): void
    {
        $tenantId = TenantId::fromString($command->tenantId);

        // Verify translation exists before deleting
        $entry = $this->repository->findById($command->id, $tenantId);
        if (null === $entry) {
            throw new \DomainException(
                sprintf('Translation with ID %d not found', $command->id)
            );
        }

        $this->repository->delete($command->id, $tenantId);

        // Invalidate cache for this locale/domain combination
        $this->cacheService->invalidate(
            $entry->tenantId,
            $entry->locale,
            $entry->domain
        );
    }
}
