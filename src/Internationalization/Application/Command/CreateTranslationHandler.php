<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

use App\Internationalization\Domain\Model\Translation;
use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationId;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationRepositoryInterface;
use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * CreateTranslation Command Handler.
 *
 * Creates a new Translation aggregate with UUID identity and domain events.
 */
#[AsMessageHandler]
final readonly class CreateTranslationHandler
{
    public function __construct(
        private TranslationRepositoryInterface $repository,
        private TranslationCacheService $cacheService,
    ) {
    }

    public function __invoke(CreateTranslation $command): Translation
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $locale = LanguageCode::fromString($command->locale);
        $domain = TranslationDomain::fromString($command->domain);
        $key = TranslationKey::fromString($command->key);
        $value = TranslationValue::fromString($command->value);

        // Check if translation already exists
        $existing = $this->repository->findByKey($tenantId, $locale, $domain, $key);
        if (null !== $existing) {
            throw new \DomainException(sprintf('Translation already exists for key "%s" in locale "%s" and domain "%s"', $key->value(), $locale->value(), $domain->value()));
        }

        $translation = Translation::create(
            id: TranslationId::generate(),
            tenantId: $tenantId,
            domain: $domain,
            key: $key,
            locale: $locale,
            value: $value,
            parameters: $command->parameters,
        );

        $this->repository->save($translation);

        // Invalidate cache for this locale/domain combination
        $this->cacheService->invalidate($tenantId, $locale, $domain);

        return $translation;
    }
}
