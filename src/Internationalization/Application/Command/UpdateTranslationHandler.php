<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

use App\Internationalization\Domain\Model\Translation;
use App\Internationalization\Domain\Model\TranslationId;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationRepositoryInterface;
use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * UpdateTranslation Command Handler.
 *
 * Updates an existing Translation aggregate's value and parameters.
 */
#[AsMessageHandler]
final readonly class UpdateTranslationHandler
{
    public function __construct(
        private TranslationRepositoryInterface $repository,
        private TranslationCacheService $cacheService,
    ) {
    }

    public function __invoke(UpdateTranslation $command): Translation
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $translationId = TranslationId::fromString($command->id);

        $translation = $this->repository->findById($translationId, $tenantId);
        if (null === $translation) {
            throw new \DomainException(sprintf('Translation with ID %s not found', $command->id));
        }

        $newValue = TranslationValue::fromString($command->value);
        $translation->updateValue($newValue, $command->parameters);

        $this->repository->save($translation);

        // Invalidate cache for this locale/domain combination
        $this->cacheService->invalidate(
            $translation->tenantId(),
            $translation->locale(),
            $translation->domain()
        );

        return $translation;
    }
}
