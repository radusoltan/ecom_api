<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Query\GetAllTaxRules;
use App\Tax\Presentation\Api\Resource\TaxRuleResource;
use App\Tax\Presentation\Api\Transformer\TaxRuleResourceTransformer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Provider for retrieving tax rule collections
 */
final class TaxRuleCollectionProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly TaxRuleResourceTransformer $transformer
    ) {
        $this->messageBus = $queryBus;
    }

    /**
     * @return TaxRuleResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Get tenant ID from request headers
        $request = $context['request'] ?? null;
        if (!$request) {
            throw new BadRequestHttpException('Request context is missing');
        }

        $tenantId = $request->headers->get('X-Tenant-ID');
        if (!$tenantId) {
            throw new BadRequestHttpException('X-Tenant-ID header is required');
        }

        // Get query parameters
        $activeOnly = $request->query->get('activeOnly', 'false') === 'true';
        $limit = (int) $request->query->get('limit', 30);
        $offset = (int) $request->query->get('offset', 0);

        // Create query
        $query = new GetAllTaxRules(
            tenantId: TenantId::fromString($tenantId),
            activeOnly: $activeOnly,
            limit: $limit,
            offset: $offset
        );

        // Execute query
        $dtos = $this->handle($query);

        return $this->transformer->fromDTOs($dtos);
    }
}
