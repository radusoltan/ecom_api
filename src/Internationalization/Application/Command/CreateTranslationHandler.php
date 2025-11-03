<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * CreateTranslation Command Handler
 *
 * Creates a new translation entry in the system.
 */
#[AsMessageHandler]
final readonly class CreateTranslationHandler
{
    public function __construct(
        private TranslationEntryRepositoryInterface $repository,
        private TranslationCacheService $cacheService,
    ) {}

    public function __invoke(CreateTranslation $command): TranslationEntry
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $locale = LanguageCode::fromString($command->locale);
        $domain = TranslationDomain::fromString($command->domain);
        $key = TranslationKey::fromString($command->key);
        $value = TranslationValue::fromString($command->value);

        // Check if translation already exists
        $existing = $this->repository->findByKey($tenantId, $locale, $domain, $key);
        if (null !== $existing) {
            throw new \DomainException(
                sprintf(
                    'Translation already exists for key "%s" in locale "%s" and domain "%s"',
                    $key->value(),
                    $locale->value(),
                    $domain->value()
                )
            );
        }

        $entry = TranslationEntry::create(
            tenantId: $tenantId,
            locale: $locale,
            domain: $domain,
            key: $key,
            value: $value,
        );

        $this->repository->save($entry);

        // Invalidate cache for this locale/domain combination
        $this->cacheService->invalidate($tenantId, $locale, $domain);

        return $entry;
    }
}
