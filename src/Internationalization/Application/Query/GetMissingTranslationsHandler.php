<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Query;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Service\TranslationCoverageService;
use App\Shared\Domain\ValueObject\LanguageCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Missing Translations Query Handler.
 *
 * Returns list of translation keys that exist in source locale
 * but are missing in target locale.
 *
 * Response format:
 * [
 *   {key: "hello.world", domain: "messages"},
 *   {key: "email.invalid", domain: "validators"}
 * ]
 */
#[AsMessageHandler]
final readonly class GetMissingTranslationsHandler
{
    public function __construct(
        private TranslationCoverageService $coverageService,
    ) {
    }

    /**
     * @return array<array{key: string, domain: string}>
     */
    public function __invoke(GetMissingTranslations $query): array
    {
        $targetLocale = LanguageCode::fromString($query->targetLocale);

        $sourceLocale = null !== $query->sourceLocale
            ? LanguageCode::fromString($query->sourceLocale)
            : null;

        $domain = null !== $query->domain
            ? TranslationDomain::fromString($query->domain)
            : null;

        return $this->coverageService->findMissingTranslations(
            tenantId: $query->tenantId,
            targetLocale: $targetLocale,
            sourceLocale: $sourceLocale,
            domain: $domain,
        );
    }
}
