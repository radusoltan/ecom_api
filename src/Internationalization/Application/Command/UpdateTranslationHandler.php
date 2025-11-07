<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * UpdateTranslation Command Handler.
 *
 * Updates an existing translation entry's value.
 */
#[AsMessageHandler]
final readonly class UpdateTranslationHandler
{
    public function __construct(
        private TranslationEntryRepositoryInterface $repository,
        private TranslationCacheService $cacheService,
    ) {
    }

    public function __invoke(UpdateTranslation $command): TranslationEntry
    {
        $tenantId = TenantId::fromString($command->tenantId);

        $entry = $this->repository->findById($command->id, $tenantId);
        if (null === $entry) {
            throw new \DomainException(sprintf('Translation with ID %d not found', $command->id));
        }

        $newValue = TranslationValue::fromString($command->value);
        $updatedEntry = $entry->updateValue($newValue);

        $this->repository->save($updatedEntry);

        // Invalidate cache for this locale/domain combination
        $this->cacheService->invalidate(
            $updatedEntry->tenantId,
            $updatedEntry->locale,
            $updatedEntry->domain
        );

        return $updatedEntry;
    }
}
