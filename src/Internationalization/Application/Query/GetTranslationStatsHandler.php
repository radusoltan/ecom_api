<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Query;

use App\Internationalization\Domain\Service\TranslationCoverageService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Translation Statistics Query Handler.
 *
 * Returns comprehensive coverage statistics grouped by domain and locale.
 *
 * Response format:
 * {
 *   "byDomain": {
 *     "messages": {
 *       "totalKeys": 100,
 *       "locales": {
 *         "en": {"translatedKeys": 100, "coverage": 100.0},
 *         "fr": {"translatedKeys": 85, "coverage": 85.0}
 *       }
 *     }
 *   },
 *   "byLocale": {
 *     "en": {"totalKeys": 300, "translatedKeys": 300, "coverage": 100.0},
 *     "fr": {"totalKeys": 300, "translatedKeys": 250, "coverage": 83.33}
 *   },
 *   "overall": {
 *     "totalKeys": 300,
 *     "domains": 3
 *   }
 * }
 */
#[AsMessageHandler]
final readonly class GetTranslationStatsHandler
{
    public function __construct(
        private TranslationCoverageService $coverageService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetTranslationStats $query): array
    {
        $statistics = $this->coverageService->getStatistics($query->tenantId);

        return $statistics->toArray();
    }
}
