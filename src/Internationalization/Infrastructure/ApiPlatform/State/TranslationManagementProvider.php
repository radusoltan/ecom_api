<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Internationalization\Application\Query\GetTranslations;
use App\Internationalization\Infrastructure\ApiPlatform\Resource\TranslationManagementResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Translation Management Provider
 *
 * State provider for GET operations on translations.
 */
final readonly class TranslationManagementProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = $context['tenant_id'] ?? throw new \RuntimeException('Tenant ID not found');

        // Single item
        if (isset($uriVariables['id'])) {
            // Simplified - should fetch single translation
            return new TranslationManagementResource(
                id: (int) $uriVariables['id'],
            );
        }

        // Collection
        $filters = [];
        if ($locale = $request?->query->get('locale')) {
            $filters['locale'] = $locale;
        }
        if ($domain = $request?->query->get('domain')) {
            $filters['domain'] = $domain;
        }
        if ($key = $request?->query->get('key')) {
            $filters['key'] = $key;
        }

        $page = (int) ($request?->query->get('page', 1) ?? 1);
        $perPage = (int) ($request?->query->get('per_page', 50) ?? 50);

        $query = new GetTranslations(
            tenantId: $tenantId,
            filters: $filters,
            page: $page,
            perPage: $perPage,
        );

        $envelope = $this->queryBus->dispatch($query);
        $result = $envelope->last(HandledStamp::class)?->getResult();

        $translations = array_map(
            fn (array $data) => new TranslationManagementResource(
                id: $data['id'],
                tenantId: $data['tenantId'],
                locale: $data['locale'],
                domain: $data['domain'],
                key: $data['key'],
                value: $data['value'],
                createdAt: $data['createdAt'],
                updatedAt: $data['updatedAt'],
            ),
            $result['translations']
        );

        return $translations;
    }
}
