<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

use App\Internationalization\Domain\Model\TranslationId;
use App\Internationalization\Domain\Repository\TranslationRepositoryInterface;
use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * DeleteTranslation Command Handler.
 *
 * Deletes a Translation aggregate and records domain event.
 */
#[AsMessageHandler]
final readonly class DeleteTranslationHandler
{
    public function __construct(
        private TranslationRepositoryInterface $repository,
        private TranslationCacheService $cacheService,
    ) {
    }

    public function __invoke(DeleteTranslation $command): void
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $translationId = TranslationId::fromString($command->id);

        // Verify translation exists and record delete event
        $translation = $this->repository->findById($translationId, $tenantId);
        if (null === $translation) {
            throw new \DomainException(sprintf('Translation with ID %s not found', $command->id));
        }

        $translation->delete();

        $this->repository->delete($translationId, $tenantId);

        // Invalidate cache for this locale/domain combination
        $this->cacheService->invalidate(
            $translation->tenantId(),
            $translation->locale(),
            $translation->domain()
        );
    }
}
