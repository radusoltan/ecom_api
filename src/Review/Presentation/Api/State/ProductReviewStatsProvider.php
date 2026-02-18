<?php

declare(strict_types=1);

namespace App\Review\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Query\GetReviewStats;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class ProductReviewStatsProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly RequestStack $requestStack,
    ) {
        $this->messageBus = $queryBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = $request->headers->get('X-Tenant-ID');

        if (!$tenantId) {
            throw new \RuntimeException('X-Tenant-ID header is required');
        }

        $productId = $uriVariables['productId'] ?? null;
        if (!$productId) {
            throw new \InvalidArgumentException('Product ID is required');
        }

        $query = new GetReviewStats(
            ProductId::fromString($productId),
            TenantId::fromString($tenantId)
        );

        return [$this->handle($query)];
    }
}
