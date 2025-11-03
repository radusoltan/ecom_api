<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Query;

use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * GetTranslations Query Handler
 *
 * Retrieves translations with optional filters and pagination.
 */
#[AsMessageHandler]
final readonly class GetTranslationsHandler
{
    public function __construct(
        private TranslationEntryRepositoryInterface $repository,
    ) {}

    /**
     * @return array{
     *     translations: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     perPage: int,
     *     totalPages: int
     * }
     */
    public function __invoke(GetTranslations $query): array
    {
        $tenantId = TenantId::fromString($query->tenantId);

        $translations = $this->repository->findAll(
            tenantId: $tenantId,
            filters: $query->filters,
            page: $query->page,
            perPage: $query->perPage,
        );

        $total = $this->repository->count($tenantId, $query->filters);
        $totalPages = (int) ceil($total / $query->perPage);

        return [
            'translations' => array_map(fn ($entry) => $entry->toArray(), $translations),
            'total' => $total,
            'page' => $query->page,
            'perPage' => $query->perPage,
            'totalPages' => $totalPages,
        ];
    }
}
