<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Internationalization\Application\Query\GetTranslationStats;
use App\Shared\Infrastructure\ApiPlatform\State\TenantContextProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Translation Stats Provider.
 *
 * Handles GET /api/translations/stats
 *
 * Returns comprehensive translation coverage statistics
 */
final readonly class TranslationStatsProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private TenantContextProvider $tenantContext,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new BadRequestHttpException('No request found');
        }

        // Create query
        $query = new GetTranslationStats(
            tenantId: $this->tenantContext->getTenantId(),
        );

        // Dispatch query and get result
        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);

        if (null === $handledStamp) {
            throw new \RuntimeException('Query was not handled');
        }

        /** @var array<string, mixed> $result */
        $result = $handledStamp->getResult();

        return $result;
    }
}
